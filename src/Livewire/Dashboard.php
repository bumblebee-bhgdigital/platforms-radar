<?php

namespace Platform\Radar\Livewire;

use Livewire\Attributes\Url;
use Livewire\Component;
use Platform\Radar\Models\RadarItem;
use Platform\Radar\Models\RadarSource;

/** Einträge aller Quellen, neueste zuerst. Nur lesen – Radar bewertet nichts. */
class Dashboard extends Component
{
    #[Url]
    public string $topic = '';

    #[Url]
    public string $source = '';

    #[Url]
    public string $search = '';

    public int $limit = 50;

    public function updated($name): void
    {
        if (in_array($name, ['topic', 'source', 'search'], true)) {
            $this->limit = 50;
        }
    }

    public function more(): void
    {
        $this->limit += 50;
    }

    public function render()
    {
        $teamId = auth()->user()?->currentTeam?->id;
        $q = RadarItem::query()->with('source')->where('team_id', $teamId);
        if ($this->source !== '') {
            $q->where('source_id', (int) $this->source);
        }
        if ($this->topic !== '') {
            $q->whereHas('source', fn ($s) => $s->where('topic', $this->topic));
        }
        if (trim($this->search) !== '') {
            $term = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($this->search)) . '%';
            $q->where(fn ($w) => $w->where('title', 'like', $term)->orWhere('excerpt', 'like', $term));
        }
        $total = (clone $q)->count();
        $items = $q->orderByRaw('COALESCE(published_at, fetched_at) DESC')->orderByDesc('id')->take($this->limit)->get();

        $sources = RadarSource::query()->where('team_id', $teamId)->orderBy('name')->get(['id', 'name', 'topic']);

        return view('radar::livewire.dashboard', [
            'items' => $items,
            'total' => $total,
            'stats' => [
                'sources' => $sources->count(),
                'items' => RadarItem::query()->where('team_id', $teamId)->count(),
                'today' => RadarItem::query()->where('team_id', $teamId)->where('fetched_at', '>=', now()->startOfDay())->count(),
                'failing' => RadarSource::query()->where('team_id', $teamId)->active()->whereNotNull('last_error')->count(),
            ],
            'sourceOptions' => $sources->map(fn ($s) => ['value' => (string) $s->id, 'label' => $s->name])->all(),
            'topicOptions' => $sources->pluck('topic')->filter()->unique()->sort()->map(fn ($t) => ['value' => $t, 'label' => $t])->values()->all(),
        ])->layout('platform::layouts.app');
    }
}
