<?php

namespace App\Console\Commands;

use App\Services\LibraryPortalMailService;
use Illuminate\Console\Command;

class DeliverLibraryPortalMail extends Command
{
    protected $signature = 'library:deliver-mail';

    protected $description = 'Deliver admission and fee emails with retries.';

    public function handle(LibraryPortalMailService $mail): int
    {
        $this->info('Transport accepted: '.$mail->deliver());

        return self::SUCCESS;
    }
}
