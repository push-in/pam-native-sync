<?php

declare(strict_types=1);

namespace Pam\Native\Sync;

/** Stable response codes shared by every compatible sync server. */
enum SyncOutcomeStatus: int
{
    case Applied = 1;
    case Rejected = 2;
    case Conflict = 3;
    case Processing = 4;
}
