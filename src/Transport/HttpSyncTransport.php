<?php

declare(strict_types=1);

namespace Pam\Native\Sync\Transport;

use Closure;
use JsonException;
use Pam\Native\Http\Http;
use Pam\Native\Http\HttpResponse;
use Pam\Native\Sync\Contracts\SyncTransport;
use Pam\Native\Sync\RemoteChange;
use Pam\Native\Sync\SyncOperation;
use Pam\Native\Sync\SyncOperationKind;
use Pam\Native\Sync\SyncOutcomeStatus;
use Pam\Native\Sync\SyncPullResult;
use Pam\Native\Sync\SyncPushResult;
use Throwable;

/**
 * Protocol-only HTTP adapter. It works with Laravel, PAM HTTP, or any server
 * that implements the documented JSON contract and has no server dependency.
 */
final readonly class HttpSyncTransport implements SyncTransport
{
    /** @param Closure():?string $tokenProvider */
    public function __construct(
        private string $endpoint,
        private string $clientIdentifier,
        private Closure $tokenProvider,
        private int $timeoutMs = 30_000,
    ) {
        if ($endpoint === '' || $clientIdentifier === '' || $timeoutMs < 1) {
            throw new \InvalidArgumentException('Sync transport configuration is invalid.');
        }
    }

    public function push(array $operations, Closure $complete): void
    {
        $payload = array_map(
            static fn (SyncOperation $operation): array => $operation->jsonSerialize(),
            $operations,
        );

        $this->request($payload, '', 1, static function (?array $data, ?string $error) use ($complete): void {
            if ($error !== null) {
                $complete(new SyncPushResult([], [], 0, $error));

                return;
            }

            $acknowledged = [];
            $rejected = [];
            $conflicts = 0;

            foreach ((array) ($data['outcomes'] ?? []) as $outcome) {
                if (!is_array($outcome)) {
                    continue;
                }
                $identifier = (string) ($outcome['id'] ?? '');
                $status = SyncOutcomeStatus::tryFrom((int) ($outcome['status'] ?? 0));
                if ($identifier === '' || $status === null) {
                    continue;
                }
                if ($status === SyncOutcomeStatus::Applied) {
                    $acknowledged[] = $identifier;
                } elseif ($status === SyncOutcomeStatus::Conflict) {
                    $acknowledged[] = $identifier;
                    ++$conflicts;
                } elseif ($status === SyncOutcomeStatus::Rejected) {
                    $rejected[$identifier] = (string) ($outcome['message'] ?? 'Rejected by sync server.');
                }
            }

            $complete(new SyncPushResult($acknowledged, $rejected, $conflicts));
        });
    }

    public function pull(string $cursor, int $limit, Closure $complete): void
    {
        $this->request([], $cursor, $limit, static function (?array $data, ?string $error) use ($complete, $cursor): void {
            if ($error !== null) {
                $complete(new SyncPullResult($cursor, [], false, $error));

                return;
            }

            try {
                $changes = array_map(
                    static fn (array $change): RemoteChange => new RemoteChange(
                        (string) $change['collection'],
                        (string) $change['recordId'],
                        SyncOperationKind::from((int) $change['kind']),
                        (array) $change['payload'],
                        (int) $change['version'],
                        (int) $change['serverTimestampMillis'],
                    ),
                    (array) ($data['changes'] ?? []),
                );
                $complete(new SyncPullResult(
                    (string) ($data['cursor'] ?? ''),
                    $changes,
                    (bool) ($data['hasMore'] ?? false),
                ));
            } catch (Throwable $exception) {
                $complete(new SyncPullResult(
                    $cursor,
                    [],
                    false,
                    'Invalid sync response: '.$exception->getMessage(),
                ));
            }
        });
    }

    /**
     * @param list<array<string, mixed>> $operations
     * @param Closure(?array<string, mixed>, ?string):void $complete
     */
    private function request(
        array $operations,
        string $cursor,
        int $limit,
        Closure $complete,
    ): void {
        $token = ($this->tokenProvider)();
        $headers = [];
        if (is_string($token) && $token !== '') {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        Http::json(
            'POST',
            $this->endpoint,
            [
                'clientId' => $this->clientIdentifier,
                'cursor' => $cursor === '' ? null : $cursor,
                'limit' => $limit,
                'operations' => $operations,
            ],
            static function (HttpResponse $response) use ($complete): void {
                if (!$response->successful()) {
                    $message = $response->transportFailed()
                        ? $response->error
                        : "Sync server returned HTTP {$response->statusCode}.";
                    $complete(null, $message);

                    return;
                }

                try {
                    $data = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
                    if (!is_array($data)) {
                        throw new JsonException('Expected a JSON object.');
                    }
                    $complete($data, null);
                } catch (JsonException $exception) {
                    $complete(null, 'Invalid sync JSON: '.$exception->getMessage());
                }
            },
            $headers,
            $this->timeoutMs,
        );
    }
}
