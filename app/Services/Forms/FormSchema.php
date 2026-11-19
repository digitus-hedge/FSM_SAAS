<?php

namespace App\Services\Forms;

/**
 * The content of a checklist version (spec D5 to D7). Pure functions, no database.
 *
 * schema = ['sections' => [['key', 'title', 'fields' => [field, ...]], ...]]
 * field  = ['key', 'type', 'label', 'required', 'help', 'unit', 'min', 'max', 'options', 'fails_on_no',
 *           'visible_to_client', 'show_if' => ['field', 'op', 'value']|null]
 */
class FormSchema
{
    public const TYPES = ['text', 'number', 'dropdown', 'checkbox', 'yes_no', 'date', 'photo', 'signature'];
    public const OPS   = ['equals', 'not_equals', 'is_yes', 'is_no', 'gt', 'lt'];
    public const MAX_SECTIONS = 10;

    /** @return string[] one message for each problem, empty when the schema may be published (TEMPLATE_INVALID). */
    public static function problems(array $schema): array
    {
        $problems = [];
        $sections = $schema['sections'] ?? [];
        if (! is_array($sections) || count($sections) < 1) {
            return ['Add at least one field.'];
        }
        if (count($sections) > self::MAX_SECTIONS) {
            $problems[] = 'Use at most ' . self::MAX_SECTIONS . ' sections.';
        }

        $seen  = [];   // keys of earlier fields
        $count = 0;
        foreach ($sections as $section) {
            foreach (($section['fields'] ?? []) as $f) {
                $count++;
                $key   = (string) ($f['key'] ?? '');
                $label = trim((string) ($f['label'] ?? ''));
                $name  = $label !== '' ? $label : ($key ?: "field {$count}");

                if ($key === '' || isset($seen[$key])) {
                    $problems[] = "Fix {$name}: the key is missing or used twice.";
                }
                if ($label === '' || mb_strlen($label) > 120) {
                    $problems[] = "Fix {$name}: the label needs 1 to 120 characters.";
                }
                if (mb_strlen((string) ($f['help'] ?? '')) > 300) {
                    $problems[] = "Fix {$name}: the help text is longer than 300 characters.";
                }
                $type = $f['type'] ?? null;
                if (! in_array($type, self::TYPES, true)) {
                    $problems[] = "Fix {$name}: the field type is not known.";
                    $seen[$key] = true;
                    continue;
                }
                if ($type === 'dropdown') {
                    $opts = array_values(array_filter(array_map('trim', (array) ($f['options'] ?? [])), fn ($o) => $o !== ''));
                    if (count($opts) < 2 || count($opts) > 20) {
                        $problems[] = "Fix {$name}: a dropdown needs 2 to 20 options.";
                    }
                }
                if ($type === 'number' && isset($f['min'], $f['max']) && is_numeric($f['min']) && is_numeric($f['max']) && (float) $f['min'] > (float) $f['max']) {
                    $problems[] = "Fix {$name}: the minimum is above the maximum.";
                }
                if (! empty($f['show_if'])) {
                    $c = $f['show_if'];
                    if (empty($c['field']) || ! isset($seen[$c['field']]) || ! in_array($c['op'] ?? '', self::OPS, true)) {
                        $problems[] = "Fix {$name}: a show-if rule can only look at an earlier field.";
                    }
                }
                $seen[$key] = true;
            }
        }
        if ($count < 1) {
            $problems[] = 'Add at least one field.';
        }

        return array_values(array_unique($problems));
    }

    /** @return array<string,array> the fields by key, in order. */
    public static function fields(array $schema): array
    {
        $out = [];
        foreach (($schema['sections'] ?? []) as $s) {
            foreach (($s['fields'] ?? []) as $f) {
                $out[$f['key']] = $f;
            }
        }

        return $out;
    }

    /** Is the field shown for these answers? (D6) A hidden field is never required and its answer is dropped. */
    public static function isVisible(array $field, array $answers): bool
    {
        $c = $field['show_if'] ?? null;
        if (empty($c)) {
            return true;
        }
        $v = $answers[$c['field']] ?? null;

        return match ($c['op']) {
            'equals'     => (string) $v === (string) ($c['value'] ?? ''),
            'not_equals' => (string) $v !== (string) ($c['value'] ?? ''),
            'is_yes'     => $v === 'yes' || $v === true,
            'is_no'      => $v === 'no' || $v === false,
            'gt'         => is_numeric($v) && (float) $v > (float) ($c['value'] ?? 0),
            'lt'         => is_numeric($v) && (float) $v < (float) ($c['value'] ?? 0),
            default      => true,
        };
    }

    /**
     * Cleans the answers sent by a device and works the result out (D7). The device never sends a result.
     *
     * @return array{answers: array, complete: bool, missing: int, result: ?string, errors: array<string,string>}
     */
    public static function evaluate(array $schema, array $input): array
    {
        $fields  = self::fields($schema);
        $answers = [];
        $errors  = [];

        // Walk the fields in order, so a show-if rule sees the cleaned answers before it.
        foreach ($fields as $key => $f) {
            if (! self::isVisible($f, $answers)) {
                continue;   // hidden: its answer is dropped
            }
            if (! array_key_exists($key, $input) || self::isEmpty($input[$key])) {
                continue;
            }
            [$value, $error] = self::clean($f, $input[$key]);
            if ($error) {
                $errors[$key] = $error;
            } else {
                $answers[$key] = $value;
            }
        }

        $missing = 0;
        $issues  = false;
        foreach ($fields as $key => $f) {
            if (! self::isVisible($f, $answers)) {
                continue;
            }
            $has = array_key_exists($key, $answers);
            if (! empty($f['required']) && ! $has) {
                $missing++;
                continue;
            }
            if (! $has) {
                continue;
            }
            if (($f['type'] ?? '') === 'yes_no' && ! empty($f['fails_on_no']) && $answers[$key] === 'no') {
                $issues = true;
            }
            if (($f['type'] ?? '') === 'number') {
                $min = $f['min'] ?? null;
                $max = $f['max'] ?? null;
                if (($min !== null && $min !== '' && $answers[$key] < (float) $min) || ($max !== null && $max !== '' && $answers[$key] > (float) $max)) {
                    $issues = true;
                }
            }
        }

        $complete = $missing === 0 && ! $errors;

        return [
            'answers'  => $answers,
            'complete' => $complete,
            'missing'  => $missing,
            'result'   => $complete ? ($issues ? 'issues' : 'ok') : null,
            'errors'   => $errors,
        ];
    }

    private static function isEmpty($v): bool
    {
        return $v === null || $v === '' || $v === [];
    }

    /** @return array{0:mixed,1:?string} */
    private static function clean(array $f, $v): array
    {
        $label = $f['label'] ?? $f['key'];

        switch ($f['type']) {
            case 'text':
                return mb_strlen((string) $v) > 2000 ? [null, "{$label} is too long."] : [(string) $v, null];
            case 'number':
                return is_numeric($v) ? [0 + $v, null] : [null, "{$label} must be a number."];
            case 'dropdown':
                $opts = array_map('trim', (array) ($f['options'] ?? []));
                return in_array((string) $v, $opts, true) ? [(string) $v, null] : [null, "{$label}: choose one of the options."];
            case 'checkbox':
                return [filter_var($v, FILTER_VALIDATE_BOOLEAN), null];
            case 'yes_no':
                return in_array($v, ['yes', 'no'], true) ? [$v, null] : [null, "{$label}: answer yes or no."];
            case 'date':
                $ok = is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && checkdate((int) substr($v, 5, 2), (int) substr($v, 8, 2), (int) substr($v, 0, 4));
                return $ok ? [$v, null] : [null, "{$label} must be a date."];
            case 'photo':
                $list = is_array($v) ? array_values($v) : [$v];
                return (count($list) >= 1 && count($list) <= 5) ? [$list, null] : [null, "{$label}: add 1 to 5 photos."];
            case 'signature':
                return [is_array($v) ? ($v[0] ?? null) : $v, null];
        }

        return [null, "{$label}: unknown field."];
    }

    /** A new, empty schema for the builder. */
    public static function blank(): array
    {
        return ['sections' => [['key' => 's1', 'title' => 'Checks', 'fields' => []]]];
    }

    /** Cleans a schema posted by the builder: known keys only, trimmed text, a key for every field. */
    public static function normalise(array $schema): array
    {
        $out = ['sections' => []];
        $n   = 0;
        foreach (($schema['sections'] ?? []) as $si => $s) {
            $sec = ['key' => $s['key'] ?? 's' . ($si + 1), 'title' => mb_substr(trim((string) ($s['title'] ?? '')), 0, 120), 'fields' => []];
            foreach (($s['fields'] ?? []) as $f) {
                $n++;
                $opts = $f['options'] ?? [];
                if (! is_array($opts)) {
                    $opts = preg_split('/\R/', (string) $opts);
                }
                $sec['fields'][] = [
                    'key'               => (string) (($f['key'] ?? '') !== '' ? $f['key'] : 'f' . $n . substr(md5(uniqid('', true)), 0, 4)),
                    'type'              => $f['type'] ?? 'text',
                    'label'             => trim((string) ($f['label'] ?? '')),
                    'required'          => ! empty($f['required']),
                    'help'              => trim((string) ($f['help'] ?? '')),
                    'unit'              => trim((string) ($f['unit'] ?? '')),
                    'min'               => (isset($f['min']) && is_numeric($f['min'])) ? 0 + $f['min'] : null,
                    'max'               => (isset($f['max']) && is_numeric($f['max'])) ? 0 + $f['max'] : null,
                    'options'           => array_values(array_filter(array_map('trim', $opts), fn ($o) => $o !== '')),
                    'fails_on_no'       => ! empty($f['fails_on_no']),
                    'visible_to_client' => ! empty($f['visible_to_client']),
                    'show_if'           => ! empty($f['show_if']['field']) ? [
                        'field' => $f['show_if']['field'], 'op' => $f['show_if']['op'] ?? 'equals', 'value' => $f['show_if']['value'] ?? null,
                    ] : null,
                ];
            }
            $out['sections'][] = $sec;
        }

        return $out;
    }
}
