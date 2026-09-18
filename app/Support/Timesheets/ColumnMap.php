<?php

namespace App\Support\Timesheets;

/**
 * Which column of an uploaded sheet holds which part of a day, and the row the
 * data starts on.
 */
class ColumnMap
{
    public function __construct(
        public readonly ?int $date = null,
        public readonly ?int $start = null,
        public readonly ?int $end = null,
        public readonly ?int $status = null,
        public readonly ?int $remark = null,
        public readonly int $firstDataRow = 0,
    ) {}

    /**
     * The layout this application's own CSV export writes, used when a file
     * carries no header for its columns to be read from.
     */
    public static function positional(): self
    {
        return new self(date: 0, start: 1, end: 2, status: null, remark: 4);
    }

    /**
     * @param  array{date?: int|null, start?: int|null, end?: int|null, status?: int|null, remark?: int|null, firstDataRow?: int|null}  $fields
     */
    public static function fromArray(array $fields): self
    {
        return new self(
            date: $fields['date'] ?? null,
            start: $fields['start'] ?? null,
            end: $fields['end'] ?? null,
            status: $fields['status'] ?? null,
            remark: $fields['remark'] ?? null,
            firstDataRow: $fields['firstDataRow'] ?? 0,
        );
    }

    /**
     * @return array{date: int|null, start: int|null, end: int|null, status: int|null, remark: int|null, firstDataRow: int}
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'start' => $this->start,
            'end' => $this->end,
            'status' => $this->status,
            'remark' => $this->remark,
            'firstDataRow' => $this->firstDataRow,
        ];
    }

    /**
     * A day without a date cannot be placed on the calendar, so that one column
     * is the least a file has to offer.
     */
    public function isUsable(): bool
    {
        return $this->date !== null;
    }

    public function withFirstDataRow(int $row): self
    {
        return new self($this->date, $this->start, $this->end, $this->status, $this->remark, $row);
    }
}
