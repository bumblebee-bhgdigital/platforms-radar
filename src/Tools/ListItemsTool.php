<?php

namespace Platform\Radar\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Radar\Models\RadarItem;
use Platform\Radar\Tools\Concerns\RadarTool;

class ListItemsTool implements ToolContract, ToolMetadataContract
{
    use RadarTool;

    public function getName(): string
    {
        return 'radar.items.GET';
    }

    public function getDescription(): string
    {
        return 'GET /radar/items - Einträge aus den abonnierten Umwelt-Feeds (Branche, Markt, Regulierung, Technik, Makro …), roh: Titel, Link, Auszug, Datum. '
            . 'Neueste zuerst. Filter: source_id, topic, cluster, search (Titel/Auszug), since (abgerufen ab, ISO-Datum – „was ist neu seit …“), published_after, published_before. '
            . 'Radar bewertet nichts – Relevanz entscheidest du. Quellen und ihre Themen: radar.sources.GET.';
    }

    public function getSchema(): array
    {
        return ['type' => 'object', 'properties' => [
            'id' => ['type' => 'integer'],
            'source_id' => ['type' => 'integer'],
            'topic' => ['type' => 'string'],
            'cluster' => ['type' => 'string'],
            'search' => ['type' => 'string'],
            'since' => ['type' => 'string', 'description' => 'abgerufen ab (ISO-Datum/Zeit)'],
            'published_after' => ['type' => 'string'],
            'published_before' => ['type' => 'string'],
            'limit' => ['type' => 'integer'],
            'offset' => ['type' => 'integer'],
            'team_id' => ['type' => 'integer'],
        ]];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            $q = RadarItem::query()->with('source')->where('team_id', $team->id);

            if (! empty($arguments['id'])) {
                $i = $q->find((int) $arguments['id']);

                return $i ? ToolResult::success(['item' => $this->itemToArray($i)]) : ToolResult::error('NOT_FOUND', 'Eintrag nicht gefunden.');
            }
            if (! empty($arguments['source_id'])) {
                $q->where('source_id', (int) $arguments['source_id']);
            }
            foreach (['topic', 'cluster'] as $f) {
                if (! empty($arguments[$f])) {
                    $q->whereHas('source', fn ($s) => $s->where($f, $arguments[$f]));
                }
            }
            if (! empty($arguments['search'])) {
                $term = '%' . str_replace(['%', '_'], ['\%', '\_'], $arguments['search']) . '%';
                $q->where(fn ($w) => $w->where('title', 'like', $term)->orWhere('excerpt', 'like', $term));
            }
            if (! empty($arguments['since'])) {
                $q->where('fetched_at', '>=', \Carbon\Carbon::parse($arguments['since']));
            }
            if (! empty($arguments['published_after'])) {
                $q->where('published_at', '>=', \Carbon\Carbon::parse($arguments['published_after']));
            }
            if (! empty($arguments['published_before'])) {
                $q->where('published_at', '<=', \Carbon\Carbon::parse($arguments['published_before']));
            }

            $total = (clone $q)->count();
            $limit = min(200, max(1, (int) ($arguments['limit'] ?? 50)));
            $offset = max(0, (int) ($arguments['offset'] ?? 0));
            $rows = $q->orderByRaw('COALESCE(published_at, fetched_at) DESC')->orderByDesc('id')->skip($offset)->take($limit)->get();

            return ToolResult::success([
                'items' => $rows->map(fn ($i) => $this->itemToArray($i))->all(),
                'pagination' => ['limit' => $limit, 'offset' => $offset, 'total' => $total, 'has_more' => $offset + $rows->count() < $total],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return $this->meta(['items', 'news', 'rss', 'read'], true);
    }
}
