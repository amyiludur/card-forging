<?php

namespace Tests\Feature;

use App\Support\DesignFolder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * The design folder from a button: the same `design:import` and `design:export`
 * the designer has always run, plus the commit that followed.
 *
 * The git side is tested against a scratch repository rather than this one, so
 * the suite can commit and rewrite files without touching the real design/ or
 * the history it lives in.
 */
class DesignFolderTest extends TestCase
{
    use RefreshDatabase;

    private string $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = base_path('storage/framework/testing/design-folder-repo');

        File::deleteDirectory($this->repo);
        File::makeDirectory($this->repo.'/'.DesignFolder::PATH.'/data', 0777, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->repo);

        parent::tearDown();
    }

    /** @param  list<string>  $args */
    private function git(array $args): string
    {
        $process = new Process(['git', ...$args], $this->repo, null, null, 30);
        $process->run();

        return trim($process->getOutput()."\n".$process->getErrorOutput());
    }

    private function scratchRepo(): DesignFolder
    {
        $this->git(['init', '-b', 'main']);
        // Set on the repo itself, so the suite never depends on whose machine
        // it is running on.
        $this->git(['config', 'user.name', 'Test']);
        $this->git(['config', 'user.email', 'test@example.com']);

        File::put($this->repo.'/'.DesignFolder::PATH.'/data/kraken.json', "{}\n");
        File::put($this->repo.'/app.php', "<?php // the tool, not the game\n");
        $this->git(['add', '-A']);
        $this->git(['commit', '-m', 'first']);

        return new DesignFolder($this->repo);
    }

    // ------------------------------------------------------------------ status

    public function test_it_reports_what_has_changed_in_the_folder(): void
    {
        $folder = $this->scratchRepo();

        $this->assertSame([], $folder->changes());

        File::put($this->repo.'/'.DesignFolder::PATH.'/data/kraken.json', "{\"id\": \"kraken\"}\n");
        File::put($this->repo.'/'.DesignFolder::PATH.'/data/wendigo.json', "{}\n");

        $changes = collect($folder->changes())->keyBy('path');

        $this->assertSame('M', $changes['design/data/kraken.json']['status']);
        // A new scenario is a new file: leaving untracked files out would have
        // the page say nothing had changed.
        $this->assertSame('??', $changes['design/data/wendigo.json']['status']);
    }

    public function test_it_says_what_would_stop_a_commit_before_one_is_tried(): void
    {
        $folder = $this->scratchRepo();
        $status = $folder->status();

        $this->assertTrue($status['git']);
        $this->assertSame('main', $status['branch']);
        $this->assertSame('first', $status['last_commit']['subject']);
        // A scratch repo has no origin, and the page says so rather than
        // letting the push fail afterwards.
        $this->assertFalse($status['remote']);
        $this->assertContains(
            'There is no "origin" remote, so a commit can be made but not pushed.',
            $status['blockers']
        );
    }

    public function test_a_folder_that_is_not_a_repository_is_reported_rather_than_crashed_on(): void
    {
        // Outside the working tree on purpose: anywhere under this repository
        // would find this repository's own git and say yes.
        $loose = sys_get_temp_dir().'/card-forge-not-a-repo-'.uniqid();
        File::makeDirectory($loose, 0777, true);

        try {
            $status = (new DesignFolder($loose))->status();

            $this->assertFalse($status['git']);
            $this->assertSame([], $status['changes']);
            $this->assertNotEmpty($status['blockers']);
        } finally {
            File::deleteDirectory($loose);
        }
    }

    public function test_a_staged_change_keeps_its_whole_path(): void
    {
        $folder = $this->scratchRepo();

        File::put($this->repo.'/'.DesignFolder::PATH.'/data/kraken.json', "{\"id\": \"kraken\"}\n");
        $this->git(['add', '--', DesignFolder::PATH]);

        // git writes the two status columns as "M " when a change is staged and
        // " M" when it is not. Counting characters instead of matching them is
        // how the leading space got eaten and took the "d" off "design/".
        $this->assertSame(
            ['design/data/kraken.json'],
            array_column($folder->changes(), 'path')
        );
    }

    // ----------------------------------------------------------------- commits

    public function test_a_commit_carries_the_design_folder_and_never_the_code(): void
    {
        $folder = $this->scratchRepo();

        File::put($this->repo.'/'.DesignFolder::PATH.'/data/kraken.json', "{\"id\": \"kraken\"}\n");
        // The tool changed at the same time, as it does while the designer is
        // being helped. It is not theirs to commit from this button.
        File::put($this->repo.'/app.php', "<?php // edited\n");

        $this->assertTrue($folder->commit('Kraken')['ok']);

        $committed = $this->git(['show', '--name-only', '--format=', 'HEAD']);

        $this->assertStringContainsString('design/data/kraken.json', $committed);
        $this->assertStringNotContainsString('app.php', $committed);

        // And the code is still sitting there uncommitted, exactly as it was.
        $this->assertStringContainsString('app.php', $this->git(['status', '--porcelain']));
    }

    public function test_a_commit_message_is_a_message_and_never_a_command(): void
    {
        $folder = $this->scratchRepo();

        File::put($this->repo.'/'.DesignFolder::PATH.'/data/kraken.json', "{\"id\": \"kraken\"}\n");

        // No shell is involved, so this is a subject line and nothing else.
        $message = 'Kraken; rm -rf / && echo $(whoami) `id` "quoted"';

        $this->assertTrue($folder->commit($message)['ok']);
        $this->assertSame($message, $this->git(['log', '-1', '--format=%s']));
    }

    public function test_committing_nothing_is_reported_rather_than_throwing(): void
    {
        $folder = $this->scratchRepo();

        $result = $folder->commit('nothing changed');

        $this->assertFalse($result['ok']);
        $this->assertNotSame('', $result['output']);
    }

    // ------------------------------------------------------------- the commands

    public function test_export_writes_the_folder_it_was_built_on(): void
    {
        $folder = $this->scratchRepo();

        $this->assertTrue($folder->export()['ok']);

        // Written into the scratch repo, not the real design folder.
        $this->assertFileExists($this->repo.'/'.DesignFolder::PATH.'/data/rules-config.json');
        $this->assertNotSame([], $folder->changes());
    }

    // ---------------------------------------------------------------- the page

    public function test_the_page_renders_with_the_folders_state(): void
    {
        $this->get('/design')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Design/Index')
                ->has('status.changes')
                ->where('status.path', 'design'));
    }

    public function test_publishing_needs_a_message(): void
    {
        // A push is deliberate: there is no default message that would let one
        // happen without the designer saying what it was for.
        $this->post('/design/publish', ['message' => ''])->assertSessionHasErrors('message');
        $this->post('/design/publish', ['message' => str_repeat('x', 201)])->assertSessionHasErrors('message');
    }
}
