<?php

namespace Excel;

use Models\InvoiceTemplateInput;
use Models\BucketInfo;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelHandler {
    public static function readExcelData(InvoiceTemplateInput $input): array {
        try {
            $spreadsheet = IOFactory::load($input->getFilePath());
            $worksheet = $spreadsheet->getSheetByName($input->getSheetName());
            
            if (!$worksheet) {
                throw new \Exception("Sheet not found: {$input->getSheetName()}");
            }

            $data = [];
            $startRow = $input->hasHeader() ? 2 : 1;
            $colIndex = $input->getColumnIndex();
            $rowsToProcess = $input->getRowsToProcess();

            $highestRow = $worksheet->getHighestRow();
            $endRow = $rowsToProcess > 0 ? min($startRow + $rowsToProcess - 1, $highestRow) : $highestRow;

            for ($row = $startRow; $row <= $endRow; $row++) {
                $invoiceNum = trim($worksheet->getCellByColumnAndRow($colIndex, $row)->getValue());
                if ($invoiceNum !== '' && $invoiceNum !== 'NAN') {
                    $data[] = [$invoiceNum];
                }
            }

            return $data;
        } catch (\Exception $e) {
            throw new \Exception("Error reading Excel file: " . $e->getMessage());
        }
    }

    public static function saveResults(string $filename, array $buckets): string {
        if (empty($buckets)) {
            echo "No results to save!\n";
            return "";
        }

        $spreadsheet = new Spreadsheet();
        
        // Analysis Summary sheet
        $summaryData = self::getAnalysisSummary($buckets);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Analysis_Summary');

        // Write summary data
        foreach ($summaryData as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + 1, $value);
            }
        }

        // Format headers
        if (!empty($summaryData)) {
            $headerRange = 'A1:' . $sheet->getHighestColumn() . '1';
            $sheet->getStyle($headerRange)->getFont()->setBold(true);
            $sheet->getStyle($headerRange)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E0E0E0');
        }

        // Auto-fit columns
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Create detailed sheets for each template
        $detailSheetCount = 0;
        foreach ($buckets as $bucketKey => $bucket) {
            if ($detailSheetCount >= 10) { // Limit to prevent too many sheets
                break;
            }

            // Create safe sheet name
            $safeSheetName = str_replace(['|', '/'], '_', $bucketKey);
            $safeSheetName = substr($safeSheetName, 0, 31);

            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($safeSheetName);

            // Headers for detail sheet
            $detailHeaders = ['Invoice_Number', 'Template', 'Pattern', 'Year_Found', 'Prefix', 'Sequence_Number'];
            foreach ($detailHeaders as $colIndex => $header) {
                $sheet->setCellValueByColumnAndRow($colIndex + 1, 1, $header);
            }

            // Apply header style
            $headerRange = 'A1:' . $sheet->getHighestColumn() . '1';
            $sheet->getStyle($headerRange)->getFont()->setBold(true);
            $sheet->getStyle($headerRange)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E0E0E0');

            // Add sample invoices data
            foreach ($bucket->sampleInvoices as $rowIndex => $invoice) {
                $rowNum = $rowIndex + 2;
                $sheet->setCellValue('A' . $rowNum, $invoice);
                $sheet->setCellValue('B' . $rowNum, explode('|', $bucketKey)[0]);
                $sheet->setCellValue('C' . $rowNum, $bucket->pattern);

                // Extract years and prefixes
                $yearsStr = implode(', ', array_keys($bucket->yearsFound));
                $prefixesStr = implode(', ', array_keys($bucket->prefixesFound));

                $sheet->setCellValue('D' . $rowNum, $yearsStr);
                $sheet->setCellValue('E' . $rowNum, $prefixesStr);
            }

            // Auto-fit columns
            foreach (range('A', $sheet->getHighestColumn()) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $detailSheetCount++;
        }

        // Save file
        $outputPath = $filename;
        if (!str_ends_with($outputPath, '.xlsx')) {
            $outputPath .= '.xlsx';
        }

        try {
            $writer = new Xlsx($spreadsheet);
            $writer->save($outputPath);
            echo "Results saved to: $outputPath\n";
            return $outputPath;
        } catch (\Exception $e) {
            echo "Error saving Excel file: " . $e->getMessage() . "\n";
            return "";
        }
    }

    private static function getAnalysisSummary(array $buckets): array {
        if (empty($buckets)) {
            return [];
        }

        $summaryData = [];
        // Header
        $summaryData[] = [
            'Template_Name', 'Pattern', 'Count', 'From_Value', 'To_Value',
            'Range_Size', 'Years_Found', 'Prefixes_Found', 'Sample_Invoices'
        ];

        // Convert buckets to array for sorting
        $bucketsList = [];
        foreach ($buckets as $key => $bucket) {
            $bucketsList[] = ['key' => $key, 'bucket' => $bucket];
        }

        // Sort by count descending
        usort($bucketsList, function($a, $b) {
            return $b['bucket']->count <=> $a['bucket']->count;
        });

        foreach ($bucketsList as $b) {
            $bucket = $b['bucket'];
            $rangeSize = max(0, $bucket->toValue - $bucket->fromValue + 1);

            // Convert years found to string
            $years = !empty($bucket->yearsFound) ? array_keys($bucket->yearsFound) : [];
            sort($years);
            $yearsStr = implode(', ', $years);

            // Convert prefixes found to string
            $prefixes = !empty($bucket->prefixesFound) ? array_keys($bucket->prefixesFound) : [];
            sort($prefixes);
            $prefixesStr = implode(', ', $prefixes);

            // Sample invoices
            $sampleStr = !empty($bucket->sampleInvoices) ? implode(', ', $bucket->sampleInvoices) : '';

            $summaryData[] = [
                explode('|', $b['key'])[0],
                $bucket->pattern,
                (string)$bucket->count,
                (string)$bucket->fromValue,
                (string)$bucket->toValue,
                (string)$rangeSize,
                $yearsStr,
                $prefixesStr,
                $sampleStr
            ];
        }

        return $summaryData;
    }
} 