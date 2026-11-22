<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;

/**
 * Setup für die Radar-Tests (sqlite :memory: in der lokalen taiste-Kopie). Lädt das Modul aus RADAR_MODULE_PATH,
 * spielt nur Core- und Radar-Migrationen ein – Radar braucht kein anderes Modul.
 */
trait RadarHarness
{
    protected string $module;
    protected array $s = [];

    protected function bootModule(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->module = getenv('RADAR_MODULE_PATH') ?: dirname(__DIR__, 2);
        $module = $this->module;
        spl_autoload_register(function ($c) use ($module) {
            $p = 'Platform\\Radar\\';
            if (str_starts_with($c, $p)) {
                $f = $module . '/src/' . str_replace('\\', '/', substr($c, strlen($p))) . '.php';
                if (is_file($f)) {
                    require $f;
                }
            }
        });

        $files = array_merge(
            glob(base_path('vendor/martin3r/platform-core/database/migrations/*.php')),
            glob($module . '/database/migrations/*.php'),
        );
        usort($files, fn ($a, $b) => strcmp(basename($a), basename($b)));
        foreach ($files as $f) {
            try {
                (require $f)->up();
            } catch (\Throwable $e) {
                // Core-Migrationen, die auf sqlite nicht laufen, ignorieren
            }
        }
        $this->app->register(\Platform\Radar\RadarServiceProvider::class);
        $this->app['config']->set('radar', require $module . '/config/radar.php');
    }

    protected function seedWorld(): void
    {
        $U = \Platform\Core\Models\User::class;
        $user = $U::create(['name' => 'Martin', 'email' => 'm@x.test', 'password' => 'x']);
        $other = $U::create(['name' => 'Fremd', 'email' => 'f@x.test', 'password' => 'x']);
        $team = DB::table('teams')->insertGetId(['name' => 'T1', 'user_id' => $user->id, 'personal_team' => false, 'created_at' => now(), 'updated_at' => now()]);
        $team2 = DB::table('teams')->insertGetId(['name' => 'T2', 'user_id' => $other->id, 'personal_team' => false, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('team_user')->insert(['team_id' => $team, 'user_id' => $user->id, 'role' => 'owner', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('team_user')->insert(['team_id' => $team2, 'user_id' => $other->id, 'role' => 'owner', 'created_at' => now(), 'updated_at' => now()]);
        $user->forceFill(['current_team_id' => $team])->save();
        $other->forceFill(['current_team_id' => $team2])->save();
        $this->actingAs($user);
        $this->s = ['user' => $user->fresh(), 'other' => $other->fresh(), 'team' => \Platform\Core\Models\Team::find($team), 'teamId' => $team, 'team2' => $team2];
    }

    protected function ctx($user = null): \Platform\Core\Contracts\ToolContext
    {
        $user ??= $this->s['user'];

        return new \Platform\Core\Contracts\ToolContext($user, $user->currentTeam);
    }

    protected function bootUi(): void
    {
        $provider = $this->app->getProvider(\Platform\Radar\RadarServiceProvider::class);
        $m = new \ReflectionMethod($provider, 'registerLivewireComponents');
        $m->setAccessible(true);
        $m->invoke($provider);
        if (! \Illuminate\Support\Facades\Route::has('radar.dashboard')) {
            \Illuminate\Support\Facades\Route::middleware('web')->prefix('radar')->group($this->module . '/routes/web.php');
        }
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->app['router']->getRoutes()->refreshActionLookups();
        $this->app['view']->addNamespace('radar', $this->module . '/resources/views');
        $layoutDir = sys_get_temp_dir() . '/radar-ui-layout';
        @mkdir($layoutDir . '/layouts', 0777, true);
        file_put_contents($layoutDir . '/layouts/app.blade.php', '<html><body>{{ $slot }}</body></html>');
        $this->app['view']->prependNamespace('platform', $layoutDir);
    }
}
