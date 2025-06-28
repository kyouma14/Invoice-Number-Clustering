<?php

namespace Models;

class TemplateConfig {
    public string $name;
    public string $pattern;
    public string $description;
    public array $yearGroups;
    public array $sequenceGroups;
    public array $prefixGroups;
    public bool $enabled;

    public function __construct(array $data = []) {
        $this->name = $data['name'] ?? '';
        $this->pattern = $data['pattern'] ?? '';
        $this->description = $data['description'] ?? '';
        $this->yearGroups = $data['year_groups'] ?? [];
        $this->sequenceGroups = $data['sequence_groups'] ?? [];
        $this->prefixGroups = $data['prefix_groups'] ?? [];
        $this->enabled = $data['enabled'] ?? false;
    }
} 