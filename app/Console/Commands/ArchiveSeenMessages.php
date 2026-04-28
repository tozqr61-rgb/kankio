<?php

namespace App\Console\Commands;

use App\Models\Message;
use Illuminate\Console\Command;

class ArchiveSeenMessages extends Command
{
    protected $signature = 'messages:archive';
    protected $description = 'Archive old messages and purge stale system messages';

    public function handle(): int
    {
        /* 1) Delete music system messages older than 10 minutes */
        $deletedSystem = Message::where('is_system_message', true)
            ->where('created_at', '<', now()->subMinutes(10))
            ->delete();

        /* 2) Archive non-system messages older than 2 hours */
        $archived = Message::where('is_archived', false)
            ->where('is_system_message', false)
            ->where('created_at', '<', now()->subHours(2))
            ->update(['is_archived' => true]);

        $this->info("Deleted {$deletedSystem} system messages, archived {$archived} messages.");
        return 0;
    }
}
