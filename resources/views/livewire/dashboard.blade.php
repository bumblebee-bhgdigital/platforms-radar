{{-- Radar: Einträge aller Quellen (nx). Liste = Element mit Identität, lesen & öffnen. --}}
<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Radar" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[['label' => 'Radar', 'icon' => 'signal']]">
            <x-nx-button variant="secondary" size="sm" :href="route('radar.sources.index')">
                @svg('heroicon-o-rss', 'w-4 h-4')
                <span>Quellen</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained" spacing="space-y-6">
        <x-nx-stat-grid :cols="4">
            <x-nx-stat label="Quellen" :value="$stats['sources']" icon="heroicon-o-rss" />
            <x-nx-stat label="Einträge" :value="$stats['items']" icon="heroicon-o-newspaper" />
            <x-nx-stat label="Heute neu" :value="$stats['today']" icon="heroicon-o-sparkles" />
            <x-nx-stat label="Abruffehler" :value="$stats['failing']" icon="heroicon-o-exclamation-triangle" :accent="$stats['failing'] > 0 ? 'var(--nx-danger)' : null" />
        </x-nx-stat-grid>

        <x-nx-section icon="heroicon-o-newspaper" title="Einträge" :hint="$total"
                      description="Roh gesammelt aus den abonnierten Feeds. Was davon zählt, entscheiden Agenten und Menschen beim Lesen.">
            <x-slot name="action">
                <div class="flex items-center gap-2">
                    <x-nx-input-text name="search" size="sm" placeholder="Suchen …" wire:model.live.debounce.400ms="search" />
                    <x-nx-input-select name="topic" size="sm" :options="$topicOptions" nullable="true" nullLabel="Alle Themen" wire:model.live="topic" />
                    <x-nx-input-select name="source" size="sm" :options="$sourceOptions" nullable="true" nullLabel="Alle Quellen" wire:model.live="source" />
                </div>
            </x-slot>

            @if($items->isEmpty())
                <x-nx-card>
                    <x-nx-empty icon="heroicon-o-newspaper">
                        Keine Einträge{{ ($topic !== '' || $source !== '' || $search !== '') ? ' für diesen Filter' : '' }}.
                        <x-slot name="action">
                            <x-nx-button variant="secondary" size="sm" :href="route('radar.sources.index')">Quellen verwalten</x-nx-button>
                        </x-slot>
                    </x-nx-empty>
                </x-nx-card>
            @else
                <x-nx-card flush class="divide-y divide-[color:var(--nx-line)]">
                    @foreach($items as $item)
                        <a wire:key="ri-{{ $item->id }}" href="{{ $item->url }}" target="_blank" rel="noopener noreferrer"
                           class="block px-4 py-3 hover:bg-[var(--nx-hover)]">
                            <div class="flex items-baseline gap-3">
                                <span class="text-sm font-medium text-[var(--nx-text)]">{{ $item->title }}</span>
                                <span class="ml-auto shrink-0 text-xs text-[var(--nx-faint)]">{{ ($item->published_at ?? $item->fetched_at)?->format('d.m.Y') }}</span>
                            </div>
                            <div class="mt-0.5 text-xs text-[var(--nx-muted)]">
                                {{ $item->source?->name }}@if($item->source?->topic) · {{ $item->source->topic }}@endif
                            </div>
                            @if($item->excerpt)
                                <p class="mt-1 text-sm text-[var(--nx-muted)] line-clamp-2">{{ $item->excerpt }}</p>
                            @endif
                        </a>
                    @endforeach
                </x-nx-card>
                @if($items->count() < $total)
                    <div class="flex justify-center">
                        <x-nx-button variant="ghost" size="sm" wire:click="more">Weitere laden ({{ $total - $items->count() }})</x-nx-button>
                    </div>
                @endif
            @endif
        </x-nx-section>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Radar" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-3 text-sm text-[color:var(--nx-muted)]">
                <p>Radar sammelt Feeds roh: Titel, Link, Auszug, Datum.</p>
                <p>Bewertung, Zusammenfassung und Relevanz sind Arbeit der Agenten und Menschen – über <code>radar.items.GET</code> lesen sie selbst, so viel sie brauchen.</p>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
