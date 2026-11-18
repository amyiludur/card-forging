<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Uploading a picture puts it in the design folder, beside the cards that use
 * it, and the app serves it back from there and from nowhere else.
 */
class ArtUploadTest extends TestCase
{
    private string $design;

    protected function setUp(): void
    {
        parent::setUp();

        // Never the real design folder: nothing a test uploads may land there.
        $this->design = sys_get_temp_dir().'/cardforge-art-'.uniqid();
        File::ensureDirectoryExists($this->design);
        config(['cardforge.design_path' => $this->design]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->design);

        parent::tearDown();
    }

    private function upload(string $name = 'Lucky Coin', string $kind = 'player', ?UploadedFile $file = null)
    {
        return $this->postJson('/art', [
            'file' => $file ?? UploadedFile::fake()->image('x.png', 60, 80),
            'kind' => $kind,
            'name' => $name,
        ]);
    }

    public function test_an_upload_lands_in_the_design_folder_under_its_kind(): void
    {
        $this->upload()
            ->assertOk()
            ->assertJson(['path' => 'art/player/lucky-coin.png', 'url' => '/art/player/lucky-coin.png']);

        $this->assertFileExists($this->design.'/art/player/lucky-coin.png');
    }

    public function test_a_second_upload_of_the_same_name_is_numbered(): void
    {
        $this->upload()->assertOk();

        $this->upload()->assertOk()->assertJson(['path' => 'art/player/lucky-coin-2.png']);
        $this->assertFileExists($this->design.'/art/player/lucky-coin.png');
        $this->assertFileExists($this->design.'/art/player/lucky-coin-2.png');
    }

    public function test_a_name_with_nothing_to_slug_is_called_card(): void
    {
        $this->upload('???')->assertOk()->assertJson(['path' => 'art/player/card.png']);
    }

    public function test_it_refuses_a_file_that_is_not_an_image_or_too_big(): void
    {
        $this->upload(file: UploadedFile::fake()->create('notes.txt', 2, 'text/plain'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->upload(file: UploadedFile::fake()->image('huge.png')->size(9000))
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    /**
     * PHP refuses an upload over its own upload_max_filesize before the app
     * sees it, so the real cap is the smaller of the two, and the editor is
     * told what it is rather than "the file failed to upload".
     */
    public function test_the_cap_is_what_php_will_actually_accept_and_says_so(): void
    {
        $limit = min(
            \App\Support\CardArt::MAX_KB,
            \App\Support\CardArt::iniKb(ini_get('upload_max_filesize')),
            \App\Support\CardArt::iniKb(ini_get('post_max_size')),
        );

        $this->assertSame($limit, \App\Support\CardArt::maxKb());

        $response = $this->upload(file: UploadedFile::fake()->image('big.png')->size($limit + 1))->assertStatus(422);

        $this->assertStringContainsString('upload_max_filesize', $response->json('errors.file.0'));
    }

    public function test_ini_sizes_read_as_kilobytes(): void
    {
        $this->assertSame(2048, \App\Support\CardArt::iniKb('2M'));
        $this->assertSame(512, \App\Support\CardArt::iniKb('512K'));
        $this->assertSame(1048576, \App\Support\CardArt::iniKb('1G'));
        $this->assertSame(\App\Support\CardArt::MAX_KB, \App\Support\CardArt::iniKb('0'));
    }

    public function test_it_refuses_an_unknown_kind(): void
    {
        $this->upload(kind: 'setup')->assertStatus(422)->assertJsonValidationErrors('kind');
    }

    public function test_it_serves_a_stored_picture(): void
    {
        $this->upload()->assertOk();

        $this->get('/art/player/lucky-coin.png')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_it_will_not_serve_outside_the_art_folder(): void
    {
        file_put_contents($this->design.'/secret.txt', 'not a picture');

        $this->get('/art/../secret.txt')->assertNotFound();
        $this->get('/art/%2e%2e/secret.txt')->assertNotFound();
        $this->get('/art/../../.env')->assertNotFound();
        $this->get('/art/player/missing.png')->assertNotFound();
    }
}
