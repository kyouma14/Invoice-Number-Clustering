<?php

namespace Models;

class NumberInfo {
    public $value;
    public int $start;
    public int $end;
    public int $length;
    public bool $isYearRange;

    public function __construct($value, int $start, int $end, int $length, bool $isYearRange = false) {
        $this->value = $value;
        $this->start = $start;
        $this->end = $end;
        $this->length = $length;
        $this->isYearRange = $isYearRange;
    }
} 