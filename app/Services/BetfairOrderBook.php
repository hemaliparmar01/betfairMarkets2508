<?php

namespace App\Services;

/** Keep repeated runner field names once while retaining every original value and key. */
final readonly class BetfairOrderBook
{
    /**
     * @param  array<string, mixed>  $head
     * @param  list<string|int>|null  $columns
     * @param  array<string|int, mixed>  $rows
     * @param  array<string|int, mixed>  $exceptions
     */
    private function __construct(private array $head, private ?array $columns, private array $rows, private array $exceptions) {}

    /** @param array<string, mixed> $event */
    public static function fromArray(array $event): self
    {
        $markets = $event['markets'] ?? null;
        if (! is_array($markets) || $markets === [] || ! is_array(reset($markets))) {
            return new self($event, null, [], []);
        }
        $columns = array_keys(reset($markets));
        $rows = [];
        $exceptions = [];
        foreach ($markets as $key => $row) {
            if (! is_array($row) || array_keys($row) !== $columns) {
                $exceptions[$key] = $row;
                $rows[$key] = null;
            } else {
                $rows[$key] = array_values($row);
            }
        }
        $event['markets'] = null;

        return new self($event, $columns, $rows, $exceptions);
    }

    public function isCompact(): bool
    {
        return $this->columns !== null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $event = $this->head;
        if ($this->columns !== null) {
            $event['markets'] = [];
            foreach ($this->rows as $key => $row) {
                $event['markets'][$key] = array_key_exists($key, $this->exceptions)
                    ? $this->exceptions[$key] : array_combine($this->columns, $row);
            }
        }

        return $event;
    }
}
