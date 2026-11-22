<?php

namespace Platform\Radar\Console\Commands;

use Illuminate\Console\Command;
use Platform\Radar\Models\RadarItem;

class PruneItemsCommand extends Command
{
    protected $signature = 'radar:prune';

    protected $description = 'Löscht Radar-Einträge, die älter als radar.retention_days sind';

    public function handle(): int
    {
        $days = (int) config('radar.retention_days', 730);
        if ($days <= 0) {
            $this->info('Aufbewahrung unbegrenzt – nichts gelöscht.');

            return self::SUCCESS;
        }
        $deleted = RadarItem::query()->where('fetched_at', '<', now()->subDays($days))->delete();
        $this->info("{$deleted} Einträge gelöscht.");

        return self::SUCCESS;
    }
}
