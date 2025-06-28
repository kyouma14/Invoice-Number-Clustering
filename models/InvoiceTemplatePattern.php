<?php

namespace Models;

class InvoiceTemplatePattern {
    private string $name;
    private string $pattern;
    private string $description;
    private array $yearGroups;
    private array $sequenceGroups;
    private array $prefixGroups;
    private bool $enabled;

    public function __construct(
        string $name,
        string $pattern,
        string $description,
        array $yearGroups,
        array $sequenceGroups,
        array $prefixGroups,
        bool $enabled = true
    ) {
        $this->name = $name;
        $this->pattern = $pattern;
        $this->description = $description;
        $this->yearGroups = $yearGroups;
        $this->sequenceGroups = $sequenceGroups;
        $this->prefixGroups = $prefixGroups;
        $this->enabled = $enabled;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getPattern(): string {
        return $this->pattern;
    }

    public function getDescription(): string {
        return $this->description;
    }

    public function getYearGroups(): array {
        return $this->yearGroups;
    }

    public function getSequenceGroups(): array {
        return $this->sequenceGroups;
    }

    public function getPrefixGroups(): array {
        return $this->prefixGroups;
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }
} 