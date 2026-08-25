<?php

declare(strict_types=1);

namespace Pam\Native\Sync\Crdt;

use InvalidArgumentException;

final class LastWriteWinsMap
{
    /** @var array<string,array{value:mixed,timestamp:LogicalTimestamp,deleted:bool}> */
    private array $entries = [];

    public function assign(string $key, mixed $value, LogicalTimestamp $timestamp): void
    {
        $this->apply($key, $value, $timestamp, false);
    }

    public function remove(string $key, LogicalTimestamp $timestamp): void
    {
        $this->apply($key, null, $timestamp, true);
    }

    public function merge(self $remote): void
    {
        foreach ($remote->entries as $key => $entry) {
            $this->apply($key, $entry['value'], $entry['timestamp'], $entry['deleted']);
        }
    }

    public function value(string $key): mixed
    {
        $entry = $this->entries[$key] ?? null;
        return $entry === null || $entry['deleted'] ? null : $entry['value'];
    }

    /** @return array<string,mixed> */
    public function values(): array
    {
        $values = [];
        foreach ($this->entries as $key => $entry) {
            if (!$entry['deleted']) {
                $values[$key] = $entry['value'];
            }
        }
        ksort($values, SORT_STRING);
        return $values;
    }

    /** @return array{version:int,entries:array<string,array{value:mixed,timestamp:array{wallTimeMillis:int,counter:int,nodeId:string},deleted:bool}>} */
    public function snapshot(): array
    {
        $entries = [];
        foreach ($this->entries as $key => $entry) {
            $entries[$key] = ['value' => $entry['value'], 'timestamp' => $entry['timestamp']->toArray(), 'deleted' => $entry['deleted']];
        }
        ksort($entries, SORT_STRING);
        return ['version' => 1, 'entries' => $entries];
    }

    /** @param array<string,mixed> $snapshot */
    public static function restore(array $snapshot): self
    {
        if (($snapshot['version'] ?? null) !== 1 || !is_array($snapshot['entries'] ?? null) || count($snapshot['entries']) > 100_000) {
            throw new InvalidArgumentException('CRDT snapshot is invalid.');
        }
        $map = new self();
        foreach ($snapshot['entries'] as $key => $entry) {
            if (!is_string($key) || !is_array($entry) || !is_array($entry['timestamp'] ?? null) || !is_bool($entry['deleted'] ?? null)) {
                throw new InvalidArgumentException('CRDT snapshot entry is invalid.');
            }
            $map->apply($key, $entry['value'] ?? null, LogicalTimestamp::fromArray($entry['timestamp']), $entry['deleted']);
        }
        return $map;
    }

    private function apply(string $key, mixed $value, LogicalTimestamp $timestamp, bool $deleted): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,255}$/D', $key) !== 1) {
            throw new InvalidArgumentException('CRDT key is invalid.');
        }
        $current = $this->entries[$key] ?? null;
        if ($current !== null && $timestamp->compare($current['timestamp']) < 0) {
            return;
        }
        $this->entries[$key] = ['value' => $value, 'timestamp' => $timestamp, 'deleted' => $deleted];
    }
}
