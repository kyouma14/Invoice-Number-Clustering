<?php

namespace Models;

class InvoiceTemplateManager {
    private string $description;
    private string $version;
    private InvoiceTemplateInput $input;
    private array $templates;

    public function __construct(
        string $description,
        string $version,
        InvoiceTemplateInput $input,
        array $templates
    ) {
        $this->description = $description;
        $this->version = $version;
        $this->input = $input;
        $this->templates = $templates;
    }

    public function getDescription(): string {
        return $this->description;
    }

    public function getVersion(): string {
        return $this->version;
    }

    public function getInput(): InvoiceTemplateInput {
        return $this->input;
    }

    public function getTemplates(): array {
        return $this->templates;
    }

    public function getEnabledTemplates(): array {
        return array_filter($this->templates, fn($template) => $template->isEnabled());
    }

    public static function createDefault(): self {          # input excel file parameters here
        $input = new InvoiceTemplateInput(
            "invoice_numbers_dash_separator.xlsx",
            "Sheet1",
            1,
            2100,
            true
        );

        $templates = [
            new InvoiceTemplatePattern(
                "prefix_year_seq_dash",
                "^([A-Za-z]+)-(\\d{4})-(\\d+)$",
                "PREFIX-YYYY-NNNN format with dashes (e.g., ABC-2024-048)",
                [2],
                [3],
                [1]
            ),
            new InvoiceTemplatePattern(
                "bill-2digit-year-seq",
                "^BILL-(\\d{2})-(\\d+)$",
                "BILL-YY-NNNN format",
                [1],
                [2],
                []
            ),
            new InvoiceTemplatePattern(
                "prefix-year-concatenated",
                "^([A-Z]+)(\\d{2}|\\d{4})-(\\d+)$",
                "PREFIX-YY/YYYY-NNNN concatenated format (e.g., ABC24-001 or ABC2024-001)",
                [2],
                [3],
                [1]
            ),
            new InvoiceTemplatePattern(
                "doc-prefix-year-seq",
                "^DOC-([A-Z]+)-(\\d{4})-(\\d+)$",
                "DOC-PREFIX-YYYY-NNNN format",
                [2],
                [3],
                [1]
            ),
            new InvoiceTemplatePattern(
                "prefix-slash-year-seq",
                "^([A-Z]+)/(\\d{4})/(\\d+)$",
                "PREFIX/YYYY/NNNN format",
                [2],
                [3],
                [1]
            ),
            new InvoiceTemplatePattern(
                "receipt-year-seq",
                "^RCP(\\d{4})(\\d+)$",
                "RCPYYYYNNNN format",
                [1],
                [2],
                []
            ),
            new InvoiceTemplatePattern(
                "prefix-dash-seq",
                "^([A-Z]+)-(\\d+)$",
                "PREFIX-NNNN format",
                [],
                [2],
                [1]
            ),
            new InvoiceTemplatePattern(
                "prefix-dash-seq-lower",
                "^([a-z]+)-(\\d+)$",
                "prefix-NNNN format (lowercase prefix)",
                [],
                [2],
                [1]
            ),
            new InvoiceTemplatePattern(
                "prefix-slash-seq",
                "^([A-Z]+)/(\\d+)$",
                "PREFIX/NNNN format",
                [],
                [2],
                [1]
            ),
            new InvoiceTemplatePattern(
                "finyear-slash",
                "^(\\d+)[/](\\d{2}-\\d{2})$",
                "NNNN/YY-YY format",
                [2],
                [1],
                []
            ),
            new InvoiceTemplatePattern(
                "finyear-dash",
                "^(\\d+)-(\\d{2}-\\d{2})$",
                "NNNN-YY-YY format",
                [2],
                [1],
                []
            ),
            new InvoiceTemplatePattern(
                "prefix-seq-finyear",
                "^([A-Z]+)/(\\d+)/(\\d{4}-\\d{2})$",
                "PREFIX/SEQ/YYYY-YY format",
                [3],
                [2],
                [1]
            ),
            new InvoiceTemplatePattern(
                "prefix-alphanumseq",
                "^([A-Z]+)-(\\d+[A-Z])$",
                "PREFIX-NNNNA format",
                [],
                [2],
                [1]
            ),
            new InvoiceTemplatePattern(
                "numseq-suffix",
                "^(\\d+)-([a-z]{2})$",
                "Numeric sequence with 2-letter lowercase suffix for quarterly patterns (e.g., 001-aa, 500-bb)",
                [],
                [1],
                [2]
            ),
            new InvoiceTemplatePattern(
                "numseq-suffix-upper",
                "^(\\d+)-([A-Z]{2})$",
                "Numeric sequence with 2-letter uppercase suffix for quarterly patterns (e.g., 001-AA, 500-BB)",
                [],
                [1],
                [2]
            ),
            new InvoiceTemplatePattern(
                "numseq-alphacode-year",
                "^(\\d+)-([A-Za-z]+)-(\\d{4})$",
                "Numeric sequence, mixed-case alphabetic code, and 4-digit year (e.g., 001-AbC-2024)",
                [3],
                [1],
                [2]
            ),
            new InvoiceTemplatePattern(
                "prefix-year-seq",
                "^([A-Za-z]+)-(\\d{2})-(\\d+)$",
                "PREFIX-YY-NNNN format (e.g., ABC-24-001)",
                [2],
                [3],
                [1]
            ),
            new InvoiceTemplatePattern(
                "prefix_seq_year_slash",
                "^([A-Za-z]+)/(\\d+)/(\\d{4})$",
                "PREFIX/SEQ/YYYY format (e.g., ABC/001/2024)",
                [3],
                [2],
                [1]
            ),
            new InvoiceTemplatePattern(
                "prefix_2digityear_seq_slash",
                "^([A-Za-z]+)/(\\d{2})/(\\d+)$",
                "PREFIX/YY/NNN format (e.g., ABC/24/001)",
                [2],
                [3],
                [1]
            ),
            new InvoiceTemplatePattern(
                "seq_prefix_year_slash",
                "^(\\d+)/([A-Za-z]+)/(\\d{4})$",
                "SEQ/PREFIX/YYYY format (e.g., 001/ABC/2024)",
                [3],
                [1],
                [2]
            ),
            new InvoiceTemplatePattern(
                "seq_prefix_year_concatenated",
                "^(\\d+)([A-Za-z]+)(\\d{4})$",
                "SEQ + PREFIX + YYYY with no separators (e.g., 001ABC2024)",
                [3],
                [1],
                [2]
            ),
            new InvoiceTemplatePattern(
                "prefix_seq_year_dash",
                "^([A-Za-z]+)-(\\d+)-(\\d{4})$",
                "PREFIX-SEQ-YYYY format (e.g., ABC-001-2024)",
                [3],
                [2],
                [1]
            ),
            new InvoiceTemplatePattern(
                "prefix_seq_year_concatenated",
                "^([A-Za-z]+)(\\d+)(\\d{4})$",
                "PREFIX + SEQ + YYYY with no separators (e.g., ABC0012024)",
                [3],
                [2],
                [1]
            ),
            new InvoiceTemplatePattern(
                "varprefix_2digityear_region_seq",
                "^([A-Za-z]+)(\\d{2})([A-Z]{3})(\\d+)$",
                "Variable prefix + 2-digit year + 3-letter region + numeric sequence (e.g., Af25KAR150780751)",
                [2],
                [4],
                [1, 3]
            ),
            new InvoiceTemplatePattern(
                "varprefix_2digityear_region_seq_slash",
                "^([A-Za-z]+)/(\\d{2})/([A-Z]{3})/(\\d+)$",
                "Variable prefix / 2-digit year / 3-letter region / numeric sequence (e.g., AF/25/KAR/150780751)",
                [2],
                [4],
                [1, 3]
            ),
            new InvoiceTemplatePattern(
                "varprefix_2digityear_region_seq_dash",
                "^([A-Za-z]+)-(\\d{2})-([A-Z]{3})-(\\d+)$",
                "Variable prefix - 2-digit year - 3-letter region - numeric sequence (e.g., AF-25-KAR-150780752)",
                [2],
                [4],
                [1, 3]
            )
        ];

        return new self(
            "Invoice number regex templates configuration",
            "1.0",
            $input,
            $templates
        );
    }
} 