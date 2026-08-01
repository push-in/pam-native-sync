<?php
declare(strict_types=1);namespace Pam\Native\Sync;
final readonly class SyncRunReport{public function __construct(public SyncRunState $state,public int $pushed,public int $pulled,public int $conflicts,public string $cursor,public ?string $message=null){}}
