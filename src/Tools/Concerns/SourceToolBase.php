<?php

namespace Platform\Radar\Tools\Concerns;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Radar\Models\RadarSource;
use Platform\Radar\Services\RadarPuller;

/**
 * radar.sources.GET | POST | PUT | DELETE und radar.sources.pull.POST. Die konkreten Tools setzen nur das Verb
 * (der ToolLoader instanziiert ohne Argumente).
 */
abstract class SourceToolBase implements ToolContract, ToolMetadataContract
{
    use RadarTool;

    protected string $verb = 'GET';

    private const FIELDS = ['name', 'source_type', 'url', 'topic', 'cluster', 'note', 'pull_interval_hours', 'is_active'];

    public function getName(): string
    {
        return $this->verb === 'PULL' ? 'radar.sources.pull.POST' : 'radar.sources.' . $this->verb;
    }

    public function getDescription(): string
    {
        return match ($this->verb) {
            'GET' => 'GET /radar/sources - Abonnierte Umwelt-Quellen (RSS/Atom) mit Thema (topic), Cluster, Notiz (wofür die Quelle da ist), Intervall, letztem Abruf und Fehler. Filter: topic, cluster, is_active, id.',
            'POST' => 'POST /radar/sources - Quelle abonnieren. Pflicht: name, url. Optional: source_type (rss), topic, cluster, note, pull_interval_hours (Standard 24), is_active. Gleiche URL im Team → bestehende Quelle wird zurückgegeben.',
            'PUT' => 'PUT /radar/sources/{id} - Quelle ändern (name, url, topic, cluster, note, pull_interval_hours, is_active).',
            'DELETE' => 'DELETE /radar/sources/{id} - Quelle abbestellen (Soft-Delete, Einträge bleiben lesbar).',
            'PULL' => 'POST /radar/sources/{id}/pull - Quelle sofort abrufen (unabhängig vom Intervall). Liefert Anzahl neuer Einträge oder den Fehler.',
        };
    }

    public function getSchema(): array
    {
        $fields = [
            'name' => ['type' => 'string'],
            'source_type' => ['type' => 'string', 'enum' => RadarSource::TYPES],
            'url' => ['type' => 'string'],
            'topic' => ['type' => 'string'],
            'cluster' => ['type' => 'string'],
            'note' => ['type' => 'string'],
            'pull_interval_hours' => ['type' => 'integer'],
            'is_active' => ['type' => 'boolean'],
            'team_id' => ['type' => 'integer'],
        ];

        return match ($this->verb) {
            'GET' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'topic' => $fields['topic'], 'cluster' => $fields['cluster'], 'is_active' => $fields['is_active'], 'team_id' => $fields['team_id']]],
            'POST' => ['type' => 'object', 'properties' => $fields, 'required' => ['name', 'url']],
            'PUT' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']] + $fields, 'required' => ['id']],
            'DELETE', 'PULL' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'team_id' => $fields['team_id']], 'required' => ['id']],
        };
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            $q = RadarSource::query()->where('team_id', $team->id)->withCount('items');

            if ($this->verb === 'GET') {
                if (! empty($arguments['id'])) {
                    $s = $q->find((int) $arguments['id']);

                    return $s ? ToolResult::success(['source' => $this->sourceToArray($s)]) : ToolResult::error('NOT_FOUND', 'Quelle nicht gefunden.');
                }
                foreach (['topic', 'cluster'] as $f) {
                    if (! empty($arguments[$f])) {
                        $q->where($f, $arguments[$f]);
                    }
                }
                if (array_key_exists('is_active', $arguments) && $arguments['is_active'] !== null) {
                    $q->where('is_active', (bool) $arguments['is_active']);
                }
                $rows = $q->orderBy('cluster')->orderBy('name')->get();

                return ToolResult::success(['sources' => $rows->map(fn ($s) => $this->sourceToArray($s))->all(), 'count' => $rows->count()]);
            }

            if ($this->verb === 'POST') {
                $data = $this->validated($arguments, true);
                if (is_string($data)) {
                    return ToolResult::error('VALIDATION_ERROR', $data);
                }
                $existing = RadarSource::query()->where('team_id', $team->id)->where('url', $data['url'])->first();
                if ($existing) {
                    return ToolResult::success(['source' => $this->sourceToArray($existing), 'created' => false, 'message' => "Quelle mit dieser URL existiert bereits (#{$existing->id})."]);
                }
                $s = RadarSource::create($data + ['team_id' => $team->id, 'user_id' => $context->user->id]);

                return ToolResult::success(['source' => $this->sourceToArray($s), 'created' => true]);
            }

            $s = $q->find((int) ($arguments['id'] ?? 0));
            if (! $s) {
                return ToolResult::error('NOT_FOUND', 'Quelle nicht gefunden.');
            }

            if ($this->verb === 'DELETE') {
                $s->delete();

                return ToolResult::success(['id' => $s->id, 'message' => 'Quelle abbestellt.']);
            }

            if ($this->verb === 'PULL') {
                return ToolResult::success(resolve(RadarPuller::class)->pull($s));
            }

            $data = $this->validated($arguments, false);
            if (is_string($data)) {
                return ToolResult::error('VALIDATION_ERROR', $data);
            }
            $s->update($data);

            return ToolResult::success(['source' => $this->sourceToArray($s->fresh()->loadCount('items'))]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    /** @return array<string,mixed>|string Fehlermeldung als String */
    private function validated(array $arguments, bool $create): array|string
    {
        $data = array_intersect_key($arguments, array_flip(self::FIELDS));
        if ($create && (trim((string) ($data['name'] ?? '')) === '' || trim((string) ($data['url'] ?? '')) === '')) {
            return 'name und url sind erforderlich.';
        }
        if (isset($data['url']) && ! preg_match('#^https?://#i', (string) $data['url'])) {
            return 'url muss mit http:// oder https:// beginnen.';
        }
        if (isset($data['source_type']) && ! in_array($data['source_type'], RadarSource::TYPES, true)) {
            return 'source_type muss einer von ' . implode(', ', RadarSource::TYPES) . ' sein.';
        }
        if (isset($data['pull_interval_hours']) && (int) $data['pull_interval_hours'] < 1) {
            return 'pull_interval_hours muss mindestens 1 sein.';
        }

        return $data;
    }

    public function getMetadata(): array
    {
        return $this->meta(['sources', 'rss', strtolower($this->verb)], $this->verb === 'GET');
    }
}
