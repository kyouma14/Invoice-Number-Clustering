<?php

namespace Analyzer;

class YearAnalyzer {
    private array $confirmedYears = [];
    private array $likelyYears = [];

    public function analyzeYears(array $invoices): array {
        $this->confirmedYears = [];
        $this->likelyYears = [];
        
        $yearCandidates = $this->collectYearCandidates($invoices);
        $this->processYearCandidates($yearCandidates, count($invoices));
        
        return $this->confirmedYears;
    }

    private function collectYearCandidates(array $invoices): array {
        $yearCandidates = [];
        $finYearRegex = '/(\d{2})-(\d{2})/';
        $numberRegex = '/\d+/';

        foreach ($invoices as $invoice) {
            // Check for financial year pattern
            if (preg_match($finYearRegex, $invoice, $finYearMatches)) {
                $this->processFinancialYear($finYearMatches, $yearCandidates);
                continue;
            }

            // Check for other year patterns
            preg_match_all($numberRegex, $invoice, $matches);
            foreach ($matches[0] as $numberStr) {
                $this->processNumberString($numberStr, $yearCandidates);
            }
        }

        return $yearCandidates;
    }

    private function processFinancialYear(array $matches, array &$yearCandidates): void {
        $year1 = (int)$matches[1];
        $year2 = (int)$matches[2];
        if ($year1 >= 20 && $year1 <= 30 && $year2 >= 20 && $year2 <= 30) {
            $fullYear1 = 2000 + $year1;
            $fullYear2 = 2000 + $year2;
            $yearCandidates[$fullYear1] = ($yearCandidates[$fullYear1] ?? 0) + 1;
            $yearCandidates[$fullYear2] = ($yearCandidates[$fullYear2] ?? 0) + 1;
        }
    }

    private function processNumberString(string $numberStr, array &$yearCandidates): void {
        if (strlen($numberStr) === 4) {
            $potentialYear4 = (int)$numberStr;
            if ($potentialYear4 >= 2000 && $potentialYear4 <= 2030) {
                $yearCandidates[$potentialYear4] = ($yearCandidates[$potentialYear4] ?? 0) + 1;
                return;
            }
        }

        $num = (int)$numberStr;
        if (strlen($numberStr) >= 5 && $num >= 10000) {
            $this->processLongNumber($numberStr, $yearCandidates);
        }
    }

    private function processLongNumber(string $numberStr, array &$yearCandidates): void {
        if (strlen($numberStr) >= 6) {
            // Check for 4-digit year at start
            $potentialYear4 = (int)substr($numberStr, 0, 4);
            if ($potentialYear4 >= 2000 && $potentialYear4 <= 2030) {
                $yearCandidates[$potentialYear4] = ($yearCandidates[$potentialYear4] ?? 0) + 1;
            }

            // Check for 4-digit year at end
            $potentialYear4End = (int)substr($numberStr, -4);
            if ($potentialYear4End >= 2000 && $potentialYear4End <= 2030) {
                $yearCandidates[$potentialYear4End] = ($yearCandidates[$potentialYear4End] ?? 0) + 1;
            }
        }

        if (strlen($numberStr) >= 4) {
            // Check for 2-digit year at start
            $potentialYear2 = (int)substr($numberStr, 0, 2);
            if ($potentialYear2 >= 20 && $potentialYear2 <= 30) {
                $yearCandidates[$potentialYear2] = ($yearCandidates[$potentialYear2] ?? 0) + 1;
            }

            // Check for 2-digit year at end
            $potentialYear2End = (int)substr($numberStr, -2);
            if ($potentialYear2End >= 20 && $potentialYear2End <= 30) {
                $yearCandidates[$potentialYear2End] = ($yearCandidates[$potentialYear2End] ?? 0) + 1;
            }
        }
    }

    private function processYearCandidates(array $yearCandidates, int $totalInvoices): void {
        // First pass: Add 4-digit years that are frequent
        foreach ($yearCandidates as $yearCandidate => $count) {
            $frequencyRatio = $totalInvoices > 0 ? $count / $totalInvoices : 0;
            if ($frequencyRatio > 0.4 && $count >= 10 && $yearCandidate >= 2000 && $yearCandidate <= 2030) {
                $this->confirmedYears[$yearCandidate] = true;
            }
        }

        // Second pass: Only add 2-digit years if no conflicting 4-digit years exist
        foreach ($yearCandidates as $yearCandidate => $count) {
            $frequencyRatio = $totalInvoices > 0 ? $count / $totalInvoices : 0;
            if ($frequencyRatio > 0.4 && $count >= 10 && $yearCandidate >= 20 && $yearCandidate <= 30) {
                if (!$this->hasConflictingYear($yearCandidate)) {
                    $this->confirmedYears[$yearCandidate] = true;
                }
            }
        }
    }

    private function hasConflictingYear(int $yearCandidate): bool {
        foreach ($this->confirmedYears as $fourDigit => $_) {
            if ($fourDigit >= 2000) {
                $fourDigitStr = (string)$fourDigit;
                if (strlen($fourDigitStr) >= 4 && substr($fourDigitStr, 2, 2) === sprintf("%02d", $yearCandidate)) {
                    return true;
                }
            }
        }
        return false;
    }

    public function isValidYear(int $num, bool $isTwoDigit): bool {
        if ($isTwoDigit) {
            return $num >= 20 && $num <= 30;
        }
        return $num >= 2000 && $num <= 2030;
    }
} 