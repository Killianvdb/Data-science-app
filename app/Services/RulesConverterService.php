<?php

namespace App\Services;

/**
 * RulesConverterService
 * ======================
 * Converts the user-friendly dataset context form into the rules.json
 * format consumed by cross_reference.py / context_validator.py.
 *
 * Form fields supported:
 *   dataset_description  string        Free-text description of the dataset
 *   no_negative_cols     string[]      Column names that must be >= 0
 *   identifier_cols      string[]      Columns that must never be imputed
 *   range_rules          array[]       [{column, min, max}] valid numeric ranges
 *   required_cols        string[]      Columns that must not be NULL
 *   flag_only            bool          If true, all actions become "flag"
 *
 * Output rules.json schema (matches context_validator.py):
 * {
 *   "context": "...",
 *   "rules": [
 *     {
 *       "rule_id":     "unique_string",
 *       "description": "Human-readable description",
 *       "column":      "column_name",
 *       "condition":   "df['column_name'] < 0",   ← safe pattern only
 *       "fix_action":  "flag|null|abs",
 *       "source":      "user_form",
 *       "justification": "..."
 *     }
 *   ]
 * }
 *
 * Security note:
 *   All generated condition strings use ONLY the safe pattern whitelist
 *   accepted by context_validator._is_safe_condition():
 *     df['col'] <op> <number>
 *   Column names are sanitised before interpolation.
 */
class RulesConverterService
{
    // =========================================================================
    // PUBLIC ENTRY POINT
    // =========================================================================

    /**
     * Convert validated form data into a rules.json array ready for json_encode.
     *
     * @param  array $formData  Validated POST data from the context form
     * @return array            ['context' => string, 'rules' => array[]]
     */
    public function convert(array $formData): array
    {
        $rules   = [];
        $flagOnly = (bool) ($formData['flag_only'] ?? false);

        // ── No-negative columns ───────────────────────────────────────────────
        foreach ($this->parseColumns($formData['no_negative_cols'] ?? []) as $col) {
            $safeCol = $this->sanitiseColumnName($col);
            if ($safeCol === null) continue;

            $action = $this->resolveAction('abs', $flagOnly);
            $rules[] = [
                'rule_id'       => 'user_no_negative_' . $this->slug($safeCol),
                'description'   => "{$safeCol}: negative values are not allowed",
                'column'        => $safeCol,
                'condition'     => "df['{$safeCol}'] < 0",
                'fix_action'    => $action,
                'source'        => 'user_form',
                'justification' => "User specified that '{$safeCol}' cannot contain negative values.",
            ];
        }

        // ── Valid range rules ─────────────────────────────────────────────────
        foreach ($formData['range_rules'] ?? [] as $rangeRule) {
            $col = $this->sanitiseColumnName($rangeRule['column'] ?? '');
            if ($col === null) continue;

            $min = isset($rangeRule['min']) && is_numeric($rangeRule['min'])
                ? (float) $rangeRule['min']
                : null;
            $max = isset($rangeRule['max']) && is_numeric($rangeRule['max'])
                ? (float) $rangeRule['max']
                : null;

            if ($min !== null) {
                $action  = $this->resolveAction('flag', $flagOnly);
                $rules[] = [
                    'rule_id'       => 'user_range_min_' . $this->slug($col),
                    'description'   => "{$col}: value below minimum ({$min})",
                    'column'        => $col,
                    'condition'     => "df['{$col}'] < {$min}",
                    'fix_action'    => $action,
                    'source'        => 'user_form',
                    'justification' => "User specified minimum value of {$min} for '{$col}'.",
                ];
            }

            if ($max !== null) {
                $action  = $this->resolveAction('flag', $flagOnly);
                $rules[] = [
                    'rule_id'       => 'user_range_max_' . $this->slug($col),
                    'description'   => "{$col}: value above maximum ({$max})",
                    'column'        => $col,
                    'condition'     => "df['{$col}'] > {$max}",
                    'fix_action'    => $action,
                    'source'        => 'user_form',
                    'justification' => "User specified maximum value of {$max} for '{$col}'.",
                ];
            }
        }

        // ── Required (non-null) columns ───────────────────────────────────────
        // These are handled as metadata only — the pipeline uses the
        // identifier_cols list to skip imputation, not as eval() conditions.
        // We store them in the context string, not as rules.

        return [
            'context'         => trim($formData['dataset_description'] ?? ''),
            'identifier_cols' => $this->parseColumns($formData['identifier_cols'] ?? []),
            'required_cols'   => $this->parseColumns($formData['required_cols']   ?? []),
            'rules'           => $rules,
        ];
    }

    /**
     * Write the converted rules to a JSON file and return its path.
     *
     * @param  array  $formData   Validated form POST data
     * @param  string $outputDir  Directory to write the file into
     * @param  string $prefix     Filename prefix (e.g. uniquePrefix())
     * @return string             Absolute path to the written rules.json
     */
    public function writeRulesFile(array $formData, string $outputDir, string $prefix): string
    {
        $payload  = $this->convert($formData);
        $filename = $prefix . '_rules.json';
        $path     = rtrim($outputDir, '/') . '/' . $filename;

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        @chmod($path, 0666);

        return $path;
    }

    // =========================================================================
    // VALIDATION HELPER
    // =========================================================================

    /**
     * Return Laravel validation rules for the context form fields.
     * Use in your controller: $request->validate(RulesConverterService::validationRules())
     */
    public static function validationRules(): array
    {
        return [
            'dataset_description'      => 'nullable|string|max:1000',
            'no_negative_cols'         => 'nullable|array',
            'no_negative_cols.*'       => 'nullable|string|max:100',
            'identifier_cols'          => 'nullable|array',
            'identifier_cols.*'        => 'nullable|string|max:100',
            'required_cols'            => 'nullable|array',
            'required_cols.*'          => 'nullable|string|max:100',
            'range_rules'              => 'nullable|array|max:20',
            'range_rules.*.column'     => 'nullable|string|max:100',
            'range_rules.*.min'        => 'nullable|numeric',
            'range_rules.*.max'        => 'nullable|numeric',
            'flag_only'                => 'nullable|boolean',
        ];
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Normalise the no_negative_cols / identifier_cols inputs.
     * Accepts either an array of strings or a comma-separated string.
     */
    private function parseColumns(mixed $input): array
    {
        if (is_string($input)) {
            $input = array_map('trim', explode(',', $input));
        }
        if (!is_array($input)) {
            return [];
        }
        return array_values(array_filter(
            array_map('trim', $input),
            fn ($v) => $v !== ''
        ));
    }

    /**
     * Sanitise a column name so it is safe to embed in a condition string.
     *
     * Allowed: letters, digits, underscores, spaces, hyphens.
     * Returns null if the name is empty or contains dangerous characters
     * (quotes, brackets, dollar signs, etc.) that could break out of the
     * df['col'] pattern.
     */
    private function sanitiseColumnName(string $col): ?string
    {
        $col = trim($col);
        if ($col === '') {
            return null;
        }
        // Only allow characters that are safe inside df['...']
        if (!preg_match('/^[\w\s\-]+$/u', $col)) {
            return null;
        }
        // Max length guard
        if (strlen($col) > 100) {
            return null;
        }
        return $col;
    }

    /**
     * Resolve the fix_action, forcing "flag" when flag_only mode is on.
     */
    private function resolveAction(string $preferred, bool $flagOnly): string
    {
        return $flagOnly ? 'flag' : $preferred;
    }

    /**
     * Convert a column name to a slug safe for use in rule_id strings.
     */
    private function slug(string $col): string
    {
        return preg_replace('/[^a-z0-9]+/', '_', strtolower($col));
    }
}