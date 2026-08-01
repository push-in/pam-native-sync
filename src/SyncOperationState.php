<?php
declare(strict_types=1);namespace Pam\Native\Sync;enum SyncOperationState:int{case Pending=1;case InFlight=2;case Acknowledged=3;case Failed=4;}
