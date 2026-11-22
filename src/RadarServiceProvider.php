<?php

namespace Platform\Radar;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Radar – sammelt Umwelt-Feeds roh und dedupliziert. Kennt keine anderen Module: Lesen und Bewerten
 * übernehmen Agenten und Menschen über die radar.*-Tools.
 */
class RadarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/radar.php', 'radar');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Platform\Radar\Console\Commands\PullSourcesCommand::class,
                \Platform\Radar\Console\Commands\PruneItemsCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        if (config()->has('radar.routing') && config()->has('radar.navigation') && PlatformCore::modulesTableExists()) {
            PlatformCore::registerModule([
                'key'        => 'radar',
                'title'      => 'Radar',
                'group'      => 'dev',
                'routing'    => config('radar.routing'),
                'guard'      => config('radar.guard'),
                'navigation' => config('radar.navigation'),
                'sidebar'    => config('radar.sidebar'),
            ]);
        }

        if (PlatformCore::getModule('radar')) {
            ModuleRouter::group('radar', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->publishes([__DIR__.'/../config/radar.php' => config_path('radar.php')], 'config');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'radar');

        $this->registerLivewireComponents();
        $this->registerMorphMap();
        $this->registerTools();
        $this->registerSchedule();
    }

    /** Stündlich prüfen, welche Quellen fällig sind (Intervall je Quelle); nachts alte Einträge aufräumen. */
    protected function registerSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('radar:pull')->hourlyAt(7)->withoutOverlapping();
            $schedule->command('radar:prune')->dailyAt('03:37')->withoutOverlapping();
        });
    }

    /** Morph-Aliase, damit andere Module auf Quellen und Einträge verweisen können, ohne Radar zu kennen. */
    protected function registerMorphMap(): void
    {
        Relation::morphMap([
            \Platform\Radar\Models\RadarSource::MORPH_ALIAS => \Platform\Radar\Models\RadarSource::class,
            \Platform\Radar\Models\RadarItem::MORPH_ALIAS => \Platform\Radar\Models\RadarItem::class,
        ]);
    }

    protected function registerTools(): void
    {
        try {
            $registry = resolve(\Platform\Core\Tools\ToolRegistry::class);
            $registry->register(new \Platform\Radar\Tools\ListItemsTool());
            $registry->register(new \Platform\Radar\Tools\ListSourcesTool());
            $registry->register(new \Platform\Radar\Tools\CreateSourceTool());
            $registry->register(new \Platform\Radar\Tools\UpdateSourceTool());
            $registry->register(new \Platform\Radar\Tools\DeleteSourceTool());
            $registry->register(new \Platform\Radar\Tools\PullSourceTool());
        } catch (\Throwable $e) {
            // ToolRegistry nicht verfügbar
        }
    }

    /** src/Livewire/Foo/Bar.php → radar.foo.bar */
    protected function registerLivewireComponents(): void
    {
        $basePath = __DIR__ . '/Livewire';
        if (! is_dir($basePath)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath));
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $class = 'Platform\\Radar\\Livewire\\' . str_replace(['/', '.php'], ['\\', ''], $relativePath);
            if (! class_exists($class)) {
                continue;
            }
            $alias = 'radar.' . str_replace(['\\', '/'], '.', Str::kebab(str_replace('.php', '', $relativePath)));
            Livewire::component($alias, $class);
        }
    }
}
