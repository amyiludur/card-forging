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
                @foreach ($card['faces'] as $face)
                    <div class="half">
                        <div class="type">{{ $face['type_name'] ?? 'No type' }}</div>
                        <div class="effect">{!! $face['html'] !!}</div>
                    </div>
                @endforeach

                @if ($options->showPlaceholders && $card['is_placeholder'])
                    <div class="placeholder-flag">PLACEHOLDER</div>
                @endif
            </div>

            @if (! empty($card['traits']) || $card['added_by_beat'] || $card['set_icon'])
                <div class="card-foot">
                    @foreach ($card['traits'] as $trait)
                        <span class="trait">{{ $trait }}</span>
                    @endforeach
                    @if ($card['added_by_beat'])
                        <span class="beat-tag">Beat {{ $card['added_by_beat']['order'] }}</span>
                    @endif
                    @if ($card['set_icon'])
                        <span class="set-icon">{{ $card['set_icon'] }}</span>
                    @endif
                </div>
            @endif
        </div>

        {{-- The arrow points at the top or bottom half of the card to its right. --}}
        <div class="arrow-edge arrow-{{ $card['arrow'] }}">▶</div>

    </div>

@elseif ($kind === 'player')
    {{-- Mirrors the player branch of resources/js/Components/CardPreview.vue. --}}
    <div class="card player-card">
        <div class="card-inner">
            <div class="card-head">
                <div class="omen">{{ $card['gold_cost'] }}<span class="pip-mark">●</span></div>
                {{-- Both economy numbers on the left, so the top-right corner
                     stays clear for the placeholder flag. --}}
                @if ($card['omen_icons'])
                    <div class="omen-pips">{{ str_repeat('◆', $card['omen_icons']) }}</div>
                @endif
                <div class="card-name">{{ $card['name'] }}</div>
            </div>

            <div class="card-body">
                <div class="half">
                    <div class="type">{{ \App\Support\CardPresenter::PLAYER_TYPES[$card['type']] ?? $card['type'] }}</div>
                    <div class="effect">{!! $card['html'] !!}</div>
                </div>

                @if ($options->showPlaceholders && $card['is_placeholder'])
                    <div class="placeholder-flag">PLACEHOLDER</div>
                @endif
            </div>

            <div class="card-foot">
                @foreach ($card['traits'] as $trait)
                    <span class="trait">{{ $trait }}</span>
                @endforeach
                @if ($card['shop_cost'] !== null)
                    <span class="shop-cost">shop {{ $card['shop_cost'] }}●</span>
                @endif
                @if ($zone = \App\Support\CardPresenter::START_ZONE_LABELS[$card['start_zone']] ?? null)
                    <span class="start-zone">{{ $zone }}</span>
                @endif
            </div>
        </div>

    </div>

@elseif ($kind === 'character')
    <div class="card character-card">
        <div class="card-inner">
            <div class="card-head">
                <div class="card-name">{{ $card['name'] }}</div>
                <div class="health">{{ $card['health'] }}<span class="pip-mark">♥</span></div>
            </div>

            @if ($card['identity'])
                <div class="beat-flavour">{{ $card['identity'] }}</div>
            @endif

            <div class="card-body">
                <div class="half">
                    <div class="type">{{ $card['ability_name'] ?: 'Ability' }}</div>
                    <div class="effect">{!! $card['html'] !!}</div>
                </div>

                @if ($options->showPlaceholders && $card['is_placeholder'])
                    <div class="placeholder-flag">PLACEHOLDER</div>
                @endif
            </div>

            <div class="card-foot">
                <span class="trait">hand {{ $card['hand_size'] }}</span>
                @unless ($card['title'])
                    <span class="start-zone">name and story not written</span>
                @endunless
            </div>
        </div>

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

                @if ($options->showPlaceholders && $card['is_placeholder'])
                    <div class="placeholder-flag">PLACEHOLDER</div>
                @endif
            </div>

            @if (! empty($card['traits']) || $card['added_by_beat'] || $card['set_icon'])
                <div class="card-foot">
                    @foreach ($card['traits'] as $trait)
                        <span class="trait">{{ $trait }}</span>
                    @endforeach
                    @if ($card['added_by_beat'])
                        <span class="beat-tag">Beat {{ $card['added_by_beat']['order'] }}</span>
                    @endif
                    @if ($card['set_icon'])
                        <span class="set-icon">{{ $card['set_icon'] }}</span>
                    @endif
                </div>
            @endif
        </div>

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
