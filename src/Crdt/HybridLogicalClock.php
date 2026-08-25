<?php

declare(strict_types=1);

namespace Pam\Native\Sync\Crdt;

use Closure;
use InvalidArgumentException;

final class HybridLogicalClock
{
    private int $wallTimeMillis = 0;
    private int $counter = 0;

    /** @param Closure():int|null $clock */
    public function __construct(
        private readonly string $nodeIdentifier,
        private readonly ?Closure $clock = null,
    ) {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D', $nodeIdentifier) !== 1) {
            throw new InvalidArgumentException('Logical clock node identifier is invalid.');
        }
    }

    public function tick(): LogicalTimestamp
    {
        $now = $this->now();
        if ($now > $this->wallTimeMillis) {
            $this->wallTimeMillis = $now;
            $this->counter = 0;
        } else {
            ++$this->counter;
        }
        return $this->timestamp();
    }

    public function observe(LogicalTimestamp $remote): LogicalTimestamp
    {
        $now = $this->now();
        $maximum = max($now, $this->wallTimeMillis, $remote->wallTimeMillis);
        $this->counter = match (true) {
            $maximum === $this->wallTimeMillis && $maximum === $remote->wallTimeMillis => max($this->counter, $remote->counter) + 1,
            $maximum === $this->wallTimeMillis => $this->counter + 1,
            $maximum === $remote->wallTimeMillis => $remote->counter + 1,
            default => 0,
        };
        $this->wallTimeMillis = $maximum;
        return $this->timestamp();
    }

    private function timestamp(): LogicalTimestamp
    {
        return new LogicalTimestamp($this->wallTimeMillis, $this->counter, $this->nodeIdentifier);
    }

    private function now(): int
    {
        $value = $this->clock === null ? (int) floor(microtime(true) * 1000) : ($this->clock)();
        if (!is_int($value) || $value < 0) {
            throw new \RuntimeException('Logical clock returned an invalid time.');
        }
        return $value;
    }
}
