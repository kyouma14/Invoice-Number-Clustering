<?php

namespace Analyzer;

use Models\BucketInfo;

class BucketAnalyzer {
    private array $buckets = [];

    public function createBucket(string $bucketKey, array $data): void {
        $this->buckets[$bucketKey] = new BucketInfo($data);
    }

    public function getBucket(string $bucketKey): ?BucketInfo {
        return $this->buckets[$bucketKey] ?? null;
    }

    public function getAllBuckets(): array {
        return $this->buckets;
    }

    public function getSampleInvoices(array $invoices, int $maxSamples): array {
        if (count($invoices) <= $maxSamples) {
            return $invoices;
        }
        return array_slice($invoices, 0, $maxSamples);
    }

    public function getYearsFoundMap(array $years): array {
        $yearsFoundMap = [];
        foreach ($years as $year => $_) {
            $yearsFoundMap[$year] = true;
        }
        return $yearsFoundMap;
    }

    public function calculateRange(array $sequenceNumbers): array {
        if (empty($sequenceNumbers)) {
            return [0, 0];
        }

        // Convert sequence numbers to integers for comparison while preserving original strings
        $numericValues = array_map(function($seq) {
            return (int)$seq;
        }, $sequenceNumbers);

        $minValue = min($numericValues);
        $maxValue = max($numericValues);

        // Find the original string representations
        $minSeq = $sequenceNumbers[array_search($minValue, $numericValues)];
        $maxSeq = $sequenceNumbers[array_search($maxValue, $numericValues)];

        return [$minSeq, $maxSeq];
    }

    public function analyzeBucket(string $bucketKey): void {
        if (!isset($this->buckets[$bucketKey])) {
            echo "Bucket not found: $bucketKey\n";
            return;
        }

        $bucket = $this->buckets[$bucketKey];
        echo "\n=== Detailed Analysis for Bucket: $bucketKey ===\n";
        echo "Template Name: " . $bucket->templateName . "\n";
        echo "Pattern: " . $bucket->pattern . "\n";
        echo "Smart Pattern: " . $bucket->smartPattern . "\n";
        echo "Count: " . $bucket->count . "\n";
        echo "Range: " . $bucket->fromValue . " to " . $bucket->toValue . "\n";
        
        $this->printYearsFound($bucket);
        $this->printPrefixesFound($bucket);
        $this->printSampleInvoices($bucket);
        $this->printSequenceAnalysis($bucket);
    }

    private function printYearsFound(BucketInfo $bucket): void {
        echo "\nYears Found:\n";
        if (!empty($bucket->yearsFound)) {
            foreach (array_keys($bucket->yearsFound) as $year) {
                echo "- $year\n";
            }
        } else {
            echo "- None\n";
        }
    }

    private function printPrefixesFound(BucketInfo $bucket): void {
        echo "\nPrefixes Found:\n";
        if (!empty($bucket->prefixesFound)) {
            foreach (array_keys($bucket->prefixesFound) as $prefix) {
                echo "- $prefix\n";
            }
        } else {
            echo "- None\n";
        }
    }

    private function printSampleInvoices(BucketInfo $bucket): void {
        echo "\nSample Invoices:\n";
        if (!empty($bucket->sampleInvoices)) {
            foreach ($bucket->sampleInvoices as $invoice) {
                echo "- $invoice\n";
            }
        } else {
            echo "- None\n";
        }
    }

    private function printSequenceAnalysis(BucketInfo $bucket): void {
        echo "\nSequence Numbers Analysis:\n";
        echo "Min: " . $bucket->fromValue . "\n";
        echo "Max: " . $bucket->toValue . "\n";
        echo "Count: " . $bucket->count . "\n";
        
        if ($bucket->toValue - $bucket->fromValue + 1 > $bucket->count) {
            echo "\nNote: There are gaps in the sequence\n";
            echo "Expected count: " . ($bucket->toValue - $bucket->fromValue + 1) . "\n";
            echo "Actual count: " . $bucket->count . "\n";
            echo "Missing numbers: " . (($bucket->toValue - $bucket->fromValue + 1) - $bucket->count) . "\n";
        }
    }

    public function getSummary(): array {
        if (empty($this->buckets)) {
            return [];
        }

        $summary = [];
        foreach ($this->buckets as $key => $bucket) {
            $templateName = explode('|', $key)[0];
            $summary[] = [
                'template' => $templateName,
                'sample_invoice' => !empty($bucket->sampleInvoices) ? $bucket->sampleInvoices[0] : '',
                'min_value' => $templateName === 'unmatched' ? $bucket->alphaFromValue : $bucket->fromValue,
                'max_value' => $templateName === 'unmatched' ? $bucket->alphaToValue : $bucket->toValue,
                'count' => $bucket->count
            ];
        }

        // Sort by count descending
        usort($summary, function($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        return $summary;
    }
} 