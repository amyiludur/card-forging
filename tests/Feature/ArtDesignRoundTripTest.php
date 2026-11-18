<?php

namespace Tests\Feature;

use App\Models\BoardCard;
use App\Models\EntityCard;
use App\Models\Keyword;
use App\Models\PlayerCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Card art, flavour and keyword reminders, through the design folder and back.
 * A key is written only when there is something to write, so a folder nobody
 * has drawn for comes back out with no new keys in it.
 */
class ArtDesignRoundTripTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('framework/testing/art-'.uniqid());
        $this->artisan('design:import');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        parent::tearDown();
    }

    private function export(): void
    {
        $this->artisan('design:export', ['--path' => $this->path])->assertSuccessful();
    }

    private function readJson(string $file): array
    {
        return json_decode(file_get_contents("{$this->path}/{$file}"), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<int, string> every JSON file under a folder, as text */
    private function jsonUnder(string $root): array
    {
        return collect(File::allFiles($root))
            ->filter(fn ($f) => $f->getExtension() === 'json')
            ->map(fn ($f) => $f->getContents())
            ->values()
            ->all();
    }

    private function board(): BoardCard
    {
        return BoardCard::whereHas('scenario', fn ($q) => $q->where('slug', 'kraken'))
            ->whereNull('added_by_beat_id')
            ->orderBy('sort')
            ->firstOrFail();
    }

    private function exportedBoard(): array
    {
        return collect($this->readJson('data/kraken.json')['boardSetup'])
            ->firstWhere('name', $this->board()->name);
    }

    public function test_a_folder_with_no_art_flavour_or_reminders_grows_no_new_keys(): void
    {
        $this->export();

        $exported = implode("\n", $this->jsonUnder($this->path));
        $source = implode("\n", $this->jsonUnder(base_path('design')));

        // Compared with the folder rather than with zero, so a card the designer
        // has drawn for does not turn this red: test the tool, not the draft.
        // Beats and Tale cards already wrote flavour; nothing else may start to.
        foreach (['"art"', '"reminder"', '"flavour"'] as $key) {
            $this->assertSame(substr_count($source, $key), substr_count($exported, $key), "{$key} count changed on export");
        }
    }

    public function test_art_with_only_a_file_writes_a_string(): void
    {
        $this->board()->update(['art' => 'art/board/tentacle.png']);

        $this->export();

        $this->assertSame('art/board/tentacle.png', $this->exportedBoard()['art']);
    }

    public function test_art_at_its_defaults_still_writes_a_string(): void
    {
        $this->board()->update(['art' => 'art/board/tentacle.png', 'art_fit' => 'cover', 'art_focus' => [50, 50]]);

        $this->export();

        $this->assertSame('art/board/tentacle.png', $this->exportedBoard()['art']);
    }

    public function test_art_with_settings_writes_an_object_of_what_was_set(): void
    {
        $this->board()->update(['art' => 'art/board/tentacle.png', 'art_size' => 30, 'art_focus' => [20, 80]]);

        $this->export();

        $this->assertSame(
            ['file' => 'art/board/tentacle.png', 'size' => 30, 'focus' => [20, 80]],
            $this->exportedBoard()['art'],
        );
    }

    public function test_art_flavour_and_reminder_survive_export_then_import(): void
    {
        $art = ['art' => 'art/x/a.png', 'art_size' => 55, 'art_fit' => 'contain', 'art_focus' => [10, 90]];

        $board = $this->board();
        $entity = EntityCard::where('name', 'Salt Wind')->firstOrFail();
        $player = PlayerCard::where('slug', 'lucky-coin')->firstOrFail();

        foreach ([$board, $entity, $player] as $card) {
            $card->update($art + ['flavour' => 'Salt and silence.']);
        }

        Keyword::where('token', 'unique')->update(['reminder' => 'One per deck.']);

        $this->export();

        // Wipe what was written, then read it back from the exported folder.
        foreach ([$board, $entity, $player] as $card) {
            $card->update(['art' => null, 'art_size' => null, 'art_fit' => null, 'art_focus' => null, 'flavour' => null]);
        }
        Keyword::where('token', 'unique')->update(['reminder' => null]);

        $this->artisan('design:import', ['--path' => $this->path])->assertSuccessful();

        foreach ([$board, $entity, $player] as $card) {
            $fresh = $card->fresh();

            $this->assertSame('art/x/a.png', $fresh->art, $card::class);
            $this->assertSame(55, $fresh->art_size, $card::class);
            $this->assertSame('contain', $fresh->art_fit, $card::class);
            $this->assertSame([10, 90], $fresh->art_focus, $card::class);
            $this->assertSame('Salt and silence.', $fresh->flavour, $card::class);
        }

        $this->assertSame('One per deck.', Keyword::where('token', 'unique')->value('reminder'));
    }

    /**
     * A window can be sized before there is a picture — 0 hides an entity
     * card's, a portrait card can show an empty one — and that choice has to
     * survive the design folder like any other.
     */
    public function test_a_window_size_without_a_picture_survives_the_round_trip(): void
    {
        $entity = EntityCard::where('name', 'Salt Wind')->firstOrFail();
        $entity->update(['art' => null, 'art_size' => 0]);

        $this->export();

        $written = collect($this->readJson('data/kraken.json')['entityDeck'])->firstWhere('name', 'Salt Wind');
        $this->assertSame(['size' => 0], $written['art']);

        $entity->update(['art_size' => null]);
        $this->artisan('design:import', ['--path' => $this->path])->assertSuccessful();

        $this->assertSame(0, $entity->fresh()->art_size);
        $this->assertNull($entity->fresh()->art);
    }

    public function test_pictures_no_card_uses_are_listed_not_deleted(): void
    {
        $design = sys_get_temp_dir().'/cardforge-orphans-'.uniqid();
        config(['cardforge.design_path' => $design]);

        try {
            $used = \App\Support\CardArt::store(\Illuminate\Http\UploadedFile::fake()->image('a.png'), 'board', 'Used');
            $other = \App\Support\CardArt::store(\Illuminate\Http\UploadedFile::fake()->image('b.png'), 'board', 'Other');
            $this->board()->update(['art' => $used]);

            $this->assertSame([$other], \App\Support\CardArt::orphans());
            $this->assertFileExists("{$design}/{$used}");
            $this->assertFileExists("{$design}/{$other}");

            $this->get('/design')->assertInertia(fn ($page) => $page->where('status.unused_art', [$other]));
        } finally {
            File::deleteDirectory($design);
        }
    }

    public function test_art_naming_a_missing_file_is_imported_as_written_and_warned(): void
    {
        $this->board()->update(['art' => 'art/board/nowhere.png']);
        $this->export();
        $this->board()->update(['art' => null]);

        $this->artisan('design:import', ['--path' => $this->path])
            ->expectsOutputToContain('is not in the design folder')
            ->assertSuccessful();

        $this->assertSame('art/board/nowhere.png', $this->board()->art);
    }
}
