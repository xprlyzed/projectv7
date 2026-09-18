<?php

namespace App\Console\Commands;

use App\Models\Story;
use Illuminate\Console\Command;

class PruneStories extends Command
{
    protected $signature = 'stories:prune';

    protected $description = 'Süresi dolan (24 saati geçen) hikayeleri ve medya dosyalarını siler';

    public function handle(): int
    {
        $count = Story::deleteExpired();

        $this->info("{$count} adet süresi dolan hikaye silindi.");

        return self::SUCCESS;
    }
}
