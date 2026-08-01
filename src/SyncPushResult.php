<?php
declare(strict_types=1);namespace Pam\Native\Sync;
final readonly class SyncPushResult{/** @param list<string> $acknowledged @param array<string,string> $rejected */public function __construct(public array $acknowledged,public array $rejected=[],public int $conflicts=0,public ?string $error=null){}}
