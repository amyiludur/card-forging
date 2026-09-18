@php
    $kind = $card['kind'];
@endphp

@if ($kind === 'entity')
    <div class="card">
        <div class="card-inner">
            <div class="card-head">
                <div class="omen {{ $card['omen_is_x'] ? 'omen-x' : '' }}">{{ $card['omen_label'] }}</div>
                <div class="card-name">{{ $card['name'] }}</div>
            </div>

            <div class="card-body">
                @if ($card['layout'] === 'split')
                    <div class="split-body">
                        <div class="arrow-rail">
                            <div class="arrow-slot {{ $card['arrow'] === 'top' ? 'on' : ($card['arrow'] === null ? 'unset' : '') }}">
                                {{ $card['arrow'] === 'top' ? '▶' : '▷' }}
                            </div>
                            <div class="arrow-slot {{ $card['arrow'] === 'bottom' ? 'on' : ($card['arrow'] === null ? 'unset' : '') }}">
                                {{ $card['arrow'] === 'bottom' ? '▶' : '▷' }}
                            </div>
                        </div>
                        <div class="split-halves">
                            @foreach ($card['faces'] as $face)
                                <div class="half">
                                    <div class="type">{{ $face['type_name'] ?? 'No type' }}</div>
                                    <div class="effect">{!! $face['html'] !!}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    @foreach ($card['faces'] as $face)
                        <div class="half">
                            <div class="type">{{ $face['type_name'] ?? 'No type' }}</div>
                            <div class="effect">{!! $face['html'] !!}</div>
                        </div>
                    @endforeach
                @endif
            </div>

            @if (! empty($card['traits']) || $card['added_by_beat'])
                <div class="card-foot">
                    @foreach ($card['traits'] as $trait)
                        <span class="trait">{{ $trait }}</span>
                    @endforeach
                    @if ($card['added_by_beat'])
                        <span class="beat-tag">Beat {{ $card['added_by_beat']['order'] }}</span>
                    @endif
                </div>
            @endif
        </div>

        @if ($options->showPlaceholders && $card['is_placeholder'])
            <div class="placeholder-flag">PLACEHOLDER</div>
        @endif
    </div>

@elseif ($kind === 'board')
    <div class="card board-card">
        <div class="card-inner">
            <div class="card-head">
                <div class="card-name">{{ $card['name'] }}</div>
                @if ($card['health'])
                    <div class="health">{{ $card['health'] }}</div>
                @endif
            </div>

            <div class="card-body">
                <div class="half">
                    <div class="type">Board</div>
                    <div class="effect">{!! $card['html'] !!}</div>
                </div>
            </div>

            @if (! empty($card['traits']) || $card['added_by_beat'])
                <div class="card-foot">
                    @foreach ($card['traits'] as $trait)
                        <span class="trait">{{ $trait }}</span>
                    @endforeach
                    @if ($card['added_by_beat'])
                        <span class="beat-tag">Beat {{ $card['added_by_beat']['order'] }}</span>
                    @endif
                </div>
            @endif
        </div>

        @if ($options->showPlaceholders && $card['is_placeholder'])
            <div class="placeholder-flag">PLACEHOLDER</div>
        @endif
    </div>

@else
    <div class="card beat-card">
        <div class="card-inner">
            <div class="card-head">
                <div class="omen">{{ $card['order'] }}</div>
                <div class="card-name">{{ $card['name'] }}</div>
                @if ($card['dread_change'] !== 0)
                    <div class="health">▲{{ $card['dread_change'] > 0 ? '+' : '' }}{{ $card['dread_change'] }}</div>
                @endif
            </div>

            @if ($card['flavour'])
                <div class="beat-flavour">{{ $card['flavour'] }}</div>
            @endif

            <div class="card-body">
                @if ($card['on_reach'])
                    <div class="beat-block">
                        <div class="beat-label">When reached</div>
                        {!! $card['html']['on_reach'] !!}
                    </div>
                @endif
                @if ($card['advance'])
                    <div class="beat-block">
                        <div class="beat-label">Advances when</div>
                        {!! $card['html']['advance'] !!}
                    </div>
                @endif
                @if ($card['on_advance'])
                    <div class="beat-block">
                        <div class="beat-label">On advancing</div>
                        {!! $card['html']['on_advance'] !!}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
