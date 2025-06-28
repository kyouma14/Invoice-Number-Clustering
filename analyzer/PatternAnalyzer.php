<?php

namespace Analyzer;

use Models\NumberInfo;

class PatternAnalyzer {
    private array $compiledRegexes = [];

    public function compilePattern(string $pattern): string {
        if (!preg_match('/^\/.+\/[a-z]*$/', $pattern)) {
            $pattern = '/' . str_replace('/', '\/', $pattern) . '/';
        }
        return $pattern;
    }

    public function createSmartPattern(string $invoice, array $numbersInfo): array {
        $pattern = $invoice;
        $offset = 0;

        foreach ($numbersInfo as $i => $numInfo) {
            $placeholder = "NUM$i";
            $startPos = $numInfo->start + $offset;
            $endPos = $numInfo->end + $offset;

            $pattern = substr($pattern, 0, $startPos) . $placeholder . substr($pattern, $endPos);
            $offset += strlen($placeholder) - ($numInfo->end - $numInfo->start);
        }

        return [$pattern, $numbersInfo];
    }

    public function createValueBasedPattern(string $invoice, array $numbersInfo): string {
        if (empty($numbersInfo)) {
            return $invoice;
        }

        $result = '';
        $lastEnd = 0;

        foreach ($numbersInfo as $numInfo) {
            $textBefore = substr($invoice, $lastEnd, $numInfo->start - $lastEnd);
            $result .= $textBefore;

            if ($numInfo->isYearRange) {
                $result .= '[YEAR]';
            } else {
                $value = $numInfo->value;
                if (!is_int($value)) {
                    $result .= '[SEQ]';
                    $lastEnd = $numInfo->end;
                    continue;
                }

                $originalNumberStr = substr($invoice, $numInfo->start, $numInfo->end - $numInfo->start);
                $hasLeadingZeros = strlen($originalNumberStr) > 1 && $originalNumberStr[0] === '0';

                if ($this->isYearValue($value, $originalNumberStr, $invoice, $numInfo)) {
                    $result .= '[YEAR]';
                } else {
                    $result .= '[SEQ]';
                }
            }

            $lastEnd = $numInfo->end;
        }

        $result .= substr($invoice, $lastEnd);
        return $result;
    }

    private function isYearValue(int $value, string $originalNumberStr, string $invoice, NumberInfo $numInfo): bool {
        $hasLeadingZeros = strlen($originalNumberStr) > 1 && $originalNumberStr[0] === '0';
        
        // Check if this is a 4-digit number between separators
        $isBetweenSeparators = false;
        if ($numInfo->start > 0 && $numInfo->end < strlen($invoice)) {
            $prevChar = $invoice[$numInfo->start - 1];
            $nextChar = $invoice[$numInfo->end];
            $isBetweenSeparators = !is_numeric($prevChar) && !is_numeric($nextChar);
        }

        // Check if this is a potential year in the format 2526-3940
        $isPotentialYear = strlen($originalNumberStr) === 4 && $value >= 2526 && $value <= 3940;

        if (!$hasLeadingZeros && ($isBetweenSeparators && $isPotentialYear)) {
            return true;
        }

        return !$hasLeadingZeros && $value >= 2020 && $value <= 2030;
    }

    public function extractNumberPositionsAndValues(string $invoice): array {
        $numbersInfo = [];
        $i = 0;

        // First pass: collect all numbers and their positions
        $numberPositions = [];
        while ($i < strlen($invoice)) {
            if (preg_match('/\d+/', substr($invoice, $i), $matches, PREG_OFFSET_CAPTURE)) {
                $startPos = $i + $matches[0][1];
                $endPos = $startPos + strlen($matches[0][0]);
                $numberStr = $matches[0][0];
                $numberPositions[] = [
                    'value' => (int)$numberStr,
                    'start' => $startPos,
                    'end' => $endPos,
                    'length' => strlen($numberStr),
                    'original' => $numberStr
                ];
                $i = $endPos;
            } else {
                break;
            }
        }

        // Process number positions
        foreach ($numberPositions as $numInfo) {
            $isYear = $this->isYearNumber($numInfo['value'], $numInfo['length']);
            $numbersInfo[] = new NumberInfo(
                $numInfo['value'],
                $numInfo['start'],
                $numInfo['end'],
                $numInfo['length'],
                $isYear
            );
        }

        return $numbersInfo;
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
} 