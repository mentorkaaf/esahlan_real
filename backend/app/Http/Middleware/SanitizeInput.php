<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SanitizeInput
{
    // Control characters to strip from text inputs (keeps \t \n \r)
    const CONTROL_CHARS = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u';

    public function handle(Request $request, Closure $next): mixed
    {
        $input = $request->all();
        $cleaned = $this->cleanArray($input);
        $request->replace($cleaned);

        return $next($request);
    }

    private function cleanArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = $this->cleanString($value);
            } elseif (is_array($value)) {
                $data[$key] = $this->cleanArray($value);
            }
            // Files, booleans, integers: untouched
        }
        return $data;
    }

    private function cleanString(string $value): string
    {
        // Strip null bytes (can bypass string comparisons)
        $value = str_replace("\0", '', $value);
        // Strip control characters except whitespace (\t \n \r)
        $value = preg_replace(self::CONTROL_CHARS, '', $value) ?? $value;
        return $value;
    }
}
