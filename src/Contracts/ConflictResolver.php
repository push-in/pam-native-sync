<?php
declare(strict_types=1);namespace Pam\Native\Sync\Contracts;
use Pam\Native\Sync\RemoteChange;use Pam\Native\Sync\SyncOperation;
interface ConflictResolver{public function applyRemote(?SyncOperation $local,RemoteChange $remote):bool;}
