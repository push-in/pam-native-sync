<?php
declare(strict_types=1);namespace Pam\Native\Sync;
use InvalidArgumentException;
final readonly class RemoteChange{/** @param array<string,mixed> $payload */public function __construct(public string $collection,public string $recordIdentifier,public SyncOperationKind $kind,public array $payload,public int $version,public int $serverTimestampMillis){if($version<1||$serverTimestampMillis<0)throw new InvalidArgumentException('Remote versions must be positive.');if($kind===SyncOperationKind::Delete&&$payload!==[])throw new InvalidArgumentException('Delete changes cannot carry payload.');}}
