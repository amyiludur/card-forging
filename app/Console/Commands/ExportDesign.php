<?php

namespace App\Console\Commands;

use App\Models\BoardCard;
use App\Support\CardArt;
use App\Models\CardType;
use App\Models\Character;
use App\Models\Domain;
use App\Models\Keyword;
use App\Models\Module;
use App\Models\PlayerCard;
use App\Models\RuleDocument;
use App\Models\RulesConfig;
use App\Models\Scenario;
use App\Models\Tale;
use App\Models\TaleCard;
use App\Models\TaleLogEntry;
use App\Models\TaleMode;
use App\Models\TaleStep;
use App\Models\TownAction;
use App\Support\CardNames;
use Illuminate\Console\Command;

/**
 * Writes the database back out to the design folder, in the same shape it was
 * imported from. The design folder stays the thing the designer reads and git
 * tracks; the database is what the editor works on. Round trip: import, edit,
 * export, commit.
 */
class ExportDesign extends Command
{
    protected $signature = 'design:export {--path= : Design folder to write to (defaults to design/)}';

    protected $description = 'Export the database back to the design folder as JSON and markdown';

    /** Kept word for word from the designer's own rules-config.json. */
    private const CONFIG_NOTE = 'Every value here is a placeholder for playtesting unless marked otherwise. The platform should treat these as editable parameters and reference them from rules text.';

    public function handle(): int
    {
        $path = rtrim($this->option('path') ?: config('cardforge.design_path'), '/');

        foreach (["{$path}/data", "{$path}/data/modules", "{$path}/rules", "{$path}/players"] as $dir) {
            is_dir($dir) || mkdir($dir, 0o755, true);
        }

        $this->writeJson("{$path}/data/rules-config.json", $this->config());
        $this->writeJson("{$path}/data/card-types.json", ['types' => $this->cardTypes()]);

        // Written once there is a keyword to write, or once the file exists, so
        // a design folder with no keywords does not grow an empty one and a
        // folder that had some still sees the last one go.
        $keywords = $this->keywords();

        if ($keywords !== [] || is_file("{$path}/data/keywords.json")) {
            $this->writeJson("{$path}/data/keywords.json", ['keywords' => $keywords]);
        }

        // The common districts, written once. Only when there is one, so a
        // folder nobody has written one for does not grow the file; kept once
        // it exists, so taking the last one out is written down too.
        $common = TownAction::common()->orderBy('sort')->orderBy('name')->get()->map(fn ($a) => $this->townAction($a))->all();

        if ($common !== [] || is_file("{$path}/data/town.json")) {
            $this->writeJson("{$path}/data/town.json", ['districts' => $common]);
        }

        foreach (Scenario::with(['storyBeats', 'cardTypes', 'entityCards.faces.cardType', 'entityCards.addedByBeat', 'boardCards.addedByBeat', 'townActions', 'townPicks.townAction'])->get() as $scenario) {
            $this->writeJson("{$path}/data/{$scenario->slug}.json", $this->scenario($scenario));
            $this->line("  design/data/{$scenario->slug}.json");
        }

        foreach (Module::with(['entityCards.faces.cardType', 'boardCards', 'townActions', 'tale'])->orderBy('sort')->get() as $module) {
            $this->writeJson("{$path}/data/modules/{$module->slug}.json", $this->module($module));
            $this->line("  design/data/modules/{$module->slug}.json");
        }

        foreach (Tale::with(['steps.scenario', 'steps.modules', 'modes', 'logEntries', 'cards', 'rewardCards'])->orderBy('sort')->orderBy('name')->get() as $tale) {
            // Made on the way past, so a design folder with no Tales does not
            // grow an empty directory.
            is_dir("{$path}/data/tales") || mkdir("{$path}/data/tales", 0o755, true);

            $this->writeJson("{$path}/data/tales/{$tale->slug}.json", $this->tale($tale));
            $this->line("  design/data/tales/{$tale->slug}.json");
        }

        foreach (Domain::with('cards')->orderBy('sort')->get() as $domain) {
            // Made on the way past rather than up front, so a design folder
            // with no domains does not grow an empty directory.
            is_dir("{$path}/players/domains") || mkdir("{$path}/players/domains", 0o755, true);

            $this->writeJson("{$path}/players/domains/{$domain->slug}.json", $this->domain($domain));
            $this->line("  design/players/domains/{$domain->slug}.json");
        }

        foreach (Character::with('cards')->orderBy('sort')->get() as $character) {
            $this->writeJson("{$path}/players/{$character->slug}.json", $this->character($character));
            $this->line("  design/players/{$character->slug}.json");
        }

        foreach (RuleDocument::orderBy('sort')->get() as $document) {
            $file = "{$path}/rules/{$document->slug}.md";
            file_put_contents($file, $document->body ?? '');
            $this->line('  design/rules/'.basename($file));
        }

        $this->info('Design exported. Commit the design folder to keep the history in git.');

        return self::SUCCESS;
    }

    private function writeJson(string $file, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // json_encode indents with four spaces; the design folder is written with
        // two, and matching it keeps the git diffs down to the real changes.
        $json = preg_replace_callback(
            '/^(?: {4})+/m',
            fn (array $m): string => str_repeat('  ', strlen($m[0]) / 4),
            $json
        );

        file_put_contents($file, $json."\n");
    }

    private function config(): array
    {
        $out = ['_note' => self::CONFIG_NOTE];

        foreach (RulesConfig::orderBy('sort')->get() as $config) {
            $out[$config->key] = $config->raw_value;
        }

        return $out;
    }

    /** The shared library. A scenario's own are written in its own file. */
    private function cardTypes(): array
    {
        return CardType::shared()->orderBy('sort')->get()
            ->map(fn (CardType $t) => $this->cardType($t))
            ->all();
    }

    /**
     * One card type. Colour and icon are written only when they are set, so a
     * design folder that has never been coloured keeps the shape the designer
     * already has rather than growing two null keys — the same rule a
     * Hireling's two numbers follow.
     */
    private function cardType(CardType $type): array
    {
        return [
            'id' => $type->slug,
            'name' => $type->name,
            'description' => $type->description,
            ...($type->colour ? ['colour' => $type->colour] : []),
            ...($type->icon ? ['icon' => $type->icon] : []),
        ];
    }

    /** The keyword library, in the order the editor lists it. */
    private function keywords(): array
    {
        return Keyword::orderBy('sort')->orderBy('name')->get()
            ->map(fn (Keyword $k) => [
                'token' => $k->token,
                'name' => $k->name,
                'icon' => $k->icon,
                'showName' => $k->show_name,
                'plain' => $k->plain,
                'description' => $k->description,
                // Only when written, so a library without reminders keeps its file.
                ...($k->reminder !== null && $k->reminder !== '' ? ['reminder' => $k->reminder] : []),
                'isPlaceholder' => $k->is_placeholder,
            ])
            ->all();
    }

    /**
     * A character file, in the three lists the design folder writes it in. Keys
     * are in the handoff's order so an export is a small diff, not a rewrite.
     */
    private function character(Character $character): array
    {
        $cards = $this->cardLists($character);

        return [
            'id' => $character->slug,
            'name' => $character->name,
            'title' => $character->title,
            'story' => $character->story,
            'status' => $character->status,
            'identity' => $character->identity,
            // A picture, only when the card has one: App\Support\CardArt.
            ...CardArt::forDesign($character),
            // Written only when a colour has been picked, for the same reason
            // a card type's is.
            ...($character->colour || $character->colour_secondary ? [
                'colours' => array_filter([
                    'from' => $character->colour,
                    'to' => $character->colour_secondary,
                ]),
            ] : []),
            'health' => $character->scaledHealth()->forDesign(),
            'handSize' => $character->scaledHandSize()->forDesign(),
            'goldPerRound' => $character->scaledGoldPerRound()->forDesign(),
            'ability' => [
                'name' => $character->ability_name,
                'text' => $character->ability_text,
            ],
            // Written only when there is one, so a character with the one
            // ability keeps the file shape it already has.
            ...($character->extraAbilities() ? ['extraAbilities' => $character->extraAbilities()] : []),
            'kit' => $cards(PlayerCard::ROLE_KIT),
            'signatureCards' => $cards(PlayerCard::ROLE_SIGNATURE),
            'upgrades' => $cards(PlayerCard::ROLE_UPGRADE),
            'notes' => $character->notes ?? [],
        ];
    }

    /**
     * A domain file: the shared pool, in the same card shape a character file
     * uses, written in two lists rather than three.
     */
    private function domain(Domain $domain): array
    {
        $cards = $this->cardLists($domain);

        return [
            'id' => $domain->slug,
            'name' => $domain->name,
            'title' => $domain->title,
            'status' => $domain->status,
            'identity' => $domain->identity,
            // Written only when a colour has been picked, the same rule a
            // character's colours and a card type's follow.
            ...($domain->colour || $domain->colour_secondary ? [
                'colours' => array_filter([
                    'from' => $domain->colour,
                    'to' => $domain->colour_secondary,
                ]),
            ] : []),
            'setIcon' => $domain->set_icon,
            'neutral' => $domain->is_neutral,
            'cards' => $cards(PlayerCard::ROLE_DOMAIN),
            'upgrades' => $cards(PlayerCard::ROLE_UPGRADE),
            'notes' => $domain->notes ?? [],
        ];
    }

    /**
     * A Tale: its story, its scenarios in order with the modules that join
     * each, its modes, its log entries, its own cards, and its rewards written
     * the way a domain writes its cards and upgrades.
     */
    private function tale(Tale $tale): array
    {
        $cards = $this->cardLists($tale);

        return [
            'id' => $tale->slug,
            'name' => $tale->name,
            'status' => $tale->status,
            'overview' => $tale->overview,
            'setIcon' => $tale->set_icon,
            'isPlaceholder' => $tale->is_placeholder,
            'steps' => $tale->steps->map(fn (TaleStep $step) => [
                'scenario' => $step->scenario?->slug,
                'modules' => $step->modules->pluck('slug')->all(),
                'intro' => $step->intro,
                'outro' => $step->outro,
                'notes' => $step->notes,
            ])->all(),
            'modes' => $tale->modes->map(fn (TaleMode $mode) => [
                'name' => $mode->name,
                'text' => $mode->text,
                'isPlaceholder' => $mode->is_placeholder,
            ])->all(),
            'logEntries' => $tale->logEntries->map(fn (TaleLogEntry $entry) => [
                'name' => $entry->name,
                'kind' => $entry->kind,
                'scope' => $entry->scope,
                'isPlaceholder' => $entry->is_placeholder,
            ])->all(),
            'cards' => $tale->cards->map(fn (TaleCard $card) => [
                'id' => $card->slug,
                'name' => $card->name,
                'qty' => $card->qty,
                'typeLine' => $card->type_line,
                'text' => $card->text,
                'flavour' => $card->flavour,
                ...CardArt::forDesign($card),
                'traits' => $card->traits ?? [],
                'isPlaceholder' => $card->is_placeholder,
            ])->all(),
            'rewards' => $cards(PlayerCard::ROLE_REWARD),
            'upgrades' => $cards(PlayerCard::ROLE_UPGRADE),
        ];
    }

    /** Reads one role's cards off whichever owner holds them. A Tale's are its rewardCards. */
    private function cardLists(Character|Domain|Tale $owner): callable
    {
        return fn (string $role) => ($owner instanceof Tale ? $owner->rewardCards : $owner->cards)
            ->where('role', $role)
            ->values()
            ->map(fn (PlayerCard $c) => $this->playerCard($c))
            ->all();
    }

    private function playerCard(PlayerCard $card): array
    {
        return [
            'id' => $card->slug,
            'name' => $card->name,
            'qty' => $card->qty,
            'type' => $card->type,
            'traits' => $card->traits ?? [],
            'goldCost' => $card->gold_cost,
            'omenIcons' => $card->omen_icons,
            'shopCost' => $card->shop_cost,
            'startZone' => $card->start_zone,
            'text' => $card->text,
            ...($card->flavour !== null && $card->flavour !== '' ? ['flavour' => $card->flavour] : []),
            ...CardArt::forDesign($card),
            'keywords' => $card->keywords ?? [],
            'upgradesTo' => $card->upgrades_to,
            'upgradeOf' => $card->upgrade_of,
            'origin' => $card->origin,
            // Uses and a sacrifice value are a Hireling's, so only a Hireling
            // writes them — a card of any other type keeps the shape the
            // design folder already has rather than growing two null keys.
            ...($card->isHireling() ? [
                'uses' => $card->uses,
                'sacrificeValue' => $card->sacrifice_value,
            ] : []),
        ];
    }

    private function module(Module $module): array
    {
        return [
            'id' => $module->slug,
            'name' => $module->name,
            'status' => $module->status,
            'theme' => $module->theme,
            'setIcon' => $module->set_icon,
            // A Tale's own module names its Tale, written only when it has one,
            // so a shared module keeps its file byte for byte.
            ...($module->tale ? ['tale' => $module->tale->slug] : []),
            'compatibleScenarios' => $module->compatible_scenarios ?? [],
            'traits' => $module->traits ?? [],
            'setup' => $module->setup,
            'boardCards' => $module->boardCards->map(fn ($c) => array_filter([
                'name' => $c->name,
                // 1 is the default the import reads, so it is left out — but 0
                // (none out until a card brings one) has to be written.
                'qty' => $c->qty !== 1 ? $c->qty : null,
                // Only when the print run is its own number, so a file nobody
                // set one in comes back out byte for byte.
                'printQty' => $c->print_qty,
                'health' => $this->health($c->health),
                'traits' => $c->traits ?: null,
                'text' => $c->text,
                'flavour' => $c->flavour !== '' ? $c->flavour : null,
                ...CardArt::forDesign($c),
            ], fn ($v) => $v !== null))->all(),
            'entityCards' => $module->entityCards->map(fn ($c) => $this->entityCard($c))->all(),
            // Districts the module adds to the town, written only when it has
            // one, so a module without keeps its file byte for byte.
            ...($module->townActions->isNotEmpty() ? [
                'town' => $module->townActions->map(fn ($a) => $this->townAction($a))->all(),
            ] : []),
        ];
    }

    /** One shape for a district, whether it belongs to a scenario or a module. */
    private function townAction(TownAction $action): array
    {
        return array_filter([
            'name' => $action->name,
            'effect' => $action->effect,
            'goldCost' => $action->gold_cost,
            'omen' => $action->omen,
            'note' => $action->note,
            'flavour' => $action->flavour !== '' ? $action->flavour : null,
            ...CardArt::forDesign($action),
        ], fn ($v) => $v !== null);
    }

    /** One shape for a deck card, whether it belongs to a scenario or a module. */
    private function entityCard($card): array
    {
        // "omenMax" sits beside the X it caps, and only when one is set, so a
        // design folder nobody has capped comes back out byte for byte.
        $max = $card->omen_is_x && $card->omen_x_max !== null ? ['omenMax' => $card->omen_x_max] : [];

        return [
            'name' => $card->name,
            'qty' => $card->qty,
            'layout' => $card->layout,
            'omenCost' => $card->omen_is_x ? 'X' : $card->omen_cost,
            ...$max,
            'traits' => $card->traits ?? [],
            // One arrow as ever; an object of the count each way only when the
            // copies are mixed, so a folder nobody has mixed exports byte for byte.
            'arrow' => $card->hasMixedArrows() ? $card->arrowCounts() : $card->arrow,
            ...($card->flavour !== null && $card->flavour !== '' ? ['flavour' => $card->flavour] : []),
            ...CardArt::forDesign($card),
            'faces' => $card->faces->map(fn ($f) => array_filter([
                'position' => $f->half === 'single' ? null : $f->half,
                'type' => $f->cardType?->slug,
                'text' => $f->text,
            ], fn ($v) => $v !== null))->all(),
            'addedByBeat' => $card->addedByBeat?->order,
        ];
    }

    /** Health is free text in the database, but a plain number should stay a number. */
    private function health(?string $value): string|int|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ctype_digit($value) ? (int) $value : $value;
    }

    private function scenario(Scenario $scenario): array
    {
        return [
            'id' => $scenario->slug,
            'name' => $scenario->name,
            'entityType' => $scenario->entity_type,
            'status' => $scenario->status,
            'overview' => $scenario->overview,
            // A number when it is one, the equation when the designer has
            // written one: one key either way, so a design folder nobody has
            // scaled comes back out byte for byte.
            'startingDread' => $scenario->startingDread()->forDesign(),
            // Only when the scenario sets its own: one left on the shared
            // tunable number writes nothing, so a design folder nobody has
            // given a starting omen comes back out byte for byte.
            ...($scenario->hasOwnStartingOmen() ? ['startingOmen' => $scenario->startingOmen()->forDesign()] : []),
            'dreadEffect' => $scenario->dread_effect,
            'printedArrows' => $scenario->printed_arrows,
            'traits' => $scenario->traits ?? [],
            // The setup card's steps, as one string with the newlines the
            // designer typed. Written only when there is a setup to write, so a
            // design folder nobody has written one in comes back out byte for
            // byte — the same rule a colour and a Hireling's numbers follow.
            ...($scenario->setup !== null && $scenario->setup !== '' ? ['setup' => $scenario->setup] : []),
            'boardSetup' => $scenario->boardCards->whereNull('added_by_beat_id')->values()->map(fn ($c) => array_filter([
                'name' => $c->name,
                'qty' => $c->qty,
                // Only when set: null prints what setup puts out.
                'printQty' => $c->print_qty,
                // Only on a dealt card, so a scenario nothing is dealt in
                // comes back out byte for byte.
                'start' => $c->isDealt() ? BoardCard::START_DEALT : null,
                'dealGroup' => $c->isDealt() && $c->deal_group !== null && $c->deal_group !== '' ? $c->deal_group : null,
                'health' => $this->health($c->health),
                'traits' => $c->traits ?: null,
                'text' => $c->text,
                'flavour' => $c->flavour !== '' ? $c->flavour : null,
                ...CardArt::forDesign($c),
            ], fn ($v) => $v !== null))->all(),
            'boardAddedByBeats' => $scenario->boardCards->whereNotNull('added_by_beat_id')->values()->map(fn ($c) => array_filter([
                'beat' => $c->addedByBeat?->order,
                'name' => $c->name,
                'qty' => $c->qty,
                'printQty' => $c->print_qty,
                'health' => $this->health($c->health),
                'traits' => $c->traits ?: null,
                'text' => $c->text,
                'flavour' => $c->flavour !== '' ? $c->flavour : null,
                ...CardArt::forDesign($c),
            ], fn ($v) => $v !== null))->all(),
            'storyBeats' => $scenario->storyBeats->map(fn ($b) => [
                'order' => $b->order,
                'name' => $b->name,
                'flavour' => $b->flavour,
                ...CardArt::forDesign($b),
                'onReach' => $b->on_reach,
                'advance' => $b->advance,
                'onAdvance' => $b->on_advance,
                'dreadChange' => $b->dreadChange()->forDesign(),
            ])->all(),
            // The town in its order: its own districts in full, and a common
            // one it picked by key, with the line it adds when it adds one.
            'town' => $scenario->town()->map(fn (TownAction $a) => $a->relationLoaded('pick')
                ? array_filter([
                    'common' => CardNames::key($a->name),
                    'extra' => $a->getRelation('pick')->extra,
                ], fn ($v) => $v !== null && $v !== '')
                : $this->townAction($a))->all(),
            'win' => $scenario->win_text,
            'lose' => $scenario->lose_text,
            // The types this scenario owns, written only when it owns some, so
            // a scenario drawing on the shared library alone keeps its file.
            ...($scenario->cardTypes->isNotEmpty() ? [
                'cardTypes' => $scenario->cardTypes->map(fn (CardType $t) => $this->cardType($t))->all(),
            ] : []),
            'entityDeck' => $scenario->entityCards->map(fn ($c) => $this->entityCard($c))->all(),
            'moduleRules' => array_filter([
                'required' => $scenario->modules_required,
                'recommended' => $scenario->recommended_modules ?? [],
                'note' => $scenario->module_note,
            ], fn ($v) => $v !== null),
        ];
    }
}
