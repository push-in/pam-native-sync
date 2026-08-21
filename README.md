# PAM Native Sync

An offline-first synchronization engine, not a database wrapper. It adds an idempotent outbox, ordered batches, incremental cursors, tombstones, retry budgets and deterministic conflict policies above PAM Native SQLite and any transport implementation.

## Start here

Install the PAM Runtime and create a PAM Native project before adding Sync:

```bash
curl --proto '=https' --proto-redir '=https' --tlsv1.2 \
    --connect-timeout 15 --max-time 60 --max-filesize 1048576 -fsSL \
    https://github.com/push-in/pam/releases/latest/download/install.sh | sh

pam init my-app --template native
cd my-app
pam composer require pushinbr/pam-native-sync
pam doctor --fix
```

```php
$engine = new Pam\Native\Sync\SyncEngine(
    new Pam\Native\Sync\Storage\SQLiteSyncStore(),
    $transport,
    new Pam\Native\Sync\PolicyConflictResolver(Pam\Native\Sync\ConflictPolicy::LastWriteWins),
);
$engine->upsert('todos', 'todo-42', ['title' => 'Ship'], 7, fn ($operation) => null);
$engine->synchronize(fn (Pam\Native\Sync\SyncRunReport $report) => null);
```

`SyncTransport` is provider-neutral. HTTP, Realtime and Laravel adapters remain separate packages. SQLite writes are prepared and remote changes plus cursor advancement are committed in one native transaction. Server versions are monotonic and stale pulls cannot overwrite newer replicas.

Platform support: Android API 26+, iOS 15+, PAM Native 0.6.x.
