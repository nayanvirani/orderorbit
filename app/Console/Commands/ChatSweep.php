<?php

namespace App\Console\Commands;

use App\Services\Support\LiveChat;
use Illuminate\Console\Command;

class ChatSweep extends Command
{
    protected $signature = 'orderorbit:chat-sweep';

    protected $description = 'Live chat: email the team about chats waiting too long and close answered chats that went quiet';

    public function handle(LiveChat $chat): int
    {
        $done = $chat->sweep();
        $this->info("Alerted {$done['alerted']}, closed {$done['closed']}.");

        return self::SUCCESS;
    }
}
