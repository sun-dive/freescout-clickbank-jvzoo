<?php

namespace Modules\ClickBankJVZoo\Console;

use Illuminate\Console\Command;
use Modules\ClickBankJVZoo\Entities\Notice;

class SendNotices extends Command
{
    protected $signature = 'clickbankjvzoo:send-notices';

    protected $description = "Tell the member site about used codes whose orders changed status";

    public function handle()
    {
        $this->line('sent: '.Notice::sendDue());
    }
}
