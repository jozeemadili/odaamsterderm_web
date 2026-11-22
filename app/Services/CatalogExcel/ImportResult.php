<?php

namespace App\Services\CatalogExcel;

/** What an upload did: counts, plus a message per problem row. */
class ImportResult
{
    public int $created = 0;

    public int $updated = 0;

    public int $photos = 0;

    /** @var array<int, list<string>> row number => problems (row 0 = the whole file) */
    public array $errors = [];

    /** @var array<int, list<string>> row number => notes that did not stop the row */
    public array $warnings = [];

    public function fail(int $row, string $message): void
    {
        $this->errors[$row][] = $message;
    }

    public function warn(int $row, string $message): void
    {
        $this->warnings[$row][] = $message;
    }

    public function failed(int $row): bool
    {
        return ! empty($this->errors[$row]);
    }

    /** @return array{created:int, updated:int, photos:int, errors:array<int, list<string>>, warnings:array<int, list<string>>} */
    public function toArray(): array
    {
        ksort($this->errors);
        ksort($this->warnings);

        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'photos' => $this->photos,
            'errors' => array_slice($this->errors, 0, 200, true),
            'warnings' => array_slice($this->warnings, 0, 200, true),
        ];
    }
}
