<?php

namespace Platform\Radar\Livewire;

use Livewire\Component;
use Platform\Radar\Models\RadarSource;

class Sidebar extends Component
{
    public function render()
    {
        $teamId = auth()->user()?->currentTeam?->id;
        $topics = $teamId
            ? RadarSource::query()->where('team_id', $teamId)->active()->whereNotNull('topic')
                ->selectRaw('topic, count(*) as n')->groupBy('topic')->orderBy('topic')->pluck('n', 'topic')
            : collect();
        $failing = $teamId ? RadarSource::query()->where('team_id', $teamId)->active()->whereNotNull('last_error')->count() : 0;

        return view('radar::livewire.sidebar', compact('topics', 'failing'));
    }
}
