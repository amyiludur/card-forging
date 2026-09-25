<?php

namespace App\Support;

use App\Models\Character;
use App\Models\Domain;
use App\Models\Module;
use App\Models\RuleDocument;
use App\Models\Scenario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The pocket page: every rule and every card in one file, for reading on a
 * phone away from the machine the editor runs on.
 *
 * It is the fourth thing the design brief did not ask for and the designer did:
 * the rulebook prints and the cards print, but neither is any use at a table
 * with no printer, so this is the same two things as one page of HTML that
 * needs no server, no network and no fonts — which is what lets it be published
 * as a static file (.github/workflows/pocket.yml) and read offline.
 *
 * It writes nothing of its own. The rules are the designer's documents, the
 * cards are drawn by the print sheet's own partial, and the two appendices are
 * {@see RulesAppendices}, the same ones the printed rulebook carries. Every
 * placeholder is flagged rather than quietly filled in.
 *
 * The cards are grouped the way the editor groups them — by what owns them,
 * scenario then module then character then domain — because that is how the
 * designer looks for one.
 */
class Pocket
{
    public function __construct(
        private CardPresenter $presenter,
        private Markup $markup,
        private RulesMarkdown $rules,
        private RulesAppendices $appendices,
        private PrintCatalogue $catalogue,
    ) {
    }

    public static function make(): self
    {
        $markup = Markup::make();

        return new self(
            // Auto icons on, the same as every print page: a card that says
            // "damage" draws the symbol here too.
            new CardPresenter($markup, true),
            $markup,
            new RulesMarkdown($markup),
            new RulesAppendices($markup),
            new PrintCatalogue,
        );
    }

    /**
     * Everything the page needs, in the order it is read: the rules, the two
     * appendices, then the cards by owner.
     */
    public function data(): array
    {
        $documents = RuleDocument::orderBy('sort')->orderBy('id')->get();
        $sections = $this->sections();

        return [
            // The heading ids are prefixed per document for the same reason the
            // printed rulebook prefixes them: this is several documents in one
            // page, and two of them may well both have a "Rules" heading.
            'documents' => $documents
                ->map(fn (RuleDocument $document) => [
                    'title' => $document->title,
                    'slug' => $document->slug,
                    'html' => $this->rules->toHtml((string) $document->body, $document->slug),
                    'headings' => $this->rules->headings((string) $document->body, $document->slug),
                ])
                ->values()
                ->all(),
            'numbers' => $this->appendices->numbers(),
            'keywords' => $this->appendices->keywords(),
            'placeholderNumbers' => $this->appendices->placeholderNumbers($documents),
            'sections' => $sections,
            'counts' => [
                'documents' => $documents->count(),
                'cards' => collect($sections)
                    ->flatMap(fn (array $section) => $section['groups'])
                    ->sum(fn (array $group) => count($group['cards'])),
            ],
            // The card faces are the print sheet's, drawn at no bleed and with
            // every placeholder flagged: a page for reading is not a page for
            // cutting, and the designer wants to see which cards are not
            // written yet.
            'options' => new PrintOptions(bleed: 0, showPlaceholders: true),
            'builtAt' => now()->utc()->format('j M Y, H:i').' UTC',
            'title' => 'Card Forge — pocket',
        ];
    }

    /**
     * The cards, grouped by what owns them. A group with nothing in it is
     * dropped; an owner with nothing at all still gets a section, because a
     * domain the designer has named and not written is a fact about the design.
     *
     * @return list<array<string, mixed>>
     */
    private function sections(): array
    {
        $sections = [];

        foreach (Scenario::orderBy('name')->get() as $scenario) {
            $sections[] = $this->section('Scenario', $scenario->name, 'scenario-'.$scenario->slug, $this->scenarioNote($scenario), [
                // Only when there is one: a scenario with no setup written
                // prints no setup card either.
                ['Setup', 'setup', $scenario->hasSetup() ? collect([$scenario]) : collect()],
                ['Entity deck', 'entity', $scenario->entityCards],
                ['Board cards', 'board', $scenario->boardCards],
                ['Story beats', 'beats', $scenario->storyBeats],
                ['Town cards', 'town', $scenario->townActions],
            ]);
        }

        foreach (Module::orderBy('name')->get() as $module) {
            $sections[] = $this->section('Module', $module->name, 'module-'.$module->slug, $this->countNote($module->deckSize(), 'card'), [
                ['Entity cards', 'entity', $module->entityCards],
                ['Board cards', 'board', $module->boardCards],
            ]);
        }

        foreach (Character::orderBy('sort')->orderBy('name')->get() as $character) {
            $sections[] = $this->section('Character', $character->name, 'character-'.$character->slug, $character->title, [
                ['Character card', 'character', collect([$character])],
                ['Signature cards', 'player', $character->signatureCards],
                ['Kit', 'player', $character->kit],
                ['Upgrades', 'player', $character->upgrades],
            ]);
        }

        foreach (Domain::orderBy('sort')->orderBy('name')->get() as $domain) {
            $sections[] = $this->section('Domain', $domain->name, 'domain-'.$domain->slug, $this->countNote($domain->poolSize(), 'card', 'in the pool'), [
                ['Pool cards', 'player', $domain->poolCards],
                ['Upgrades', 'player', $domain->upgrades],
            ]);
        }

        return $sections;
    }

    /**
     * One owner and its groups. The groups arrive as [title, pool group, models]
     * and leave as rendered cards, the empty ones dropped.
     *
     * @param  list<array{0: string, 1: string, 2: Collection<int, Model>}>  $groups
     */
    private function section(string $kind, string $title, string $id, ?string $note, array $groups): array
    {
        $rendered = [];

        foreach ($groups as [$groupTitle, $group, $models]) {
            if ($models->isEmpty()) {
                continue;
            }

            $rendered[] = [
                'title' => $groupTitle,
                'cards' => $models->map(fn (Model $model) => $this->entry($group, $model))->values()->all(),
            ];
        }

        return [
            'id' => $id,
            'kind' => $kind,
            'title' => $title ?: 'Untitled',
            'note' => $note,
            'groups' => $rendered,
            'count' => collect($rendered)->sum(fn (array $group) => count($group['cards'])),
        ];
    }

    /**
     * One card: the face the print sheet would draw, the line underneath it,
     * and the words the search box matches on.
     */
    private function entry(string $group, Model $model): array
    {
        $card = $this->catalogue->present($group, $model, $this->presenter);
        $described = $this->catalogue->describe($group, $model);

        return [
            'card' => $card,
            'name' => $described['name'],
            'source' => $described['source'],
            // A deck that calls for three of a card says so under it: the face
            // itself carries no quantity, the same as the printed card.
            'qty' => $described['qty'],
            'is_placeholder' => $described['is_placeholder'],
            'find' => $this->searchText($described, $card),
        ];
    }

    /**
     * What the search box reads: the card's name, where it lives and every word
     * on its face, lowercased.
     *
     * The words come off the rendered card rather than the model's own columns,
     * so a card is findable by what it actually shows — a keyword's printed
     * form, a trait, a scenario's Dread rule quoted onto it — and no card kind
     * needs a case of its own here as the design grows. The text as typed
     * travels beside it, which means a card is also findable by the name of a
     * token written on it.
     */
    private function searchText(array $described, array $card): string
    {
        $words = [$described['name'], $described['source']];

        array_walk_recursive($card, function ($value, $key) use (&$words): void {
            if (is_string($value) && (is_int($key) || str_ends_with((string) $key, 'html'))) {
                $words[] = $value;
            }
        });

        // Tags out, entities back to characters, runs of space collapsed: what
        // is left is the words on the card.
        $text = html_entity_decode(strip_tags(implode(' ', array_filter($words))), ENT_QUOTES, 'UTF-8');

        return Str::lower(trim((string) preg_replace('/\s+/u', ' ', $text)));
    }

    /** "34 cards in the entity deck · 2 modules per play", as far as it says. */
    private function scenarioNote(Scenario $scenario): ?string
    {
        $parts = array_filter([
            $this->countNote($scenario->deckSize(), 'card', 'in the entity deck'),
            $scenario->modules_required > 0
                ? $this->countNote($scenario->modules_required, 'module', 'per play')
                : null,
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /** A count and its noun, pluralised on the noun and not on what follows it. */
    private function countNote(int $count, string $noun, string $suffix = ''): string
    {
        return trim($count.' '.Str::plural($noun, $count).' '.$suffix);
    }
}
