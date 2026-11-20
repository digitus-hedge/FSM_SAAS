<?php

namespace App\Console\Commands;

use App\Services\Platform\Impersonation;
use Illuminate\Console\Command;

class ImpersonationExpire extends Command
{
    protected $signature = 'impersonation:expire';

    protected $description = 'Close support sessions whose time is up and that nobody ended (writes the end rows of the audit). Runs every minute.';

    public function handle(Impersonation $impersonation): int
    {
        $n = $impersonation->expireStale();
        if ($n) {
            $this->line("{$n} support session(s) closed.");
        }

        return self::SUCCESS;
    }
}
