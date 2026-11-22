<?php

namespace Tests\Feature;

require_once __DIR__ . '/RadarHarness.php';

use Illuminate\Support\Facades\Http;
use Platform\Radar\Models\RadarItem;
use Platform\Radar\Models\RadarSource;
use Platform\Radar\Services\FeedParser;
use Platform\Radar\Services\RadarPuller;
use Tests\TestCase;

/**
 * Radar: Parser (RSS 2.0/1.0, Atom), Abruf mit Deduplizierung und Fehlern, Fälligkeit, Tools, Oberfläche,
 * und dass Radar kein anderes Modul kennt. Aufruf: tests/run-in-taiste.sh tests/Feature/RadarTest.php
 */
class RadarTest extends TestCase
{
    use RadarHarness;

    private const RSS2 = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:content="http://purl.org/rss/1.0/modules/content/">
<channel><title>Gastro</title>
  <item><title>Mindestlohn steigt</title><link>https://ex.test/a</link><guid>a-1</guid><pubDate>Mon, 16 Nov 2026 08:00:00 +0100</pubDate><description><![CDATA[<p>Der <b>Mindestlohn</b> &amp; die Folgen</p>]]></description><dc:creator>Redaktion</dc:creator></item>
  <item><title>Neue Hygieneverordnung</title><link>https://ex.test/b</link><content:encoded><![CDATA[<p>Volltext</p>]]></content:encoded><dc:date>2026-11-18T10:00:00Z</dc:date></item>
</channel></rss>
XML;

    private const ATOM = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<feed xmlns="http://www.w3.org/2005/Atom"><title>Dev</title>
  <entry><title>Laravel 13</title><id>urn:x:1</id><link rel="self" href="https://ex.test/self"/><link rel="alternate" href="https://ex.test/l13"/><updated>2026-11-20T09:00:00Z</updated><summary>Release</summary><author><name>Taylor</name></author></entry>
</feed>
XML;

    private const RDF = <<<'XML'
<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <channel rdf:about="https://ex.test"><title>EU</title></channel>
  <item rdf:about="https://ex.test/reg"><title>AI Act Leitlinien</title><link>https://ex.test/reg</link><description>Kommission</description><dc:date>2026-11-19</dc:date></item>
</rdf:RDF>
XML;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootModule();
        $this->seedWorld();
    }

    private function source(array $attrs = []): RadarSource
    {
        return RadarSource::create($attrs + ['team_id' => $this->s['teamId'], 'name' => 'Gastro', 'url' => 'https://ex.test/rss', 'topic' => 'gastronomy', 'cluster' => 'dach']);
    }

    public function test_parser_reads_rss2_atom_and_rdf(): void
    {
        $p = new FeedParser(1000);

        $rss = $p->parse(self::RSS2);
        $this->assertCount(2, $rss);
        $this->assertSame('Mindestlohn steigt', $rss[0]['title']);
        $this->assertSame('Der Mindestlohn & die Folgen', $rss[0]['excerpt']);
        $this->assertSame('Redaktion', $rss[0]['author']);
        $this->assertSame('a-1', $rss[0]['guid']);
        $this->assertSame('2026-11-16T07:00:00+00:00', $rss[0]['published_at']->utc()->toIso8601String());
        $this->assertSame('Volltext', $rss[1]['excerpt'], 'content:encoded als Rückfall');
        $this->assertNotNull($rss[1]['published_at'], 'dc:date als Rückfall');

        $atom = $p->parse(self::ATOM);
        $this->assertSame('https://ex.test/l13', $atom[0]['url'], 'alternate vor self');
        $this->assertSame('Taylor', $atom[0]['author']);

        $rdf = $p->parse(self::RDF);
        $this->assertSame('AI Act Leitlinien', $rdf[0]['title']);

        $this->expectException(\InvalidArgumentException::class);
        $p->parse('<html><body>kein Feed</body></html>');
    }

    public function test_pull_stores_new_items_once_and_records_errors(): void
    {
        $source = $this->source();
        Http::fake(['ex.test/rss' => Http::response(self::RSS2)]);

        $r = app(RadarPuller::class)->pull($source);
        $this->assertSame(['source_id' => $source->id, 'new' => 2, 'seen' => 2, 'error' => null], $r);
        $r = app(RadarPuller::class)->pull($source->fresh());
        $this->assertSame(0, $r['new'], 'bekannte Einträge werden nicht doppelt angelegt');
        $this->assertSame(2, RadarItem::count());
        $this->assertSame($this->s['teamId'], RadarItem::first()->team_id);

        Http::fake(['ex.test/broken' => Http::response('nope', 500)]);
        $broken = $this->source(['name' => 'Kaputt', 'url' => 'https://ex.test/broken']);
        $r = app(RadarPuller::class)->pull($broken);
        $this->assertSame('HTTP 500', $r['error']);
        $broken->refresh();
        $this->assertSame(1, $broken->error_count);
        $this->assertNotNull($broken->last_pulled_at, 'kaputte Quelle wird nicht stündlich erneut angefragt');
    }

    public function test_command_pulls_only_due_active_sources(): void
    {
        Http::fake(['*' => Http::response(self::ATOM)]);
        $due = $this->source(['url' => 'https://ex.test/due']);
        $fresh = $this->source(['url' => 'https://ex.test/fresh', 'last_pulled_at' => now()->subHour(), 'pull_interval_hours' => 24]);
        $paused = $this->source(['url' => 'https://ex.test/paused', 'is_active' => false]);

        $this->artisan('radar:pull')->assertSuccessful();
        $this->assertSame(1, RadarItem::where('source_id', $due->id)->count());
        $this->assertSame(0, RadarItem::where('source_id', $fresh->id)->count());
        $this->assertSame(0, RadarItem::where('source_id', $paused->id)->count());

        $this->artisan('radar:pull', ['--source' => $fresh->id, '--force' => true])->assertSuccessful();
        $this->assertSame(1, RadarItem::where('source_id', $fresh->id)->count());
    }

    public function test_tools_manage_sources_and_read_items(): void
    {
        $post = (new \Platform\Radar\Tools\CreateSourceTool())->execute(['name' => 'EU', 'url' => 'https://ex.test/eu', 'topic' => 'regulation', 'cluster' => 'europa', 'note' => 'Neue EU-Gesetze'], $this->ctx());
        $this->assertTrue($post->success, json_encode($post->toArray()));
        $id = $post->data['source']['id'];
        $again = (new \Platform\Radar\Tools\CreateSourceTool())->execute(['name' => 'EU doppelt', 'url' => 'https://ex.test/eu'], $this->ctx());
        $this->assertFalse($again->data['created']);
        $this->assertFalse((new \Platform\Radar\Tools\CreateSourceTool())->execute(['name' => 'X', 'url' => 'ftp://x'], $this->ctx())->success);

        Http::fake(['ex.test/eu' => Http::response(self::RDF), 'ex.test/rss' => Http::response(self::RSS2)]);
        $pull = (new \Platform\Radar\Tools\PullSourceTool())->execute(['id' => $id], $this->ctx());
        $this->assertSame(1, $pull->data['new']);
        app(RadarPuller::class)->pull($this->source());

        $items = fn (array $a) => (new \Platform\Radar\Tools\ListItemsTool())->execute($a, $this->ctx())->data;
        $this->assertSame(3, $items([])['pagination']['total']);
        $this->assertSame(['AI Act Leitlinien'], array_column($items(['topic' => 'regulation'])['items'], 'title'));
        $this->assertSame(['Mindestlohn steigt'], array_column($items(['search' => 'mindestlohn'])['items'], 'title'));
        $this->assertSame(0, $items(['since' => now()->addMinute()->toIso8601String()])['pagination']['total']);
        $this->assertSame(['AI Act Leitlinien', 'Neue Hygieneverordnung', 'Mindestlohn steigt'], array_column($items([])['items'], 'title'), 'neueste zuerst');

        $put = (new \Platform\Radar\Tools\UpdateSourceTool())->execute(['id' => $id, 'is_active' => false], $this->ctx());
        $this->assertFalse($put->data['source']['is_active']);
        $list = (new \Platform\Radar\Tools\ListSourcesTool())->execute([], $this->ctx());
        $this->assertSame(2, $list->data['count']);
        $this->assertSame(1, collect($list->data['sources'])->firstWhere('id', $id)['items_count']);

        // anderes Team sieht nichts
        $foreign = (new \Platform\Radar\Tools\ListItemsTool())->execute([], $this->ctx($this->s['other']));
        $this->assertSame(0, $foreign->data['pagination']['total']);
        $denied = (new \Platform\Radar\Tools\ListItemsTool())->execute(['team_id' => $this->s['teamId']], $this->ctx($this->s['other']));
        $this->assertFalse($denied->success);

        $del = (new \Platform\Radar\Tools\DeleteSourceTool())->execute(['id' => $id], $this->ctx());
        $this->assertTrue($del->success);
        $this->assertSame(3, $items([])['pagination']['total'], 'Einträge abbestellter Quellen bleiben lesbar');
    }

    public function test_prune_removes_old_items(): void
    {
        $s = $this->source();
        RadarItem::create(['team_id' => $s->team_id, 'source_id' => $s->id, 'fingerprint' => sha1('old'), 'title' => 'alt', 'fetched_at' => now()->subDays(800)]);
        RadarItem::create(['team_id' => $s->team_id, 'source_id' => $s->id, 'fingerprint' => sha1('new'), 'title' => 'neu', 'fetched_at' => now()]);
        $this->artisan('radar:prune')->assertSuccessful();
        $this->assertSame(['neu'], RadarItem::pluck('title')->all());
    }

    public function test_pages_render_and_source_form_works(): void
    {
        $this->bootUi();
        $this->withoutExceptionHandling();
        Http::fake(['*' => Http::response(self::RSS2)]);

        $c = new \Platform\Radar\Livewire\Sources();
        $c->mount();
        $c->openCreate();
        $c->form = ['name' => 'Tageskarte', 'url' => 'https://ex.test/tk', 'topic' => 'gastronomy', 'cluster' => '', 'note' => 'Gastgewerbe DACH', 'pull_interval_hours' => 24, 'is_active' => true];
        $c->save();
        $s = RadarSource::firstWhere('name', 'Tageskarte');
        $this->assertNotNull($s);
        $this->assertNull($s->cluster);
        $c->pullNow($s->id);
        $this->assertSame('2 neue Einträge (2 im Feed).', $c->flash);

        $this->get(route('radar.sources.index'))->assertOk()->assertSee('Tageskarte')->assertSee('Gastgewerbe DACH');
        $this->get(route('radar.dashboard'))->assertOk()->assertSee('Mindestlohn steigt')->assertSee('Der Mindestlohn &amp; die Folgen', false);
        $this->get(route('radar.dashboard', ['search' => 'Hygiene']))->assertOk()->assertSee('Neue Hygieneverordnung')->assertDontSee('Mindestlohn steigt');
    }

    /** Lose Kopplung: Radar kennt kein anderes Fachmodul und ruft kein Sprachmodell auf. */
    public function test_radar_knows_no_other_module(): void
    {
        $forbidden = ['Platform\\Organization', 'Platform\\Signals', 'Platform\\Datawarehouse', 'Platform\\ManagementBook', 'organization_', 'signal_', 'anthropic', 'openai', 'claude-'];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->module . '/src'));
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $code = file_get_contents($f->getPathname());
                foreach ($forbidden as $needle) {
                    $this->assertStringNotContainsStringIgnoringCase($needle, $code, basename($f->getPathname()) . " enthält {$needle}");
                }
            }
        }
    }
}
