<?php

// Increase memory limit to 512MB
ini_set('memory_limit', '2048M');

require_once __DIR__ . '/vendor/autoload.php';

use Analyzer\EnhancedDocNoAnalyzer;
use Excel\ExcelHandler;
use Models\InvoiceTemplateManager;
use Models\InvoiceTemplateInput;
use Models\InvoiceTemplatePattern;

// Parse command line arguments
$options = getopt("", [
    "file:",           // Excel file path
    "sheet:",          // Sheet name
    "column:",         // Column index (1-based)
    "rows:",           // Number of rows to process
    "header:",         // Has header (true/false)
    "output:"          // Output file name
]);

// Set default values
$filePath = $options['file'] ?? "invoice_numbers_reversed_format.xlsx";
$sheetName = $options['sheet'] ?? "Sheet1";
$columnIndex = isset($options['column']) ? (int)$options['column'] : 1;
$rowsToProcess = isset($options['rows']) ? (int)$options['rows'] : 2100;
$hasHeader = isset($options['header']) ? filter_var($options['header'], FILTER_VALIDATE_BOOLEAN) : true;
$outputFile = $options['output'] ?? "invoice_clusters_analysis";

// Create custom input configuration
$input = new InvoiceTemplateInput(
    $filePath,
    $sheetName,
    $columnIndex,
    $rowsToProcess,
    $hasHeader
);

// Initialize analyzer with default configuration
try {
    $analyzer = new EnhancedDocNoAnalyzer();
} catch (Exception $e) {
    echo "Error creating analyzer: " . $e->getMessage() . "\n";
    exit(1);
}

// Create template manager with custom input
$templateManager = new InvoiceTemplateManager(
    "Invoice number regex templates configuration",
    "1.0",
    $input,
    InvoiceTemplateManager::createDefault()->getTemplates()
);

// Read data from Excel
try {
    $excelData = ExcelHandler::readExcelData($templateManager->getInput());
} catch (Exception $e) {
    echo "Error reading Excel data: " . $e->getMessage() . "\n";
    exit(1);
}

// Process the data
$buckets = $analyzer->ProcessDocNumber($excelData);

// Get the summary
$summary = $analyzer->GetDocSummary();


echo "\n=== Raw Array Output ===\n";
print_r($summary); 


// Print summary in formatted way
echo "\n=== Document Number Analysis Summary ===\n";
if (!empty($summary)) {
    foreach ($summary as $item) {
        echo "\nTemplate: {$item['template']}\n";
        echo "Sample: {$item['sample_invoice']}\n";
        echo "Range: {$item['min_value']} to {$item['max_value']}\n";
        echo "Count: {$item['count']}\n";
        echo str_repeat("-", 60) . "\n";
    }
} else {
    echo "No analysis results found!\n";
}

// // Save results to Excel
// $outputFile = ExcelHandler::saveResults($outputFile, $buckets);
// if ($outputFile !== "") {
//     echo "\nDetailed analysis saved to: $outputFile\n";
// }

// Print all available bucket keys
// echo "\nAvailable bucket keys:\n";
// foreach (array_keys($buckets) as $key) {
//     echo "- $key\n";
// }

// // Get bucket for specific template
// $invoiceToAnalyze = "STVMC-162808"; // Replace with the invoice number for information on its series
// $bucketKey = $analyzer->generateBucketKey($invoiceToAnalyze);

// if ($bucketKey !== '') {
//     echo "\nAnalyzing bucket for invoice: $invoiceToAnalyze\n";
//     echo "Generated bucket key: $bucketKey\n";
//     $analyzer->analyzeBucket($bucketKey);
// } else {
//     echo "\nNo matching template found for invoice: $invoiceToAnalyze\n";
// }
 