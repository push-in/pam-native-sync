<?php

declare(strict_types=1);

namespace Pam\Native\Sync;

use InvalidArgumentException;
use JsonSerializable;

final readonly class SyncOperation implements JsonSerializable
{
    /** @param array<string,mixed> $payload */
    public function __construct(public string $identifier, public string $collection, public string $recordIdentifier, public SyncOperationKind $kind, public array $payload, public int $baseVersion, public int $clientTimestampMillis, public int $attempts = 0)
    {
        foreach ([$identifier, $collection, $recordIdentifier] as $value) if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D', $value) !== 1) throw new InvalidArgumentException('Sync identifiers must be bounded safe ASCII.');
        if ($baseVersion < 0 || $clientTimestampMillis < 0 || $attempts < 0) throw new InvalidArgumentException('Sync versions, timestamps and attempts cannot be negative.');
        if ($kind === SyncOperationKind::Delete && $payload !== []) throw new InvalidArgumentException('Delete operations cannot carry a payload.');
        json_encode($payload, JSON_THROW_ON_ERROR);
    }
    /** @return array<string,mixed> */
    public function jsonSerialize(): array{return ['id'=>$this->identifier,'collection'=>$this->collection,'recordId'=>$this->recordIdentifier,'kind'=>$this->kind->value,'payload'=>$this->payload,'baseVersion'=>$this->baseVersion,'clientTimestampMillis'=>$this->clientTimestampMillis,'attempts'=>$this->attempts];}
}
