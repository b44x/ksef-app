<?php

namespace App\Import;

/** What an import did: how many records it made and changed, and the rows it left out, with the reason. */
final class ImportResult
{
    /**
     * @param  list<array{line: int, message: string}>  $errors
     */
    public function __construct(public readonly int $created = 0, public readonly int $updated = 0, public readonly array $errors = []) {}

    /**
     * @return array{created: int, updated: int, errors: list<array{line: int, message: string}>}
     */
    public function toArray(): array
    {
        return ['created' => $this->created, 'updated' => $this->updated, 'errors' => $this->errors];
    }
}
