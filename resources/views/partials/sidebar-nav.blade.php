@php
    $navGroups = collect(config('navigation.groups'))
        ->map(function (array $group) {
            $group['items'] = collect($group['items'])
                ->filter(fn (array $item) => $item['permission'] === null || auth()->user()?->can($item['permission']))
                ->values()
                ->all();

            $group['hasActiveItem'] = collect($group['items'])
                ->contains(fn (array $item) => request()->routeIs($item['route']));

            return $group;
        })
        ->filter(fn (array $group) => count($group['items']) > 0)
        ->values();
@endphp

<nav id="sidebar-nav" class="sidebar-nav" aria-label="Navigasi utama">
    <div class="sidebar-collapse-row">
        <button type="button" class="sidebar-collapse-btn" id="sidebar-collapse-btn"
            aria-label="{{ $sidebarCollapsed ? 'Perluas sidebar' : 'Ciutkan sidebar' }}"
            aria-expanded="{{ $sidebarCollapsed ? 'false' : 'true' }}" aria-controls="sidebar-nav">
            <svg class="sidebar-collapse-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </button>
    </div>

    @foreach ($navGroups as $group)
        @if ($group['label'] === 'Utama')
            {{-- Menu Utama selalu terbuka, tidak collapsible. --}}
            <div class="sidebar-group">
                <p class="sidebar-group-label"><span class="sidebar-label">{{ $group['label'] }}</span></p>
                <ul>
                    @foreach ($group['items'] as $item)
                        @php $isActive = request()->routeIs($item['route']); @endphp
                        <li>
                            <a href="{{ route($item['route']) }}" title="{{ $item['label'] }}" @if($isActive) aria-current="page" @endif>
                                @include('partials.icon', ['name' => $item['icon'] ?? 'default'])
                                <span class="sidebar-label">{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @else
            <details class="sidebar-group sidebar-group-collapsible" @if($group['hasActiveItem']) open @endif>
                <summary class="sidebar-group-label">
                    <span class="sidebar-label">{{ $group['label'] }}</span>
                    <svg class="sidebar-group-chevron" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="9 6 15 12 9 18"></polyline>
                    </svg>
                </summary>
                <ul>
                    @foreach ($group['items'] as $item)
                        @php $isActive = request()->routeIs($item['route']); @endphp
                        <li>
                            <a href="{{ route($item['route']) }}" title="{{ $item['label'] }}" @if($isActive) aria-current="page" @endif>
                                @include('partials.icon', ['name' => $item['icon'] ?? 'default'])
                                <span class="sidebar-label">{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    @endforeach
</nav>
