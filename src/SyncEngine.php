<?php

declare(strict_types=1);

namespace Pam\Native\Sync;

use Closure;
use LogicException;
use Pam\Native\Sync\Contracts\ConflictResolver;
use Pam\Native\Sync\Contracts\SyncStore;
use Pam\Native\Sync\Contracts\SyncTransport;
use Throwable;

final class SyncEngine
{
    private bool $running = false;
    public function __construct(private readonly SyncStore $store,private readonly SyncTransport $transport,private readonly ConflictResolver $resolver=new PolicyConflictResolver(),private readonly int $batchSize=100,private readonly int $maxAttempts=8){if($batchSize<1||$batchSize>1000||$maxAttempts<1||$maxAttempts>100)throw new LogicException('Invalid sync engine limits.');}
    /** @param array<string,mixed> $payload @param Closure(SyncOperation):void $complete */
    public function upsert(string $collection,string $recordIdentifier,array $payload,int $baseVersion,Closure $complete):void{$this->enqueue($collection,$recordIdentifier,SyncOperationKind::Upsert,$payload,$baseVersion,$complete);}
    /** @param Closure(SyncOperation):void $complete */
    public function delete(string $collection,string $recordIdentifier,int $baseVersion,Closure $complete):void{$this->enqueue($collection,$recordIdentifier,SyncOperationKind::Delete,[],$baseVersion,$complete);}
    /** @param Closure(SyncRunReport):void $complete */
    public function synchronize(Closure $complete):void
    {
        if($this->running)throw new LogicException('A synchronization run is already active.');$this->running=true;
        try{$this->store->initialize(function()use($complete):void{$this->store->pending($this->batchSize,function(array $pending)use($complete):void{$this->transport->push($pending,function(SyncPushResult $push)use($pending,$complete):void{if($push->error!==null){$this->fail($complete,$push->error);return;}$this->store->acknowledge($push->acknowledged,function()use($push,$pending,$complete):void{$this->store->reject($push->rejected,$this->maxAttempts,function()use($push,$pending,$complete):void{$this->store->cursor(function(string $cursor)use($push,$pending,$complete):void{$this->pullPages($cursor,0,0,function(string $next,int $pulled,int $conflicts,?string $error)use($push,$pending,$complete):void{if($error!==null){$this->fail($complete,$error);return;}$this->running=false;$complete(new SyncRunReport($push->rejected===[]?SyncRunState::Succeeded:SyncRunState::Partial,count($push->acknowledged),$pulled,$conflicts+$push->conflicts,$next));});});});});});});});}catch(Throwable $error){$this->fail($complete,$error->getMessage());}
    }
    /** @param Closure(string,int,int,?string):void $complete */
    private function pullPages(string $cursor,int $page,int $total,Closure $complete):void{$this->transport->pull($cursor,$this->batchSize,function(SyncPullResult $result)use($page,$total,$complete):void{if($result->error!==null){$complete($cursor,$total,0,$result->error);return;}$this->store->apply($result->changes,$result->cursor,$this->resolver,function(int $conflicts)use($result,$page,$total,$complete):void{$nextTotal=$total+count($result->changes);if($result->hasMore&&$page<99){$this->pullPages($result->cursor,$page+1,$nextTotal,static function(string $cursor,int $pulled,int $nestedConflicts,?string $error)use($complete,$conflicts):void{$complete($cursor,$pulled,$conflicts+$nestedConflicts,$error);});return;}$complete($result->cursor,$nextTotal,$conflicts,null);});});}
    /** @param Closure(SyncRunReport):void $complete */private function fail(Closure$complete,string$message):void{$this->running=false;$complete(new SyncRunReport(SyncRunState::Failed,0,0,0,'',$message));}
    /** @param array<string,mixed> $payload @param Closure(SyncOperation):void $complete */
    private function enqueue(string $collection,string $recordIdentifier,SyncOperationKind $kind,array $payload,int $baseVersion,Closure $complete):void{$operation=new SyncOperation(bin2hex(random_bytes(16)),$collection,$recordIdentifier,$kind,$payload,$baseVersion,(int)floor(microtime(true)*1000));$this->store->initialize(fn()=>$this->store->enqueue($operation,fn()=>$complete($operation)));}
}
