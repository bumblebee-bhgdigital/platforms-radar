<?php

namespace Platform\Radar\Tools\Concerns;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Radar\Models\RadarItem;
use Platform\Radar\Models\RadarSource;

/** Gemeinsames der radar.*-Tools: Team auflösen, Serialisierung, Metadaten. */
trait RadarTool
{
    /** @return array{team: mixed, error: ?ToolResult} */
    protected function resolveTeam(array $arguments, ToolContext $context): array
    {
        if (! $context->user) {
            return ['team' => null, 'error' => ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.')];
        }
        $teamId = ($arguments['team_id'] ?? null) ?: $context->team?->id;
        if (! $teamId) {
            return ['team' => null, 'error' => ToolResult::error('MISSING_TEAM', 'Kein Team angegeben und kein Team im Kontext. Nutze "core.teams.GET".')];
        }
        $team = $context->user->teams()->where('teams.id', (int) $teamId)->first();
        if (! $team) {
            return ['team' => null, 'error' => ToolResult::error('ACCESS_DENIED', "Kein Zugriff auf Team {$teamId}.")];
        }

        return ['team' => $team, 'error' => null];
    }

    protected function sourceToArray(RadarSource $s): array
    {
        return [
            'id' => $s->id, 'name' => $s->name, 'source_type' => $s->source_type, 'url' => $s->url,
            'topic' => $s->topic, 'cluster' => $s->cluster, 'note' => $s->note,
            'pull_interval_hours' => $s->pull_interval_hours, 'is_active' => $s->is_active,
            'last_pulled_at' => $s->last_pulled_at?->toIso8601String(), 'last_error' => $s->last_error, 'error_count' => $s->error_count,
            'items_count' => $s->items_count ?? null,
        ];
    }

    protected function itemToArray(RadarItem $i): array
    {
        return [
            'id' => $i->id, 'source_id' => $i->source_id, 'source' => $i->source?->name,
            'topic' => $i->source?->topic, 'cluster' => $i->source?->cluster,
            'title' => $i->title, 'url' => $i->url, 'excerpt' => $i->excerpt, 'author' => $i->author,
            'published_at' => $i->published_at?->toIso8601String(), 'fetched_at' => $i->fetched_at?->toIso8601String(),
        ];
    }

    protected function meta(array $tags, bool $readOnly): array
    {
        return [
            'category' => $readOnly ? 'query' : 'action',
            'tags' => array_merge(['radar', 'environment', 'feeds'], $tags),
            'read_only' => $readOnly,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => $readOnly ? 'read' : 'write',
            'idempotent' => $readOnly,
        ];
    }
}
