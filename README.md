# PAM Native Sync

An offline-first synchronization engine, not a database wrapper. It adds an idempotent outbox, ordered batches, incremental cursors, tombstones, retry budgets and deterministic conflict policies above PAM Native SQLite and any transport implementation.

```bash
pam add sync
pam doctor
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


## What installation does

`pam add sync` resolves the official compatible package, performs a non-mutating Composer preflight, updates the normal `composer.json` and `composer.lock`, refreshes generated native integration when required, and leaves the project ready for `pam doctor` validation.

Use `pam packages` to inspect availability and `pam remove sync` to uninstall the capability safely. Direct Composer commands are an advanced interoperability path; PAM is the supported application workflow.

## API guide

| API | Responsibility |
| --- | --- |
| `SyncEngine` | Queue local mutations and orchestrate push/pull synchronization. |
| `SQLiteSyncStore` | Persist outbox, cursor, replicas, attempts, and tombstones natively. |
| `SyncTransport` | Connect any authenticated server protocol. |
| `ConflictResolver` / `PolicyConflictResolver` | Apply deterministic conflict decisions. |
| `SyncRunReport` | Observe completion state, counts, and conflicts. |

All coded states, kinds, and variants are sequential integer-backed enums. Use enum cases in application code; do not depend on raw wire numbers.

## Production checklist

- Give every mutation a stable idempotency identifier.
- Keep server versions monotonic and cursor advancement transactional.
- Define retry budgets, tombstone retention, and conflict policy per domain.
- Run `pam doctor`, `pam test`, and a signed release build on every supported platform.
- Exercise denial, cancellation, backgrounding, process restart, and offline behavior before release.

## Troubleshooting

- **Operations repeat:** inspect stable identifiers and server idempotency storage.
- **Newer local data is overwritten:** verify monotonic versions and conflict resolver behavior.
- **Pull loops:** confirm the server advances and signs cursors consistently.
- **Native integration is stale:** run `pam doctor --fix`, rebuild the native host, and inspect the first reported diagnostic.

## Compatibility and support

This package targets PAM Native `0.6.x`, Android API 26+, and iOS 15+ unless a platform-specific section above states a stricter requirement. Platform SDKs, credentials, entitlements, physical hardware, and store configuration remain application responsibilities.

- [PAM documentation](https://push-in.github.io/pam-docs/introduction/)
- [PAM Native overview](https://push-in.github.io/pam-docs/native/overview/)
- [Plugin and native capability model](https://push-in.github.io/pam-docs/native/plugins/)
- [Report an issue](https://github.com/push-in/pam-native-sync/issues)

Security vulnerabilities should be reported through the repository security policy or GitHub private vulnerability reporting, not a public issue.
