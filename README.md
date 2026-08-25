<!-- pam:product-page:start -->
<div align="center">

# PAM Native Sync

**Offline-first writes with explicit conflicts and resumable cursors.**

Coordinate local outboxes, incremental pulls, retries, and deterministic conflict resolution without prescribing an application vertical.

[![Latest version](https://img.shields.io/packagist/v/pushinbr/pam-native-sync?style=flat-square&label=stable)](https://packagist.org/packages/pushinbr/pam-native-sync)
[![CI](https://img.shields.io/github/actions/workflow/status/push-in/pam-native-sync/ci.yml?branch=main&style=flat-square&label=CI)](https://github.com/push-in/pam-native-sync/actions)
![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?style=flat-square&logo=php&logoColor=white)
![Android](https://img.shields.io/badge/Android-API%2026%2B-3DDC84?style=flat-square&logo=android&logoColor=white)
![iOS](https://img.shields.io/badge/iOS-15%2B-000000?style=flat-square&logo=apple&logoColor=white)

**[Documentation](https://push-in.github.io/pam-docs/native/overview/) · [Quick start](#quick-start) · [What you can build](#what-you-can-build) · [PAM ecosystem](https://push-in.github.io/pam-docs/ecosystem/) · [Issues](https://github.com/push-in/pam-native-sync/issues)**

</div>

---

## Why PAM Native Sync

Coordinate local outboxes, incremental pulls, retries, and deterministic conflict resolution without prescribing an application vertical. The public API is strictly typed for PHP 8.5; expensive or frame-sensitive work stays in Rust or the platform SDK instead of crossing the application boundary every frame.

| | |
| --- | --- |
| **Best for** | A focused capability you can add to any PAM Native application |
| **Native path** | Durable outbox · Cursor protocol |
| **Application model** | Composer package + generated native integration |
| **Design rule** | Independent module; no feed, vertical, or application template bundled |

## What you can build

- Field apps that must work without signal
- Collaborative records and optimistic edits
- Incremental synchronization of large local datasets

The client is backend-agnostic. Its `HttpSyncTransport` speaks the documented
JSON protocol and does not install or reference Laravel, PAM HTTP, or any
server package:

```php
use Pam\Native\Sync\SyncEngine;
use Pam\Native\Sync\Transport\HttpSyncTransport;

$transport = new HttpSyncTransport(
    endpoint: 'https://api.example.com/sync',
    clientIdentifier: $deviceId,
    tokenProvider: fn (): ?string => $session->accessToken(),
);

$engine = new SyncEngine($store, $transport);
```

## Quick start

Already have a PAM Native project? Add only this capability:

```bash
pam composer require pushinbr/pam-native-sync
pam doctor --fix
```

New to PAM? Follow the **[five-minute PAM Native setup](https://push-in.github.io/pam-docs/native/overview/)** once, then return here. Your application stays a normal Composer project with a committed lockfile.
<!-- pam:product-page:end -->

An offline-first synchronization engine, not a database wrapper. It adds an idempotent outbox, ordered batches, incremental cursors, tombstones, retry budgets and deterministic conflict policies above PAM Native SQLite and any transport implementation.

## See it in action

```php
$engine = new Pam\Native\Sync\SyncEngine(
    new Pam\Native\Sync\Storage\SQLiteSyncStore(),
    $transport,
    new Pam\Native\Sync\PolicyConflictResolver(Pam\Native\Sync\ConflictPolicy::LastWriteWins),
);
$engine->upsert('todos', 'todo-42', ['title' => 'Ship'], 7, fn ($operation) => null);
$engine->synchronize(fn (Pam\Native\Sync\SyncRunReport $report) => null);
```

`SyncTransport` is provider-neutral. The built-in HTTP adapter works with
Laravel, PAM HTTP, or any other compatible server without adding a backend
package to the app. SQLite writes are prepared and remote changes plus cursor
advancement are committed in one native transaction. Server versions are
monotonic and stale pulls cannot overwrite newer replicas.

## Local-first CRDTs

For collaboration that must converge before a server answers, the package also
ships backend-free primitives: `HybridLogicalClock`, `LogicalTimestamp`, and
`LastWriteWinsMap`. Clocks remain monotonic when wall clocks move backwards;
map snapshots retain tombstones and merge deterministically across replicas.

```php
$clock = new Pam\Native\Sync\Crdt\HybridLogicalClock($deviceId);
$document = new Pam\Native\Sync\Crdt\LastWriteWinsMap();
$document->assign('title', 'Offline edit', $clock->tick());

// Exchange snapshots through any transport, then merge in either order.
$remote = Pam\Native\Sync\Crdt\LastWriteWinsMap::restore($payload);
$document->merge($remote);
```

CRDTs do not require `pam-native-sync-laravel`, PAM HTTP, or a specific cloud.
Persistence and transport remain explicit application choices.

Platform support: Android API 26+, iOS 15+, PAM Native 0.8.x.
