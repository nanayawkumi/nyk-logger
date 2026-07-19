<?php

declare(strict_types=1);

namespace Nanayawkumi\NykLogger\Support;

/**
 * Scrubs sensitive values out of context/request data before it is ever
 * transmitted to a third-party API. Matching is done on array keys
 * (case-insensitive) and on value patterns (regex).
 */
final class Redactor
{
    private const PLACEHOLDER = '[REDACTED]';

    /**
     * @param  list<string>  $keys
     * @param  list<string>  $patterns
     */
    public function __construct(
        private readonly array $keys = [],
        private readonly array $patterns = [],
    ) {
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function scrub(array $data): array
    {
        $loweredKeys = array_map('strtolower', $this->keys);

        $result = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $loweredKeys, true)) {
                $result[$key] = self::PLACEHOLDER;

                continue;
            }

            $result[$key] = match (true) {
                is_array($value) => $this->scrub($value),
                is_string($value) => $this->scrubString($value),
                default => $value,
            };
        }

        return $result;
    }

    public function scrubString(string $value): string
    {
        foreach ($this->patterns as $pattern) {
            $replaced = @preg_replace($pattern, '$1'.self::PLACEHOLDER, $value);

            if (is_string($replaced)) {
                $value = $replaced;
            }
        }

        return $value;
    }
}
