<?php

namespace App\Automation;

/**
 * A workflow definition: { trigger, trigger_config, steps }. normalize() cleans what the builder
 * sends and reports errors by path ("steps.2.params.url"); compile() turns the nested steps into
 * a flat program with jumps, so a run can stop at a wait and resume at any step later.
 */
class Definition
{
    public const MAX_STEPS = 60;

    public const MAX_DEPTH = 4;

    private static ?array $catalog = null;

    public static function catalog(): array
    {
        return self::$catalog ??= require resource_path('automation/catalog.php');
    }

    public static function templates(): array
    {
        return require resource_path('automation/templates.php');
    }

    /**
     * @return array{0: array, 1: array<string, string>} [definition, errors]
     */
    public static function normalize(array $input): array
    {
        $catalog = self::catalog();
        $errors = [];
        $trigger = (string) ($input['trigger'] ?? '');
        if (! isset($catalog['triggers'][$trigger])) {
            $errors['trigger'] = 'Choose what starts this workflow.';
            $trigger = array_key_first($catalog['triggers']);
        }

        $config = [];
        foreach ($catalog['triggers'][$trigger]['config'] ?? [] as $key => $field) {
            [$config[$key], $error] = self::value($field, $input['trigger_config'][$key] ?? null);
            if ($error) {
                $errors["trigger_config.{$key}"] = $error;
            }
        }

        $count = 0;
        $steps = self::steps((array) ($input['steps'] ?? []), 'steps', 1, $errors, $count);
        if ($count === 0) {
            $errors['steps'] = 'Add at least one step.';
        }

        return [['trigger' => $trigger, 'trigger_config' => $config, 'steps' => $steps], $errors];
    }

    private static function steps(array $raw, string $path, int $depth, array &$errors, int &$count): array
    {
        $catalog = self::catalog();
        $out = [];
        foreach (array_values($raw) as $i => $step) {
            if (! is_array($step) || ++$count > self::MAX_STEPS) {
                continue;
            }
            $p = "{$path}.{$i}";
            switch ($step['type'] ?? null) {
                case 'wait':
                    $unit = isset($catalog['wait_units'][$step['unit'] ?? '']) ? $step['unit'] : 'days';
                    $amount = (int) ($step['amount'] ?? 0);
                    $max = ['minutes' => 10080, 'hours' => 2160, 'days' => 365][$unit];
                    if ($amount < 1 || $amount > $max) {
                        $errors["{$p}.amount"] = "Wait between 1 and {$max} {$unit}.";
                        $amount = max(1, min($max, $amount));
                    }
                    $out[] = ['type' => 'wait', 'amount' => $amount, 'unit' => $unit];
                    break;

                case 'condition':
                    $rules = [];
                    foreach (array_values((array) ($step['rules'] ?? [])) as $r => $rule) {
                        $field = $catalog['conditions'][$rule['field'] ?? ''] ?? null;
                        if (! $field) {
                            $errors["{$p}.rules.{$r}"] = 'Choose what to check.';

                            continue;
                        }
                        $op = in_array($rule['op'] ?? '', $field['ops'], true) ? $rule['op'] : $field['ops'][0];
                        $value = $rule['value'] ?? null;
                        if ($field['type'] === 'number') {
                            $value = is_numeric($value) ? (float) $value : null;
                        } elseif (in_array($field['type'], ['products', 'collections'], true)) {
                            $value = array_values(array_filter(array_map(fn ($v) => is_array($v) ? array_intersect_key($v, array_flip(['id', 'title'])) : null, (array) $value)));
                        } elseif ($field['type'] === 'select') {
                            $value = isset($field['options'][$value]) ? $value : array_key_first($field['options']);
                        } else {
                            $value = trim(mb_substr(strip_tags((string) $value), 0, 500));
                        }
                        if ($value === null || $value === '' || $value === []) {
                            $errors["{$p}.rules.{$r}"] = 'Fill in the value for “'.$field['label'].'”.';
                        }
                        $rules[] = ['field' => $rule['field'], 'op' => $op, 'value' => $value];
                    }
                    if (! $rules) {
                        $errors["{$p}.rules"] = 'Add at least one rule to the condition.';
                    }
                    if ($depth >= self::MAX_DEPTH) {
                        $errors[$p] = 'Conditions can be nested at most '.self::MAX_DEPTH.' levels deep.';
                    }
                    $out[] = [
                        'type' => 'condition',
                        'match' => ($step['match'] ?? 'all') === 'any' ? 'any' : 'all',
                        'rules' => $rules,
                        'then' => self::steps((array) ($step['then'] ?? []), "{$p}.then", $depth + 1, $errors, $count),
                        'else' => self::steps((array) ($step['else'] ?? []), "{$p}.else", $depth + 1, $errors, $count),
                    ];
                    break;

                case 'action':
                    $action = $catalog['actions'][$step['action'] ?? ''] ?? null;
                    if (! $action) {
                        $errors[$p] = 'Choose an action.';
                        break;
                    }
                    $params = [];
                    foreach ($action['params'] as $key => $field) {
                        [$params[$key], $error] = self::value($field, $step['params'][$key] ?? null);
                        if ($error) {
                            $errors["{$p}.params.{$key}"] = $error;
                        }
                    }
                    if ($step['action'] === 'webhook' && $params['url'] !== '' && ! preg_match('#^https://[^\s"\'<>]+$#', $params['url'])) {
                        $errors["{$p}.params.url"] = 'Use a full https:// address.';
                    }
                    $out[] = ['type' => 'action', 'action' => $step['action'], 'params' => $params];
                    break;
            }
        }

        return $out;
    }

    /** @return array{0: mixed, 1: ?string} */
    private static function value(array $field, mixed $raw): array
    {
        $label = $field['label'] ?? 'This field';
        $required = $field['required'] ?? false;
        switch ($field['type']) {
            case 'number':
                if ($raw === null || $raw === '') {
                    return [$field['default'] ?? null, $required && ! isset($field['default']) ? "{$label} is required." : null];
                }
                $n = (float) $raw;
                if (isset($field['min']) && $n < $field['min'] || isset($field['max']) && $n > $field['max']) {
                    return [$n, "{$label} must be between {$field['min']} and {$field['max']}."];
                }

                return [$n, null];
            case 'select':
                return [isset($field['options'][$raw]) ? $raw : ($field['default'] ?? array_key_first($field['options'])), null];
            case 'products':
            case 'collections':
                $list = array_values(array_filter(array_map(fn ($v) => is_array($v) && preg_match('#^gid://shopify/(Product|Collection)/\d+$#', (string) ($v['id'] ?? '')) ? array_intersect_key($v, array_flip(['id', 'title', 'handle', 'image'])) : null, (array) $raw)));

                return [$list, $required && ! $list ? "Choose at least one item for {$label}." : null];
            default: // text, textarea, workflow
                $value = trim(strip_tags((string) ($raw ?? ($field['default'] ?? ''))));
                $max = $field['max'] ?? 500;
                if ($required && $value === '') {
                    return [$value, "{$label} is required."];
                }

                return mb_strlen($value) > $max ? [mb_substr($value, 0, $max), "{$label} must be {$max} characters or fewer."] : [$value, null];
        }
    }

    /**
     * Flattens steps into [{t: action|wait|cond|jump, ...}]. A condition jumps to `else` when it
     * fails; the end of a "then" branch jumps past the "else" branch.
     */
    public static function compile(array $steps, array &$program = []): array
    {
        foreach ($steps as $step) {
            if ($step['type'] === 'condition') {
                $at = count($program);
                $program[] = ['t' => 'cond', 'match' => $step['match'], 'rules' => $step['rules'], 'else' => null];
                self::compile($step['then'], $program);
                if ($step['else']) {
                    $jump = count($program);
                    $program[] = ['t' => 'jump', 'to' => null];
                    $program[$at]['else'] = count($program);
                    self::compile($step['else'], $program);
                    $program[$jump]['to'] = count($program);
                } else {
                    $program[$at]['else'] = count($program);
                }
            } elseif ($step['type'] === 'wait') {
                $program[] = ['t' => 'wait', 'amount' => $step['amount'], 'unit' => $step['unit']];
            } else {
                $program[] = ['t' => 'action', 'action' => $step['action'], 'params' => $step['params']];
            }
        }

        return $program;
    }

    /** A one-line description of a step, for logs and the builder. */
    public static function describe(array $step): string
    {
        $catalog = self::catalog();

        return match ($step['type'] ?? $step['t'] ?? null) {
            'wait' => "Wait {$step['amount']} {$step['unit']}",
            'condition', 'cond' => 'If '.implode($step['match'] === 'any' ? ' or ' : ' and ', array_map(fn ($r) => ($catalog['conditions'][$r['field']]['label'] ?? $r['field']).' '.($catalog['operators'][$r['op']] ?? $r['op']).' '.(is_array($r['value']) ? implode(', ', array_column($r['value'], 'title')) : $r['value']), $step['rules'])),
            'action' => $catalog['actions'][$step['action']]['label'] ?? $step['action'],
            default => '',
        };
    }
}
