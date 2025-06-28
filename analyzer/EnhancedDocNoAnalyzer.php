<?php

namespace Analyzer;

use Models\InvoiceTemplateManager;
use Models\InvoiceTemplatePattern;
use Models\BucketInfo;
use Models\NumberInfo;

class EnhancedDocNoAnalyzer {
    private array $predefinedTemplates = [];
    private array $buckets = [];
    private array $compiledRegexes = [];
    private array $templateInvoiceGroups = [];
    private array $data = [];
    private array $likelyYears = [];
    private YearAnalyzer $yearAnalyzer;
    private PatternAnalyzer $patternAnalyzer;
    private BucketAnalyzer $bucketAnalyzer;
    private InvoiceTemplateManager $templateManager;

    public function __construct() {
        $this->yearAnalyzer = new YearAnalyzer();
        $this->patternAnalyzer = new PatternAnalyzer();
        $this->bucketAnalyzer = new BucketAnalyzer();
        
        // Use default configuration
        $this->templateManager = InvoiceTemplateManager::createDefault();
        $this->initializeTemplates();
    }

    public function setTemplateManager(InvoiceTemplateManager $manager): void {
        $this->templateManager = $manager;
        $this->initializeTemplates();
    }

    private function initializeTemplates(): void {
        $this->predefinedTemplates = [];
        $this->compiledRegexes = [];
        
        foreach ($this->templateManager->getEnabledTemplates() as $template) {
            $this->predefinedTemplates[] = $template;
            try {
                $this->compiledRegexes[$template->getName()] = $this->patternAnalyzer->compilePattern($template->getPattern());
            } catch (\Exception $e) {
                echo "Warning: Invalid regex in template '{$template->getName()}': " . $e->getMessage() . "\n";
                continue;
            }
        }

        echo "Successfully loaded " . count($this->predefinedTemplates) . " predefined templates\n";
    }

    public function ProcessDocNumber(array $data): array {
        if (empty($data)) {
            return [];
        }

        $this->data = $this->preprocessInvoiceNumbers($data);
        if (empty($this->data)) {
            return [];
        }

        // Initialize groups
        foreach ($this->predefinedTemplates as $template) {
            $this->templateInvoiceGroups[$template->getName()] = [];
        }

        $unmatchedInvoices = [];
        $this->groupInvoicesByTemplate($unmatchedInvoices);
        $this->processTemplateGroups();
        $this->processUnmatchedInvoices($unmatchedInvoices);

        return $this->bucketAnalyzer->getAllBuckets();
    }

    private function preprocessInvoiceNumbers(array $data): array {
        $cleanData = [];
        foreach ($data as $row) {
            if (!empty($row)) {
                $invoiceClean = trim($row[0]);
                if ($invoiceClean !== '' && $invoiceClean !== 'NAN') {
                    $cleanData[] = [$row[0], $invoiceClean];
                }
            }
        }
        return $cleanData;
    }

    private function groupInvoicesByTemplate(array &$unmatchedInvoices): void {
        foreach ($this->data as $row) {
            if (count($row) < 2) {
                continue;
            }
            $invoice = $row[1];  // cleaned version
            $original = $row[0]; // original version
            $matched = false;

            foreach ($this->predefinedTemplates as $template) {
                if (isset($this->compiledRegexes[$template->getName()])) {
                    if (preg_match($this->compiledRegexes[$template->getName()], $invoice)) {
                        $this->templateInvoiceGroups[$template->getName()][] = $original;
                        $matched = true;
                        break;
                    }
                }
            }

            if (!$matched) {
                $unmatchedInvoices[] = $original;
            }
        }
    }

    private function processTemplateGroups(): void {
        foreach ($this->templateInvoiceGroups as $templateName => $invoices) {
            if (empty($invoices)) {
                continue;
            }

            $templateObj = $this->findTemplate($templateName);
            if ($templateObj->getName() === '') {
                continue;
            }

            $groupedInvoices = $this->groupInvoicesByPattern($invoices, $templateObj);
            $this->processInvoiceGroups($groupedInvoices, $templateObj);
        }
    }

    private function processUnmatchedInvoices(array $unmatchedInvoices): void {
        if (!empty($unmatchedInvoices)) {
            $bucketKey = "unmatched|NOPFX";
            $sampleInvoices = $this->bucketAnalyzer->getSampleInvoices($unmatchedInvoices, 5);
            
            $prefixes = [];
            foreach ($unmatchedInvoices as $invoice) {
                list($pattern, $numbersInfo) = $this->patternAnalyzer->createSmartPattern($invoice, []);
                $smartPattern = $this->patternAnalyzer->createValueBasedPattern($invoice, $numbersInfo);
                
                if (preg_match('/^([^\[\]]+)(?:\[SEQ\]|\[YEAR\])/', $smartPattern, $matches)) {
                    $prefix = trim($matches[1]);
                    if (!empty($prefix)) {
                        $prefixes[] = $prefix;
                    }
                } else {
                    $prefixes[] = $smartPattern;
                }
            }
            
            $minPrefix = !empty($prefixes) ? min($prefixes) : 'NOPFX';
            $maxPrefix = !empty($prefixes) ? max($prefixes) : 'NOPFX';
            
            $this->bucketAnalyzer->createBucket($bucketKey, [
                'template_name' => $bucketKey,
                'pattern' => '.*',
                'smart_pattern' => '[UNMATCHED]',
                'from_value' => 0,
                'to_value' => 0,
                'alpha_from_value' => $minPrefix,
                'alpha_to_value' => $maxPrefix,
                'count' => count($unmatchedInvoices),
                'sample_invoices' => $sampleInvoices,
                'years_found' => [],
                'prefixes_found' => array_fill_keys($prefixes, true)
            ]);
        }
    }

    private function findTemplate(string $templateName): InvoiceTemplatePattern {
        foreach ($this->predefinedTemplates as $t) {
            if ($t->getName() === $templateName) {
                return $t;
            }
        }
        return new InvoiceTemplatePattern('', '', '', [], [], [], false);
    }

    private function groupInvoicesByPattern(array $invoices, InvoiceTemplatePattern $templateObj): array {
        $groupedInvoices = [];
        $regex = $this->compiledRegexes[$templateObj->getName()];

        foreach ($invoices as $inv) {
            if (preg_match($regex, $inv, $matches)) {
                $groupKey = $this->determineGroupKey($matches, $templateObj);
                $groupedInvoices[$groupKey][] = $inv;
            }
        }

        return $groupedInvoices;
    }

    private function processInvoiceGroups(array $groupedInvoices, InvoiceTemplatePattern $templateObj): void {
        foreach ($groupedInvoices as $groupKey => $invList) {
            $confirmedYears = $this->yearAnalyzer->analyzeYears($invList);
            $processed = $this->processInvoices($invList, $confirmedYears);
            $seqNumbers = $this->extractSequenceNumbers($processed, $confirmedYears);
            list($fromVal, $toVal) = $this->bucketAnalyzer->calculateRange($seqNumbers);

            $smartPattern = '';
            if (!empty($processed)) {
                list(, $numbersInfo) = $this->patternAnalyzer->createSmartPattern($processed[0], []);
                $smartPattern = $this->patternAnalyzer->createValueBasedPattern($processed[0], $numbersInfo);
            }

            $bucketKey = "{$templateObj->getName()}|$groupKey";
            $this->bucketAnalyzer->createBucket($bucketKey, [
                'template_name' => $bucketKey,
                'pattern' => $templateObj->getPattern(),
                'smart_pattern' => $smartPattern,
                'from_value' => $fromVal,
                'to_value' => $toVal,
                'count' => count($invList),
                'sample_invoices' => $this->bucketAnalyzer->getSampleInvoices($invList, 5),
                'years_found' => $this->bucketAnalyzer->getYearsFoundMap($confirmedYears),
                'prefixes_found' => $this->extractPrefixes($groupKey)
            ]);
        }
    }

    private function determineGroupKey(array $matches, InvoiceTemplatePattern $templateObj): string {
        $groupKey = '';
        $yearKey = '';

        // Extract year if year_groups is defined
        if (!empty($templateObj->getYearGroups())) {
            foreach ($templateObj->getYearGroups() as $yearIdx) {
                if (isset($matches[$yearIdx])) {
                    $yearStr = $matches[$yearIdx];
                    if (is_numeric($yearStr)) {
                        $year = (int)$yearStr;
                        if (strlen($yearStr) === 2 && $this->yearAnalyzer->isValidYear($year, true)) {
                            $yearKey = $yearStr;
                            break;
                        } else if (strlen($yearStr) === 4 && $this->yearAnalyzer->isValidYear($year, false)) {
                            $yearKey = $yearStr;
                            break;
                        }
                    }
                }
            }
        }

        // Use prefix groups if defined
        if (!empty($templateObj->getPrefixGroups())) {
            foreach ($templateObj->getPrefixGroups() as $groupIdx) {
                if (isset($matches[$groupIdx])) {
                    $groupKey = $matches[$groupIdx];
                    break;
                }
            }
        }

        // If no prefix groups, use sequence groups
        if ($groupKey === '' && !empty($templateObj->getSequenceGroups())) {
            foreach ($templateObj->getSequenceGroups() as $groupIdx) {
                if (isset($matches[$groupIdx])) {
                    $groupKey = $matches[$groupIdx];
                    break;
                }
            }
        }

        if ($groupKey === '') {
            $groupKey = 'NOPFX';
        }

        // Special handling for numeric prefixes
        if (is_numeric($groupKey) && $yearKey !== '') {
            return $yearKey;
        }

        return $groupKey . ($yearKey !== '' ? "|$yearKey" : '');
    }



    private function processInvoices(array $invoices, array $confirmedYears): array {
        $processed = [];
        foreach ($invoices as $invoice) {
            $processed[] = $this->smartSplitConcatenatedNumbers($invoice, $confirmedYears);
        }
        return $processed;
    }

    private function smartSplitConcatenatedNumbers(string $invoice, array $confirmedYears): string {
        $numberRegex = '/\d+/';
        return preg_replace_callback($numberRegex, function($matches) use ($confirmedYears) {
            $match = $matches[0];
            $num = (int)$match;
            
            if (strlen($match) >= 4 && $num >= 1000) {
                return $this->processLongNumber($match, $confirmedYears);
            }

            return $match;
        }, $invoice);
    }

    private function processLongNumber(string $match, array $confirmedYears): string {
        // Try 2-digit year at start
        if (strlen($match) >= 4) {
            $potentialYear2 = (int)substr($match, 0, 2);
            $remainingDigits = substr($match, 2);
            if (isset($confirmedYears[$potentialYear2]) && strlen($remainingDigits) >= 2) {
                return $potentialYear2 . '-' . $remainingDigits;
            }
        }

        // Try 4-digit year at start
        if (strlen($match) >= 6) {
            $potentialYear4 = (int)substr($match, 0, 4);
            $remainingDigits = substr($match, 4);
            if (isset($confirmedYears[$potentialYear4]) && strlen($remainingDigits) >= 2) {
                return $potentialYear4 . '-' . $remainingDigits;
            }
        }

        // Try 2-digit year at end
        if (strlen($match) >= 4) {
            $potentialYear2End = (int)substr($match, -2);
            $sequenceDigits = substr($match, 0, -2);
            if (isset($confirmedYears[$potentialYear2End]) && strlen($sequenceDigits) >= 2) {
                return $sequenceDigits . '-' . $potentialYear2End;
            }
        }

        // Try 4-digit year at end
        if (strlen($match) >= 6) {
            $potentialYear4End = (int)substr($match, -4);
            $sequenceDigits = substr($match, 0, -4);
            if (isset($confirmedYears[$potentialYear4End]) && strlen($sequenceDigits) >= 2) {
                return $sequenceDigits . '-' . $potentialYear4End;
            }
        }

        return $match;
    }

    private function extractSequenceNumbers(array $processed, array $confirmedYears): array {
        $sequenceNumbers = [];
        foreach ($processed as $invoice) {
            $numbers = $this->extractSequentialNumbers($invoice, $confirmedYears);
            foreach ($numbers as $num) {
                if (!in_array($num, $sequenceNumbers)) {
                    $sequenceNumbers[] = $num;
                }
            }
        }
        return $sequenceNumbers;
    }

    private function extractSequentialNumbers(string $invoice, array $confirmedYears): array {
        $sequenceNumbers = [];
        $numbersInfo = $this->patternAnalyzer->extractNumberPositionsAndValues($invoice);
        
        foreach ($numbersInfo as $numInfo) {
            // Skip if this is a year number
            if ($numInfo->isYearRange || $this->isYearNumber($numInfo->value, $numInfo->length)) {
                continue;
            }
            
            // Get the original number string to preserve leading zeros
            $originalNumber = substr($invoice, $numInfo->start, $numInfo->end - $numInfo->start);
            $sequenceNumbers[] = $originalNumber;
        }
        
        return $sequenceNumbers;
    }

    private function isYearNumber(int $value, int $length): bool {
        if ($length === 4) {
            return $value >= 2000 && $value <= 2030;
        }
        if ($length === 2) {
            return $value >= 20 && $value <= 30;
        }
        return false;
    }

    private function extractPrefixes(string $groupKey): array {
        $prefixes = [];
        if ($groupKey !== 'NOPFX') {
            $prefixes[$groupKey] = true;
        }
        return $prefixes;
    }


    public function GetDocSummary(): array {
        return $this->bucketAnalyzer->getSummary();
    }

    public function identify(string $invoice): string {
        $invoiceClean = strtoupper(trim($invoice));
        foreach ($this->predefinedTemplates as $template) {
            if (isset($this->compiledRegexes[$template->getName()])) {
                if (preg_match($this->compiledRegexes[$template->getName()], $invoiceClean)) {
                    return $template->getName();
                }
            }
        }
        return '';
    }

    public function validateInvoiceFormat(string $invoice): array {
        $templateName = $this->identify($invoice);
        if ($templateName === '') {
            return [false, '', 'No matching template found'];
        }

        $invoiceClean = strtoupper(trim($invoice));
        $prefix = $this->extractPrefix($invoiceClean);

        $bucketKey = "$templateName|$prefix";
        if ($prefix === '') {
            $bucketKey = "$templateName|NOPFX";
        }

        $bucket = $this->bucketAnalyzer->getBucket($bucketKey);
        if (!$bucket) {
            return [true, $templateName, 'Template matched but no range data available'];
        }

        $seqNumbers = $this->extractSequentialNumbers($invoiceClean, $bucket->yearsFound);
        if (empty($seqNumbers)) {
            return [true, $templateName, 'Template matched but no sequence number found'];
        }

        $seqNum = $seqNumbers[0];
        if ($seqNum >= $bucket->fromValue && $seqNum <= $bucket->toValue) {
            return [true, $templateName, "Valid - sequence $seqNum within range [{$bucket->fromValue}-{$bucket->toValue}]"];
        }

        return [false, $templateName, "Invalid - sequence $seqNum outside range [{$bucket->fromValue}-{$bucket->toValue}]"];
    }

    public function extractPrefix(string $invoice): string {
        if (preg_match('/^[A-Z]+/', $invoice, $matches)) {
            return $matches[0];
        }
        return '';
    }

    public function generateBucketKey(string $invoice): string {
        $templateName = $this->identify($invoice);
        if ($templateName === '') {
            return '';
        }

        $invoiceClean = strtoupper(trim($invoice));
        $templateObj = $this->findTemplate($templateName);
        
        if (preg_match($this->compiledRegexes[$templateName], $invoiceClean, $matches)) {
            return $this->determineGroupKey($matches, $templateObj);
        }

        $prefix = $this->extractPrefix($invoiceClean);
        return "$templateName|" . ($prefix ?: 'NOPFX');
    }

    public function analyzeBucket(string $bucketKey): void {
        $this->bucketAnalyzer->analyzeBucket($bucketKey);
    }

    public function printResults(): void {
        if (empty($this->bucketAnalyzer->getAllBuckets())) {
            echo "No analysis results found!\n";
            return;
        }

        echo "\n" . str_repeat("=", 80) . "\n";
        echo "ENHANCED DOCUMENT NUMBER ANALYSIS RESULTS\n";
        echo str_repeat("=", 80) . "\n";

        $summary = $this->GetDocSummary();
        foreach ($summary as $i => $item) {
            echo "\n" . ($i + 1) . ". Template: {$item['template']}\n";
            echo "   Sample: {$item['sample_invoice']}\n";
            echo "   Range: {$item['min_value']} to {$item['max_value']}\n";
            echo "   Count: {$item['count']}\n";
            echo str_repeat("-", 60) . "\n";
        }

        echo "\nTotal templates matched: " . count($summary) . "\n";
        $totalInvoices = array_sum(array_column($summary, 'count'));
        echo "Total invoices processed: $totalInvoices\n";
    }
} 