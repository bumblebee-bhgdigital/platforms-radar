<?php

namespace Platform\Radar\Console\Commands;

use Illuminate\Console\Command;
use Platform\Radar\Models\RadarSource;
use Platform\Radar\Services\RadarPuller;

class PullSourcesCommand extends Command
{
    protected $signature = 'radar:pull {--source= : nur diese Quelle (ID)} {--force : auch wenn sie noch nicht fällig ist}';

    protected $description = 'Holt fällige Radar-Quellen ab und speichert neue Einträge';

    public function handle(RadarPuller $puller): int
    {
        $q = RadarSource::query()->active();
        if ($this->option('source')) {
            $q->whereKey((int) $this->option('source'));
        }

        $pulled = 0;
        foreach ($q->get() as $source) {
            if (! $this->option('force') && ! $source->isDue()) {
                continue;
            }
            $r = $puller->pull($source);
            $pulled++;
            $r['error']
                ? $this->warn("#{$source->id} {$source->name}: {$r['error']}")
                : $this->line("#{$source->id} {$source->name}: {$r['new']} neu ({$r['seen']} im Feed)");
        }
        $this->info("{$pulled} Quelle(n) abgerufen.");

        return self::SUCCESS;
    }
}
