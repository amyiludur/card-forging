<?php

namespace App\Console\Commands;

use App\Models\CardType;
use App\Models\Character;
use App\Models\Domain;
use App\Models\Keyword;
use App\Models\Module;
use App\Models\PlayerCard;
use App\Models\RuleDocument;
use App\Models\RulesConfig;
use App\Models\Scenario;
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
        $path = rtrim($this->option('path') ?: base_path('design'), '/');

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

        foreach (Scenario::with(['storyBeats', 'entityCards.faces.cardType', 'entityCards.addedByBeat', 'boardCards.addedByBeat', 'townActions'])->get() as $scenario) {
            $this->writeJson("{$path}/data/{$scenario->slug}.json", $this->scenario($scenario));
            $this->line("  design/data/{$scenario->slug}.json");
        }

        foreach (Module::with(['entityCards.faces.cardType', 'boardCards'])->orderBy('sort')->get() as $module) {
            $this->writeJson("{$path}/data/modules/{$module->slug}.json", $this->module($module));
            $this->line("  design/data/modules/{$module->slug}.json");
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

    private function cardTypes(): array
    {
        return CardType::orderBy('sort')->get()
            ->map(fn (CardType $t) => ['id' => $t->slug, 'name' => $t->name, 'description' => $t->description])
            ->all();
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
            'health' => $character->health,
            'handSize' => $character->hand_size,
            'goldPerRound' => $character->gold_per_round,
            'ability' => [
                'name' => $character->ability_name,
                'text' => $character->ability_text,
            ],
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
            'setIcon' => $domain->set_icon,
            'neutral' => $domain->is_neutral,
            'cards' => $cards(PlayerCard::ROLE_DOMAIN),
            'upgrades' => $cards(PlayerCard::ROLE_UPGRADE),
            'notes' => $domain->notes ?? [],
        ];
    }

    /** Reads one role's cards off whichever owner holds them. */
    private function cardLists(Character|Domain $owner): callable
    {
        return fn (string $role) => $owner->cards
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
            'keywords' => $card->keywords ?? [],
            'upgradesTo' => $card->upgrades_to,
            'upgradeOf' => $card->upgrade_of,
            'origin' => $card->origin,
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
            'compatibleScenarios' => $module->compatible_scenarios ?? [],
            'traits' => $module->traits ?? [],
            'setup' => $module->setup,
            'boardCards' => $module->boardCards->map(fn ($c) => array_filter([
                'name' => $c->name,
                'qty' => $c->qty > 1 ? $c->qty : null,
                'health' => $this->health($c->health),
                'traits' => $c->traits ?: null,
                'text' => $c->text,
            ], fn ($v) => $v !== null))->all(),
            'entityCards' => $module->entityCards->map(fn ($c) => $this->entityCard($c))->all(),
        ];
    }

    /** One shape for a deck card, whether it belongs to a scenario or a module. */
    private function entityCard($card): array
    {
        return [
            'name' => $card->name,
            'qty' => $card->qty,
            'layout' => $card->layout,
            'omenCost' => $card->omen_is_x ? 'X' : $card->omen_cost,
            'traits' => $card->traits ?? [],
            'arrow' => $card->arrow,
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
            'startingDread' => $scenario->starting_dread,
            'dreadEffect' => $scenario->dread_effect,
            'printedArrows' => $scenario->printed_arrows,
            'traits' => $scenario->traits ?? [],
            'boardSetup' => $scenario->boardCards->whereNull('added_by_beat_id')->values()->map(fn ($c) => array_filter([
                'name' => $c->name,
                'qty' => $c->qty,
                'health' => $this->health($c->health),
                'traits' => $c->traits ?: null,
                'text' => $c->text,
            ], fn ($v) => $v !== null))->all(),
            'boardAddedByBeats' => $scenario->boardCards->whereNotNull('added_by_beat_id')->values()->map(fn ($c) => array_filter([
                'beat' => $c->addedByBeat?->order,
                'name' => $c->name,
                'qty' => $c->qty,
                'health' => $this->health($c->health),
                'traits' => $c->traits ?: null,
                'text' => $c->text,
            ], fn ($v) => $v !== null))->all(),
            'storyBeats' => $scenario->storyBeats->map(fn ($b) => [
                'order' => $b->order,
                'name' => $b->name,
                'flavour' => $b->flavour,
                'onReach' => $b->on_reach,
                'advance' => $b->advance,
                'onAdvance' => $b->on_advance,
                'dreadChange' => $b->dread_change,
            ])->all(),
            'town' => $scenario->townActions->map(fn ($a) => array_filter([
                'name' => $a->name,
                'effect' => $a->effect,
                'goldCost' => $a->gold_cost,
                'omen' => $a->omen,
                'note' => $a->note,
            ], fn ($v) => $v !== null))->all(),
            'win' => $scenario->win_text,
            'lose' => $scenario->lose_text,
            'entityDeck' => $scenario->entityCards->map(fn ($c) => $this->entityCard($c))->all(),
            'moduleRules' => array_filter([
                'required' => $scenario->modules_required,
                'recommended' => $scenario->recommended_modules ?? [],
                'note' => $scenario->module_note,
            ], fn ($v) => $v !== null),
        ];
    }
}
