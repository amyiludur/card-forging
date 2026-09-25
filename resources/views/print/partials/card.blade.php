@php
    use App\Support\Colour;
    use App\Support\Icons;

    $kind = $card['kind'];

    /*
     * The card's colours, and what the head band makes of them. Mirrors
     * headStyle in resources/js/Components/CardPreview.vue — one card design,
     * two implementations, so change one and change the other.
     *
     * An entity card takes its colour from its type: a split card whose halves
     * are different types takes both, top colour at the top, the way the
     * halves sit. A character takes the two colours the designer picked, and
     * so does every card that character brings — a hero's deck is theirs on
     * sight. A card nobody coloured emits nothing at all and keeps the dark
     * head the sheet's own CSS gives it.
     */
    $headColours = match ($kind) {
        'entity' => array_values(array_unique(array_filter(
            array_map(fn (array $f) => Colour::normalise($f['type_colour'] ?? null), $card['faces'] ?? [])
        ))),
        // A player card's colours are its character's; a domain card carries
        // none, because a domain belongs to no one hero.
        'character', 'player' => array_values(array_filter([
            Colour::normalise($card['colour'] ?? null),
            Colour::normalise($card['colour_secondary'] ?? null),
        ])),
        default => [],
    };

    $headStyle = '';

    if ($headColours !== []) {
        // A hero's wash runs across the corner; a split card's two run down
        // the band, because that is where its halves are.
        $angle = $kind === 'entity' ? 'to bottom' : '135deg';
        $headStyle = 'background: '.Colour::band($headColours[0], $headColours[1] ?? null, $angle).';'
            .' color: '.Colour::ink(...$headColours).';';

        // Two stops that disagree about which ink reads get a halo behind the
        // name, the way the arrow on the card's edge already does.
        if (count($headColours) > 1) {
            $halo = Colour::halo(...$headColours);
            $headStyle .= " text-shadow: 0 0 0.6mm {$halo}, 0 0 0.6mm {$halo};";
        }
    }

    /*
     * The gold chip in a player card's head. It was picked to match the dark
     * blue head, so once a hero colours their cards it follows the head
     * instead. Mirrors chipStyle in resources/js/Components/CardPreview.vue.
     */
    $chipStyle = '';

    if ($kind === 'player' && $headColours !== []) {
        $chip = Colour::chip($headColours[0]);
        $chipStyle = ' style="background: '.$chip.'; color: '.Colour::ink($chip).';"';
    }

    // The type line sits on the cream body, so it takes a version of the
    // colour dark enough to read there rather than the colour as picked.
    $typeStyle = fn (?string $colour) => Colour::normalise($colour)
        ? ' style="color: '.Colour::onPaper($colour).'"'
        : '';
@endphp

@if ($kind === 'entity')
    <div class="card">
        <div class="card-inner">
            <div class="card-head" @if ($headStyle) style="{{ $headStyle }}" @endif>
                <div class="omen {{ $card['omen_is_x'] ? 'omen-x' : '' }}">{{ $card['omen_label'] }}</div>
                <div class="card-name">{{ $card['name'] }}</div>
            </div>

            <div class="card-body">
                @foreach ($card['faces'] as $face)
                    <div class="half">
                        <div class="type"{!! $typeStyle($face['type_colour'] ?? null) !!}>{!! Icons::svg($face['type_icon'] ?? '') !!} {{ $face['type_name'] ?? 'No type' }}</div>
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
        <div class="arrow-edge arrow-{{ $card['arrow'] }}">{!! Icons::svg('arrow') !!}</div>

    </div>

@elseif ($kind === 'player')
    {{-- Mirrors the player branch of resources/js/Components/CardPreview.vue. --}}
    <div class="card player-card">
        <div class="card-inner">
            <div class="card-head" @if ($headStyle) style="{{ $headStyle }}" @endif>
                <div class="omen"{!! $chipStyle !!}>{{ $card['gold_cost'] }}{!! Icons::svg('gold', 'icon pip-mark') !!}</div>
                {{-- Both economy numbers on the left, so the top-right corner
                     stays clear for the placeholder flag. --}}
                @if ($card['omen_icons'])
                    <div class="omen-pips">{!! str_repeat(Icons::svg('omen'), $card['omen_icons']) !!}</div>
                @endif
                <div class="card-name">{{ $card['name'] }}</div>
                {{-- The head's right corner, where a board card carries
                     health. A Hireling has none: what it has is a term.
                     Mirrors isHireling in CardPreview.vue. --}}
                @if ($card['is_hireling'])
                    <div class="uses">{{ $card['uses'] ?? '?' }}{!! Icons::svg('uses', 'icon pip-mark') !!}</div>
                @endif
            </div>

            <div class="card-body">
                <div class="half">
                    <div class="type">{!! Icons::svg($card['type']) !!} {{ \App\Support\CardPresenter::PLAYER_TYPES[$card['type']] ?? $card['type'] }}</div>
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
                    <span class="shop-cost">shop {{ $card['shop_cost'] }}{!! Icons::svg('gold') !!}</span>
                @endif
                @if ($card['is_hireling'])
                    <span class="sacrifice">{!! Icons::svg('sacrifice') !!} {{ $card['sacrifice_value'] ?? '?' }}</span>
                @endif
                @php
                    // An upgrade names what it replaces; everything else says
                    // where it starts. Mirrors cornerNote in CardPreview.vue.
                    $isUpgrade = $card['role'] === 'upgrade';
                    $corner = $isUpgrade
                        ? 'replaces '.($card['replaces_name'] ?? 'nothing yet')
                        : (\App\Support\CardPresenter::START_ZONE_LABELS[$card['start_zone']] ?? null);
                    $cornerIcon = $isUpgrade ? 'zone-upgrade' : 'zone-'.$card['start_zone'];
                    // A card out of a shared pool carries its badge, the way a
                    // module card does. Mirrors domainBadge in CardPreview.vue.
                    $badge = $card['set_icon'] ?: $card['domain'];
                @endphp
                @if ($corner)
                    <span class="start-zone">{!! Icons::svg($cornerIcon) !!} {{ $corner }}</span>
                @endif
                @if ($badge)
                    <span class="set-icon">{{ $badge }}</span>
                @endif
            </div>
        </div>

    </div>

@elseif ($kind === 'character')
    <div class="card character-card">
        <div class="card-inner">
            <div class="card-head" @if ($headStyle) style="{{ $headStyle }}" @endif>
                <div class="card-name">{{ $card['name'] }}</div>
                {{-- The number, or the designer's equation counting the
                     players. Mirrors the character branch of CardPreview.vue. --}}
                <div class="health{{ $card['health_equation'] ? ' scaled' : '' }}">{!! $card['health_html'] !!}{!! Icons::svg('health', 'icon pip-mark') !!}</div>
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
                <span class="trait">{!! Icons::svg('hand') !!} {!! $card['hand_size_html'] !!}</span>
                <span class="trait">{!! $card['gold_per_round_html'] !!}{!! Icons::svg('gold', 'icon pip-mark') !!} a round</span>
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
                    {{-- Free text since v1, so it can hold "12 per player"
                         without an equation behind it. --}}
                    <div class="health">{{ $card['health'] }}{!! Icons::svg('health', 'icon pip-mark') !!}</div>
                @endif
            </div>

            <div class="card-body">
                <div class="half">
                    <div class="type">{!! Icons::svg('board') !!} Board</div>
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

@elseif ($kind === 'town')
    {{-- A district of the town. Mirrors the town branch of
         resources/js/Components/CardPreview.vue. --}}
    <div class="card town-card">
        <div class="card-inner">
            <div class="card-head">
                @if ($card['gold_cost'] !== null)
                    <div class="omen">{{ $card['gold_cost'] }}{!! Icons::svg('gold', 'icon pip-mark') !!}</div>
                @endif
                <div class="card-name">{{ $card['name'] }}</div>
                {{-- The corner a board card puts health in. A district has
                     none; what it has is the omen using it adds. --}}
                <div class="omen-add">+{{ $card['omen'] }}{!! Icons::svg('omen', 'icon pip-mark') !!}</div>
            </div>

            <div class="card-body">
                <div class="half">
                    <div class="type">{!! Icons::svg('town') !!} Town</div>
                    <div class="effect">{!! $card['html'] !!}</div>
                    @if ($card['note'])
                        <div class="town-note">{!! $card['note_html'] !!}</div>
                    @endif
                </div>
            </div>
        </div>

    </div>

@elseif ($kind === 'setup')
    {{-- How the scenario is laid out before the first round. Mirrors the setup
         branch of resources/js/Components/CardPreview.vue. --}}
    <div class="card setup-card">
        <div class="card-inner">
            <div class="card-head">
                {{-- The Dread the dial starts on, in the corner a deck card
                     puts its omen cost. --}}
                <div class="omen{{ $card['starting_dread_equation'] ? ' scaled' : '' }}">{!! $card['starting_dread_html'] !!}{!! Icons::svg('dread', 'icon pip-mark') !!}</div>
                <div class="card-name">{{ $card['name'] }}</div>
            </div>

            <div class="card-body">
                <div class="half">
                    <div class="type">{!! Icons::svg('setup') !!} Setup</div>
                    {{-- One step per line, as the designer typed them. The
                         splitting is Scenario::setupSteps(), so the preview and
                         this sheet number the same things. --}}
                    <ol class="effect setup-steps">
                        @foreach ($card['steps_html'] as $step)
                            <li>{!! $step !!}</li>
                        @endforeach
                    </ol>
                </div>
            </div>

            <div class="card-foot">
                <span class="trait">{{ $card['modules_required'] }} {{ $card['modules_required'] === 1 ? 'module' : 'modules' }}</span>
            </div>
        </div>

    </div>

@else
    <div class="card beat-card">
        <div class="card-inner">
            <div class="card-head">
                <div class="omen">{{ $card['order'] }}</div>
                <div class="card-name">{{ $card['name'] }}</div>
                {{-- An equation prints as written; a plain number keeps its
                     sign. A beat that changes nothing prints nothing. --}}
                @if ($card['dread_change_equation'])
                    <div class="health scaled">▲{!! $card['dread_change_html'] !!}</div>
                @elseif ($card['dread_change'] !== 0)
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
