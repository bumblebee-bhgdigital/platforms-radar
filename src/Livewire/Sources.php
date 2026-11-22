<?php

namespace Platform\Radar\Livewire;

use Livewire\Component;
use Platform\Radar\Models\RadarSource;
use Platform\Radar\Services\RadarPuller;

/** Abonnierte Quellen pflegen: anlegen, ändern, pausieren, sofort abrufen, abbestellen. */
class Sources extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public ?string $flash = null;

    public function mount(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->form = ['name' => '', 'url' => '', 'topic' => '', 'cluster' => '', 'note' => '', 'pull_interval_hours' => 24, 'is_active' => true];
    }

    private function source(int $id): RadarSource
    {
        return RadarSource::query()->where('team_id', auth()->user()->currentTeam->id)->findOrFail($id);
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $s = $this->source($id);
        $this->editingId = $s->id;
        $this->form = $s->only(['name', 'url', 'topic', 'cluster', 'note', 'pull_interval_hours', 'is_active']);
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'form.name' => 'required|string|max:255',
            'form.url' => ['required', 'string', 'max:2048', 'regex:#^https?://#i'],
            'form.topic' => 'nullable|string|max:50',
            'form.cluster' => 'nullable|string|max:50',
            'form.note' => 'nullable|string',
            'form.pull_interval_hours' => 'required|integer|min:1',
        ]);
        $data = $this->form;
        foreach (['topic', 'cluster', 'note'] as $f) {
            $data[$f] = trim((string) ($data[$f] ?? '')) === '' ? null : trim($data[$f]);
        }
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        if ($this->editingId) {
            $this->source($this->editingId)->update($data);
        } else {
            RadarSource::create($data + ['team_id' => auth()->user()->currentTeam->id, 'user_id' => auth()->id(), 'source_type' => 'rss']);
        }
        $this->showForm = false;
    }

    public function toggle(int $id): void
    {
        $s = $this->source($id);
        $s->update(['is_active' => ! $s->is_active]);
    }

    public function pullNow(int $id): void
    {
        $r = resolve(RadarPuller::class)->pull($this->source($id));
        $this->flash = $r['error'] ? "Abruf fehlgeschlagen: {$r['error']}" : "{$r['new']} neue Einträge ({$r['seen']} im Feed).";
    }

    public function delete(int $id): void
    {
        $this->source($id)->delete();
        $this->showForm = false;
    }

    public function render()
    {
        $sources = RadarSource::query()->where('team_id', auth()->user()?->currentTeam?->id)
            ->withCount('items')->orderBy('cluster')->orderBy('name')->get();

        return view('radar::livewire.sources', ['sources' => $sources])->layout('platform::layouts.app');
    }
}
