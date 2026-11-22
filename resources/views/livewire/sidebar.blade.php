<div>
    <a x-show="!collapsed" href="{{ route('radar.dashboard') }}" wire:navigate
       class="block p-3 text-sm italic text-[var(--nx-text)] uppercase border-b border-[var(--nx-line)] mb-2 hover:text-[var(--nx-accent)]">
        Radar
    </a>

    <div x-show="collapsed" class="px-2 py-2 border-b border-[var(--nx-line)]">
        <a href="{{ route('radar.dashboard') }}" wire:navigate title="Radar"
           class="flex items-center justify-center p-2 rounded-md {{ request()->routeIs('radar.*') ? 'bg-[var(--nx-accent)]/10 text-[var(--nx-accent)]' : 'text-[var(--nx-text)] hover:bg-[var(--nx-bg)]' }}">
            @svg('heroicon-o-signal', 'w-5 h-5')
        </a>
    </div>

    <div class="mt-2" x-show="!collapsed">
        <x-ui-sidebar-list label="Radar">
            <x-ui-sidebar-item :href="route('radar.dashboard')">
                @svg('heroicon-o-newspaper', 'w-4 h-4 text-[var(--nx-text)]')
                <span class="ml-2 text-sm">Einträge</span>
            </x-ui-sidebar-item>
            <x-ui-sidebar-item :href="route('radar.sources.index')">
                @svg('heroicon-o-rss', 'w-4 h-4 text-[var(--nx-text)]')
                <span class="ml-2 text-sm">Quellen</span>
                @if($failing > 0)<span class="ml-auto text-xs text-[var(--nx-danger)]" title="Quellen mit Abruffehler">{{ $failing }}</span>@endif
            </x-ui-sidebar-item>
        </x-ui-sidebar-list>

        @if($topics->isNotEmpty())
            <x-ui-sidebar-list label="Themen">
                @foreach($topics as $topic => $n)
                    <x-ui-sidebar-item :href="route('radar.dashboard', ['topic' => $topic])">
                        @svg('heroicon-o-hashtag', 'w-4 h-4 text-[var(--nx-text)]')
                        <span class="ml-2 text-sm">{{ $topic }}</span>
                        <span class="ml-auto text-xs text-[var(--nx-muted)]">{{ $n }}</span>
                    </x-ui-sidebar-item>
                @endforeach
            </x-ui-sidebar-list>
        @endif
    </div>
</div>
