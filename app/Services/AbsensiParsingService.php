<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AbsensiParsingService
{
    private const EMPLOYEE_CODE_PATTERN = '/^\d{4,10}$/';

    public function collectLogs(array $files, string $targetDate, string $nextDate): array
    {
        $rawLogs = [];
        $skippedLines = 0;
        $allowedDates = [$targetDate, $nextDate];

        foreach ($files as $file) {
            if (!Storage::disk('public')->exists($file)) {
                Log::warning('Absensi file tidak ditemukan.', ['file' => $file]);
                continue;
            }

            $result = $this->parseContent(
                Storage::disk('public')->get($file),
                $file,
                $allowedDates,
            );

            foreach ($result['raw_logs'] as $empCode => $entries) {
                $rawLogs[$empCode] = array_merge($rawLogs[$empCode] ?? [], $entries);
            }

            $skippedLines += $result['skipped_lines'];
        }

        return [
            'raw_logs' => $rawLogs,
            'skipped_lines' => $skippedLines,
        ];
    }

    public function parseContent(string $fileContent, string $filePath, array $allowedDates): array
    {
        $rawLogs = [];
        $skippedLines = 0;
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $fileContent));
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        foreach ($lines as $lineNumber => $line) {
            $parsedLine = $this->parseLine($line, $extension);
            if (!$parsedLine) {
                if (trim($line) !== '') {
                    $skippedLines++;
                    Log::warning('Baris absensi dilewati karena format tidak valid.', [
                        'file' => $filePath,
                        'line' => $lineNumber + 1,
                        'content' => trim($line),
                    ]);
                }
                continue;
            }

            if (!in_array($parsedLine['date'], $allowedDates, true)) {
                continue;
            }

            $rawLogs[$parsedLine['emp_code']][] = [
                'date' => $parsedLine['date'],
                'time' => $parsedLine['time'],
                'full' => Carbon::parse("{$parsedLine['date']} {$parsedLine['time']}"),
            ];
        }

        return [
            'raw_logs' => $rawLogs,
            'skipped_lines' => $skippedLines,
        ];
    }

    private function parseLine(string $line, string $extension): ?array
    {
        $trimmedLine = trim($line);
        if ($trimmedLine === '') {
            return null;
        }

        $parts = $this->splitLine($trimmedLine, $extension);
        if (empty($parts)) {
            return null;
        }

        $empCode = $this->extractEmployeeCode($parts);
        if ($empCode === null) {
            return null;
        }

        $dateData = $this->extractDate($parts);
        if ($dateData === null) {
            return null;
        }

        $timeToken = $this->extractTimeToken($parts, $dateData['index']);
        $timeValue = $this->normalizeTime($timeToken);

        if ($timeValue === null) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $dateData['value'])->format('Y-m-d');
            Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$timeValue}");
        } catch (\Exception $e) {
            return null;
        }

        return [
            'emp_code' => $empCode,
            'date' => $date,
            'time' => $timeValue,
        ];
    }

    private function splitLine(string $line, string $extension): array
    {
        if (($extension === 'txt' || $extension === 'dat') && str_contains($line, "\t")) {
            $tabSeparatedParts = preg_split('/\t+/', $line);

            return $this->normalizeParts($tabSeparatedParts);
        }

        $spaceSeparatedParts = preg_split('/\s+/', $line);

        return $this->normalizeParts($spaceSeparatedParts);
    }

    private function normalizeParts(array|false $parts): array
    {
        if (!is_array($parts)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', $parts)));
    }

    private function extractEmployeeCode(array $parts): ?string
    {
        $candidate = trim($parts[2] ?? '');
        if (preg_match(self::EMPLOYEE_CODE_PATTERN, $candidate) === 1) {
            return $candidate;
        }

        foreach ($parts as $part) {
            $token = trim($part);
            if (preg_match(self::EMPLOYEE_CODE_PATTERN, $token) === 1) {
                return $token;
            }
        }

        return null;
    }

    private function extractDate(array $parts): ?array
    {
        foreach ($parts as $index => $value) {
            if (preg_match('/^\d{4}[\/\-]\d{2}[\/\-]\d{2}$/', $value) !== 1) {
                continue;
            }

            return [
                'index' => $index,
                'value' => str_replace('/', '-', $value),
            ];
        }

        return null;
    }

    private function extractTimeToken(array $parts, int $dateIndex): ?string
    {
        $nextToken = $parts[$dateIndex + 1] ?? null;
        if ($this->isTimeToken($nextToken)) {
            return $nextToken;
        }

        foreach ($parts as $part) {
            if ($this->isTimeToken($part)) {
                return $part;
            }
        }

        return null;
    }

    private function normalizeTime(?string $time): ?string
    {
        if (!$this->isTimeToken($time)) {
            return null;
        }

        $trimmedTime = trim($time);
        if (strlen($trimmedTime) === 5) {
            return "{$trimmedTime}:00";
        }

        return $trimmedTime;
    }

    private function isTimeToken(?string $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', trim($value)) === 1;
    }
}
