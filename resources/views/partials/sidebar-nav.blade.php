@php
    $navGroups = collect(config('navigation.groups'))
        ->map(function (array $group) {
            $group['items'] = collect($group['items'])
                ->filter(fn (array $item) => $item['permission'] === null || auth()->user()?->can($item['permission']))
                ->values()
                ->all();

            return $group;
        })
        ->filter(fn (array $group) => count($group['items']) > 0)
        ->values();
@endphp

<nav id="sidebar-nav" class="sidebar-nav" aria-label="Navigasi utama">
    @foreach ($navGroups as $group)
        <div class="sidebar-group">
            <p class="sidebar-group-label">{{ $group['label'] }}</p>
            <ul>
                @foreach ($group['items'] as $item)
                    @php $isActive = request()->routeIs($item['route']); @endphp
                    <li>
                        <a href="{{ route($item['route']) }}" @if($isActive) aria-current="page" @endif>
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</nav>
