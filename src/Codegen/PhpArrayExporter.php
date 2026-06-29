<?php

namespace DigitalElvis\NeuronAIStudio\Codegen;

class PhpArrayExporter
{
    public function exportArray(array $data, int $indent = 0): string
    {
        $pad = str_repeat('    ', $indent);
        $inner = str_repeat('    ', $indent + 1);
        $lines = ["{$pad}["];

        foreach ($data as $key => $value) {
            $exportedKey = is_int($key) ? $key : var_export($key, true);
            $lines[] = "{$inner}{$exportedKey} => ".$this->exportValue($value, $indent + 1).',';
        }

        $lines[] = "{$pad}]";

        return implode("\n", $lines);
    }

    public function exportValue(mixed $value, int $indent = 0): string
    {
        if (is_array($value)) {
            return $this->exportArray($value, $indent);
        }

        return var_export($value, true);
    }

    public function exportString(string $value): string
    {
        return var_export($value, true);
    }
}
