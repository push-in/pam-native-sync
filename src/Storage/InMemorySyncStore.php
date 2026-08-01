<?php

declare(strict_types=1);

namespace Pam\Native\Sync\Storage;

use Closure;
use Pam\Native\Sync\Contracts\ConflictResolver;
use Pam\Native\Sync\Contracts\SyncStore;
use Pam\Native\Sync\RemoteChange;
use Pam\Native\Sync\SyncOperation;
use Pam\Native\Sync\SyncOperationKind;

final class InMemorySyncStore implements SyncStore
{
    /** @var array<string,SyncOperation> */ private array $operations=[];
    /** @var array<string,array{payload:array<string,mixed>,version:int,deleted:bool}> */ private array $records=[];
    private string $currentCursor='';
    public function initialize(Closure $complete):void{$complete();}
    public function enqueue(SyncOperation $operation,Closure $complete):void{$this->operations[$operation->identifier]=$operation;$complete();}
    public function pending(int $limit,Closure $complete):void{$operations=array_values($this->operations);usort($operations,static fn(SyncOperation $a,SyncOperation $b)=>[$a->clientTimestampMillis,$a->identifier]<=>[$b->clientTimestampMillis,$b->identifier]);$complete(array_slice($operations,0,$limit));}
    public function acknowledge(array $ids,Closure $complete):void{foreach($ids as$id)unset($this->operations[$id]);$complete();}
    public function reject(array $rejected,int $maxAttempts,Closure $complete):void{foreach($rejected as$id=>$message){$operation=$this->operations[$id]??null;if(!$operation)continue;$attempts=$operation->attempts+1;if($attempts>=$maxAttempts){unset($this->operations[$id]);continue;}$this->operations[$id]=new SyncOperation($operation->identifier,$operation->collection,$operation->recordIdentifier,$operation->kind,$operation->payload,$operation->baseVersion,$operation->clientTimestampMillis,$attempts);}$complete();}
    public function cursor(Closure $complete):void{$complete($this->currentCursor);}
    public function apply(array $changes,string $cursor,ConflictResolver $resolver,Closure $complete):void{$conflicts=0;foreach($changes as$change){if(!$change instanceof RemoteChange)continue;$local=$this->local($change);if($local!==null){$conflicts++;if(!$resolver->applyRemote($local,$change))continue;}$key=$change->collection."\0".$change->recordIdentifier;$this->records[$key]=['payload'=>$change->payload,'version'=>$change->version,'deleted'=>$change->kind===SyncOperationKind::Delete];}$this->currentCursor=$cursor;$complete($conflicts);}
    /** @return ?array{payload:array<string,mixed>,version:int,deleted:bool} */public function record(string $collection,string $recordIdentifier):?array{return $this->records[$collection."\0".$recordIdentifier]??null;}
    private function local(RemoteChange $change):?SyncOperation{foreach($this->operations as$operation)if($operation->collection===$change->collection&&$operation->recordIdentifier===$change->recordIdentifier)return$operation;return null;}
}
