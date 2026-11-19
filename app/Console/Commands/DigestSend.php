<?php

namespace App\Console\Commands;

use App\Services\Notifications\DigestSender;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DigestSend extends Command
{
    protected $signature = 'digest:send';

    protected $description = 'Send the hourly and daily digests of held notification emails.';

    public function handle(DigestSender $sender): int
    {
        $r = $sender->run(Carbon::now('UTC'));
        $this->line("digests sent {$r['sent']}, dropped {$r['dropped']}");

        return self::SUCCESS;
    }
}
