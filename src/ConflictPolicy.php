<?php
declare(strict_types=1);namespace Pam\Native\Sync;enum ConflictPolicy:int{case ServerWins=1;case ClientWins=2;case LastWriteWins=3;case Custom=4;}
