<?php
declare(strict_types=1);namespace Pam\Native\Sync\Contracts;
use Closure;use Pam\Native\Sync\SyncOperation;
interface SyncTransport{/** @param list<SyncOperation> $operations @param Closure(\Pam\Native\Sync\SyncPushResult):void $complete */public function push(array $operations,Closure $complete):void;/** @param Closure(\Pam\Native\Sync\SyncPullResult):void $complete */public function pull(string $cursor,int $limit,Closure $complete):void;}
