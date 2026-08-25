<?php

declare(strict_types=1);

namespace Pam\Native\Sync\Crdt;

use InvalidArgumentException;

final readonly class LogicalTimestamp
{
    public function __construct(
        public int $wallTimeMillis,
        public int $counter,
        public string $nodeIdentifier,
    ) {
        if ($wallTimeMillis < 0 || $counter < 0 || preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D', $nodeIdentifier) !== 1) {
            throw new InvalidArgumentException('Logical timestamp is invalid.');
        }
    }

    public function compare(self $other): int
    {
        return [$this->wallTimeMillis, $this->counter, $this->nodeIdentifier]
            <=> [$other->wallTimeMillis, $other->counter, $other->nodeIdentifier];
    }

    /** @return array{wallTimeMillis:int,counter:int,nodeId:string} */
    public function toArray(): array
    {
        return ['wallTimeMillis' => $this->wallTimeMillis, 'counter' => $this->counter, 'nodeId' => $this->nodeIdentifier];
    }

    /** @param array<string,mixed> $value */
    public static function fromArray(array $value): self
    {
        return new self((int) ($value['wallTimeMillis'] ?? -1), (int) ($value['counter'] ?? -1), (string) ($value['nodeId'] ?? ''));
    }
}
