<?php

declare(strict_types=1);

namespace Pam\Native\Sync\Storage;

use Closure;
use JsonException;
use Pam\Native\Database\SQLite;
use Pam\Native\Sync\Contracts\ConflictResolver;
use Pam\Native\Sync\Contracts\SyncStore;
use Pam\Native\Sync\RemoteChange;
use Pam\Native\Sync\SyncOperation;
use Pam\Native\Sync\SyncOperationKind;

final readonly class SQLiteSyncStore implements SyncStore
{
    public function __construct(private string $database='pam-sync.db',private string $scope='default'){}
    public function initialize(Closure $complete):void{SQLite::transaction($this->database,[
        ['sql'=>'CREATE TABLE IF NOT EXISTS pam_sync_outbox (id TEXT PRIMARY KEY, collection_name TEXT NOT NULL, record_id TEXT NOT NULL, operation_kind INTEGER NOT NULL, payload_json TEXT NOT NULL, base_version INTEGER NOT NULL, client_timestamp INTEGER NOT NULL, attempts INTEGER NOT NULL DEFAULT 0, operation_state INTEGER NOT NULL DEFAULT 1, last_error TEXT NOT NULL DEFAULT \'\')'],
        ['sql'=>'CREATE INDEX IF NOT EXISTS pam_sync_outbox_pending ON pam_sync_outbox(operation_state, client_timestamp, id)'],
        ['sql'=>'CREATE TABLE IF NOT EXISTS pam_sync_records (collection_name TEXT NOT NULL, record_id TEXT NOT NULL, operation_kind INTEGER NOT NULL, payload_json TEXT NOT NULL, server_version INTEGER NOT NULL, server_timestamp INTEGER NOT NULL, PRIMARY KEY(collection_name, record_id))'],
        ['sql'=>'CREATE TABLE IF NOT EXISTS pam_sync_cursors (scope TEXT PRIMARY KEY, cursor_value TEXT NOT NULL)'],
    ],$complete);}
    public function enqueue(SyncOperation $operation,Closure $complete):void{SQLite::execute($this->database,'INSERT OR IGNORE INTO pam_sync_outbox (id,collection_name,record_id,operation_kind,payload_json,base_version,client_timestamp,attempts,operation_state) VALUES (?,?,?,?,?,?,?,?,?)',[$operation->identifier,$operation->collection,$operation->recordIdentifier,$operation->kind->value,$this->json($operation->payload),$operation->baseVersion,$operation->clientTimestampMillis,$operation->attempts,1],$complete);}
    public function pending(int $limit,Closure $complete):void{SQLite::query($this->database,'SELECT id,collection_name,record_id,operation_kind,payload_json,base_version,client_timestamp,attempts FROM pam_sync_outbox WHERE operation_state = 1 ORDER BY client_timestamp,id LIMIT ?',[$limit],static function(array $rows)use($complete):void{$complete(array_map(static fn(array$r)=>new SyncOperation((string)$r['id'],(string)$r['collection_name'],(string)$r['record_id'],SyncOperationKind::from((int)$r['operation_kind']),self::decode((string)$r['payload_json']),(int)$r['base_version'],(int)$r['client_timestamp'],(int)$r['attempts']),$rows));});}
    public function acknowledge(array $ids,Closure $complete):void{if($ids===[]){$complete();return;}$marks=implode(',',array_fill(0,count($ids),'?'));SQLite::execute($this->database,"DELETE FROM pam_sync_outbox WHERE id IN ($marks)",array_values($ids),$complete);}
    public function reject(array $rejected,int $maxAttempts,Closure $complete):void{if($rejected===[]){$complete();return;}$sets=[];foreach($rejected as$id=>$message)$sets[]=[substr($message,0,1024),$maxAttempts,$id];SQLite::executeMany($this->database,'UPDATE pam_sync_outbox SET attempts=attempts+1,last_error=?,operation_state=CASE WHEN attempts+1>=? THEN 4 ELSE 1 END WHERE id=?',$sets,$complete);}
    public function cursor(Closure $complete):void{SQLite::query($this->database,'SELECT cursor_value FROM pam_sync_cursors WHERE scope=?',[$this->scope],static fn(array$rows)=>$complete((string)($rows[0]['cursor_value']??'')));}
    public function apply(array $changes,string $cursor,ConflictResolver $resolver,Closure $complete):void{$statements=[];$conflicts=0;$this->resolveChanges($changes,0,$resolver,$statements,$conflicts,function()use(&$statements,&$conflicts,$cursor,$complete):void{$statements[]=['sql'=>'INSERT INTO pam_sync_cursors(scope,cursor_value) VALUES(?,?) ON CONFLICT(scope) DO UPDATE SET cursor_value=excluded.cursor_value','arguments'=>[$this->scope,$cursor]];SQLite::transaction($this->database,$statements,static fn()=>$complete($conflicts));});}
    /** @param list<RemoteChange> $changes @param list<array{sql:string,arguments:list<mixed>}> $statements */
    private function resolveChanges(array $changes,int $index,ConflictResolver $resolver,array &$statements,int &$conflicts,Closure $complete):void{if(!isset($changes[$index])){$complete();return;}$change=$changes[$index];SQLite::query($this->database,'SELECT id,collection_name,record_id,operation_kind,payload_json,base_version,client_timestamp,attempts FROM pam_sync_outbox WHERE operation_state=1 AND collection_name=? AND record_id=? ORDER BY client_timestamp DESC LIMIT 1',[$change->collection,$change->recordIdentifier],function(array$rows)use($changes,$index,$resolver,&$statements,&$conflicts,$complete,$change):void{$local=null;if(isset($rows[0])){$r=$rows[0];$local=new SyncOperation((string)$r['id'],(string)$r['collection_name'],(string)$r['record_id'],SyncOperationKind::from((int)$r['operation_kind']),self::decode((string)$r['payload_json']),(int)$r['base_version'],(int)$r['client_timestamp'],(int)$r['attempts']);$conflicts++;}if($resolver->applyRemote($local,$change))$statements[]=['sql'=>'INSERT INTO pam_sync_records(collection_name,record_id,operation_kind,payload_json,server_version,server_timestamp) VALUES(?,?,?,?,?,?) ON CONFLICT(collection_name,record_id) DO UPDATE SET operation_kind=excluded.operation_kind,payload_json=excluded.payload_json,server_version=excluded.server_version,server_timestamp=excluded.server_timestamp WHERE excluded.server_version >= pam_sync_records.server_version','arguments'=>[$change->collection,$change->recordIdentifier,$change->kind->value,$this->json($change->payload),$change->version,$change->serverTimestampMillis]];$this->resolveChanges($changes,$index+1,$resolver,$statements,$conflicts,$complete);});}
    /** @param array<string,mixed> $value */private function json(array$value):string{return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
    /** @return array<string,mixed> */private static function decode(string$value):array{try{$decoded=json_decode($value,true,512,JSON_THROW_ON_ERROR);return is_array($decoded)?$decoded:[];}catch(JsonException){return[];}}
}
