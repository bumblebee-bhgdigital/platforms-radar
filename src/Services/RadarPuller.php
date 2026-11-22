<?php

namespace Platform\Radar\Services;

use Illuminate\Support\Facades\Http;
use Platform\Radar\Models\RadarItem;
use Platform\Radar\Models\RadarSource;

/** Holt eine Quelle ab und legt neue Einträge an. Bereits bekannte Einträge (gleicher Fingerabdruck) bleiben unverändert. */
class RadarPuller
{
    /** @return array{source_id:int,new:int,seen:int,error:?string} */
    public function pull(RadarSource $source): array
    {
        $now = now();
        try {
            $response = Http::timeout((int) config('radar.fetch.timeout', 30))
                ->withHeaders(['User-Agent' => (string) config('radar.fetch.user_agent'), 'Accept' => 'application/rss+xml, application/atom+xml, application/xml, text/xml, */*'])
                ->get($source->url);
            if (! $response->successful()) {
                throw new \RuntimeException('HTTP ' . $response->status());
            }

            $parsed = (new FeedParser((int) config('radar.fetch.excerpt_length', 1000)))->parse($response->body());
            $parsed = array_slice($parsed, 0, (int) config('radar.fetch.max_items', 100));

            $byFingerprint = [];
            foreach ($parsed as $item) {
                $byFingerprint[FeedParser::fingerprint($item)] = $item;
            }
            $known = RadarItem::query()->where('source_id', $source->id)
                ->whereIn('fingerprint', array_keys($byFingerprint))->pluck('fingerprint')->all();

            $new = 0;
            foreach (array_diff_key($byFingerprint, array_flip($known)) as $fingerprint => $item) {
                RadarItem::create([
                    'team_id' => $source->team_id,
                    'source_id' => $source->id,
                    'fingerprint' => $fingerprint,
                    'title' => $item['title'] !== '' ? $item['title'] : ($item['url'] ?? '(ohne Titel)'),
                    'url' => $item['url'],
                    'excerpt' => $item['excerpt'],
                    'author' => $item['author'],
                    'published_at' => $item['published_at'],
                    'fetched_at' => $now,
                ]);
                $new++;
            }

            $source->forceFill(['last_pulled_at' => $now, 'last_error' => null, 'error_count' => 0])->save();

            return ['source_id' => $source->id, 'new' => $new, 'seen' => count($byFingerprint), 'error' => null];
        } catch (\Throwable $e) {
            $message = mb_substr($e->getMessage(), 0, 1000);
            // Auch bei Fehler den Abrufzeitpunkt setzen, damit eine kaputte Quelle nicht jede Stunde erneut anfragt.
            $source->forceFill(['last_pulled_at' => $now, 'last_error' => $message, 'error_count' => $source->error_count + 1])->save();

            return ['source_id' => $source->id, 'new' => 0, 'seen' => 0, 'error' => $message];
        }
    }
}
