<?php
declare(strict_types=1);namespace Pam\Native\Sync;
final readonly class SyncPullResult{/** @param list<RemoteChange> $changes */public function __construct(public string $cursor,public array $changes,public bool $hasMore=false,public ?string $error=null){}}
