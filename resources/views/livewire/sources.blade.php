{{-- Radar-Quellen (nx). Register-artig → Tabelle. --}}
<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Quellen" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[['label' => 'Radar', 'icon' => 'signal', 'href' => route('radar.dashboard')], ['label' => 'Quellen']]">
            <x-nx-button variant="primary" size="sm" wire:click="openCreate">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Quelle</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained" spacing="space-y-6">
        @if($flash)
            <x-nx-callout variant="info" icon="heroicon-o-information-circle">{{ $flash }}</x-nx-callout>
        @endif

        <x-nx-section icon="heroicon-o-rss" title="Abonnierte Quellen" :hint="$sources->count()"
                      description="Jede Quelle wird in ihrem Intervall abgerufen; neue Einträge kommen roh dazu, bekannte werden übersprungen.">
            @if($sources->isEmpty())
                <x-nx-card>
                    <x-nx-empty icon="heroicon-o-rss">
                        Noch keine Quellen.
                        <x-slot name="action">
                            <x-nx-button variant="secondary" size="sm" wire:click="openCreate">Erste Quelle anlegen</x-nx-button>
                        </x-slot>
                    </x-nx-empty>
                </x-nx-card>
            @else
                <x-nx-card flush>
                    <x-nx-table>
                        <x-nx-table-header>
                            <x-nx-table-header-cell>Name</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Thema</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Cluster</x-nx-table-header-cell>
                            <x-nx-table-header-cell align="right">Einträge</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Letzter Abruf</x-nx-table-header-cell>
                            <x-nx-table-header-cell align="right"></x-nx-table-header-cell>
                        </x-nx-table-header>
                        <x-nx-table-body>
                            @foreach($sources as $s)
                                <x-nx-table-row wire:key="rs-{{ $s->id }}">
                                    <x-nx-table-cell>
                                        <button type="button" wire:click="edit({{ $s->id }})" class="text-left hover:text-[var(--nx-accent)]">
                                            <div class="{{ $s->is_active ? '' : 'text-[var(--nx-faint)] line-through' }}">{{ $s->name }}</div>
                                            @if($s->note)<div class="text-xs text-[var(--nx-muted)] line-clamp-1">{{ $s->note }}</div>@endif
                                        </button>
                                    </x-nx-table-cell>
                                    <x-nx-table-cell>{{ $s->topic ?? '–' }}</x-nx-table-cell>
                                    <x-nx-table-cell>{{ $s->cluster ?? '–' }}</x-nx-table-cell>
                                    <x-nx-table-cell align="right">{{ $s->items_count }}</x-nx-table-cell>
                                    <x-nx-table-cell>
                                        @if($s->last_error)
                                            <x-nx-badge variant="danger" dot title="{{ $s->last_error }}">Fehler</x-nx-badge>
                                        @elseif($s->last_pulled_at)
                                            <span class="text-xs text-[var(--nx-muted)]">{{ $s->last_pulled_at->diffForHumans() }} · alle {{ $s->pull_interval_hours }} h</span>
                                        @else
                                            <span class="text-xs text-[var(--nx-faint)]">noch nie</span>
                                        @endif
                                    </x-nx-table-cell>
                                    <x-nx-table-cell align="right">
                                        <div class="flex justify-end gap-1">
                                            <x-nx-button variant="ghost" size="sm" wire:click="pullNow({{ $s->id }})" title="Jetzt abrufen">@svg('heroicon-o-arrow-path', 'w-4 h-4')</x-nx-button>
                                            <x-nx-button variant="ghost" size="sm" wire:click="toggle({{ $s->id }})" :title="$s->is_active ? 'Pausieren' : 'Aktivieren'">
                                                @svg($s->is_active ? 'heroicon-o-pause' : 'heroicon-o-play', 'w-4 h-4')
                                            </x-nx-button>
                                        </div>
                                    </x-nx-table-cell>
                                </x-nx-table-row>
                            @endforeach
                        </x-nx-table-body>
                    </x-nx-table>
                </x-nx-card>
            @endif
        </x-nx-section>
    </x-ui-page-container>

    <x-nx-modal wire:model="showForm" size="md">
        <x-slot name="header">{{ $editingId ? 'Quelle bearbeiten' : 'Quelle abonnieren' }}</x-slot>
        <div class="space-y-4">
            <x-nx-input-text name="form.name" label="Name" wire:model="form.name" required />
            <x-nx-input-text name="form.url" label="Feed-URL (RSS/Atom)" wire:model="form.url" placeholder="https://…" required />
            <div class="grid grid-cols-2 gap-4">
                <x-nx-input-text name="form.topic" label="Thema" wire:model="form.topic" placeholder="z. B. regulation" />
                <x-nx-input-text name="form.cluster" label="Cluster" wire:model="form.cluster" placeholder="z. B. europa" />
            </div>
            <x-nx-input-textarea name="form.note" label="Wofür ist die Quelle da?" wire:model="form.note" rows="3" />
            <div class="grid grid-cols-2 gap-4">
                <x-nx-input-number name="form.pull_interval_hours" label="Abruf alle … Stunden" wire:model="form.pull_interval_hours" :min="1" />
                <x-nx-input-checkbox name="form.is_active" label="Aktiv" wire:model="form.is_active" />
            </div>
        </div>
        <x-slot name="footer">
            <div class="flex justify-between gap-3">
                <div>
                    @if($editingId)
                        <x-nx-button variant="ghost" wire:click="delete({{ $editingId }})" wire:confirm="Quelle abbestellen? Bisherige Einträge bleiben lesbar.">Abbestellen</x-nx-button>
                    @endif
                </div>
                <div class="flex gap-3">
                    <x-nx-button variant="ghost" wire:click="$set('showForm', false)">Abbrechen</x-nx-button>
                    <x-nx-button variant="primary" wire:click="save">Speichern</x-nx-button>
                </div>
            </div>
        </x-slot>
    </x-nx-modal>
</x-ui-page>
