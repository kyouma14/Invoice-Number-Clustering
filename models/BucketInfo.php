<?php

namespace Models;

class BucketInfo {
    public string $templateName;
    public string $pattern;
    public string $smartPattern;
    public int $fromValue;
    public int $toValue;
    public string $alphaFromValue;  // For alphabetical min value
    public string $alphaToValue;    // For alphabetical max value
    public int $count;
    public array $sampleInvoices;
    public array $yearsFound;
    public array $prefixesFound;

    public function __construct(array $data = []) {
        $this->templateName = $data['template_name'] ?? '';
        $this->pattern = $data['pattern'] ?? '';
        $this->smartPattern = $data['smart_pattern'] ?? '';
        $this->fromValue = $data['from_value'] ?? 0;
        $this->toValue = $data['to_value'] ?? 0;
        $this->alphaFromValue = $data['alpha_from_value'] ?? '';  // Initialize new property
        $this->alphaToValue = $data['alpha_to_value'] ?? '';      // Initialize new property
        $this->count = $data['count'] ?? 0;
        $this->sampleInvoices = $data['sample_invoices'] ?? [];
        $this->yearsFound = $data['years_found'] ?? [];
        $this->prefixesFound = $data['prefixes_found'] ?? [];
    }
} 