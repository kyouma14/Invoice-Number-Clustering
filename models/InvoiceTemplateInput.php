<?php

namespace Models;

class InvoiceTemplateInput {
    private string $filePath;
    private string $sheetName;
    private int $columnIndex;
    private int $rowsToProcess;
    private bool $hasHeader;

    public function __construct(
        string $filePath,
        string $sheetName,
        int $columnIndex,
        int $rowsToProcess,
        bool $hasHeader
    ) {
        $this->filePath = $filePath;
        $this->sheetName = $sheetName;
        $this->columnIndex = $columnIndex;
        $this->rowsToProcess = $rowsToProcess;
        $this->hasHeader = $hasHeader;
    }

    public function getFilePath(): string {
        return $this->filePath;
    }

    public function getSheetName(): string {
        return $this->sheetName;
    }

    public function getColumnIndex(): int {
        return $this->columnIndex;
    }

    public function getRowsToProcess(): int {
        return $this->rowsToProcess;
    }

    public function hasHeader(): bool {
        return $this->hasHeader;
    }
} 