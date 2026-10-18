<?php

namespace App\Support;

use Closure;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams RFC 4180 CSV downloads.
 *
 * Cells beginning with =, +, -, @, tab or carriage return are prefixed with
 * an apostrophe so spreadsheet applications treat them as text instead of
 * evaluating them as formulas (CSV/formula injection).
 */
class CsvWriter
{
    public static function sanitise(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    /**
     * @param  array<int, array<int, string>>  $preamble  Rows written before the heading (report title, filters).
     * @param  array<int, string>  $headings
     * @param  Closure(Closure(array<int, mixed>): void): void  $rows  Receives a writer callback to call once per row.
     */
    public static function download(string $filename, array $preamble, array $headings, Closure $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($preamble, $headings, $rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel detects the encoding.

            $write = fn (array $row) => fputcsv($handle, array_map(static::sanitise(...), $row), ',', '"', '');

            foreach ($preamble as $line) {
                $write($line);
            }

            if ($preamble !== []) {
                $write([]);
            }

            $write($headings);
            $rows($write);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
