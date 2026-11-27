<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class OwnerFullName implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Please enter your first and last name.');

            return;
        }

        $name = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        $suffix = '(?:,?\s+(?:Jr\.?|Sr\.?|II|III|IV|V))?';
        $part = "\\p{L}+(?:['’.-]\\p{L}+)*\\.?";

        if ($name === '' || ! preg_match('/^'.$part.'(?:\s+'.$part.')+'.$suffix.'$/iu', $name)) {
            $fail('Use your first and last name. Letters, spaces, periods, hyphens, and apostrophes are allowed.');

            return;
        }

        $withoutSuffix = preg_replace('/'.$suffix.'$/iu', '', $name) ?? $name;
        $parts = preg_split('/\s+/u', trim($withoutSuffix)) ?: [];
        $letters = static fn (string $part): int => preg_match_all('/\p{L}/u', $part) ?: 0;

        if (count($parts) < 2 || $letters($parts[0]) < 2 || $letters($parts[array_key_last($parts)]) < 2) {
            $fail('Please enter your first and last name.');
        }
    }
}
