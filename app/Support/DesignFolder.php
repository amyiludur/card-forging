<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

/**
 * The design folder as the app can see and move it: what `design:import` and
 * `design:export` do from a button, and what git has to say about the result.
 *
 * This is the same two commands the designer has always run in a terminal, and
 * it does nothing the terminal would not. What it adds is that the page can say
 * what is about to happen before it happens: which files differ, which branch a
 * push would go to, and whether git is even in a state to take a commit.
 *
 * **Only `design/` is ever staged or committed.** The folder is the game and the
 * rest of the repository is the tool, so a button that writes the game can never
 * quietly carry code along with it.
 *
 * Every git call goes through {@see git()}, which runs the binary with an array
 * of arguments and no shell, so a commit message is a commit message and never
 * something the designer typed that turns into a command.
 */
class DesignFolder
{
    /** The folder, relative to the repository root. Also the only path git is given. */
    public const PATH = 'design';

    public function __construct(private string $root)
    {
    }

    public static function make(): self
    {
        return new self(base_path());
    }

    // ------------------------------------------------------------- the commands

    /**
     * design/ → the editor. Destructive on purpose: the folder is the source of
     * truth, so anything in the editor the folder does not have goes with it.
     */
    public function import(): array
    {
        return $this->artisan('design:import');
    }

    /** The editor → design/. */
    public function export(): array
    {
        return $this->artisan('design:export');
    }

    /** The folder this instance reads and writes. */
    public function path(): string
    {
        return $this->root.'/'.self::PATH;
    }

    /**
     * Both commands are given the folder explicitly rather than letting them
     * fall back to base_path(), so an instance built on another root really
     * works on that root — which is what lets this be tested against a scratch
     * repository instead of the designer's own.
     */
    private function artisan(string $command): array
    {
        try {
            $code = Artisan::call($command, ['--path' => $this->path()]);

            return ['ok' => $code === 0, 'output' => trim(Artisan::output())];
        } catch (\Throwable $e) {
            // A broken design file throws rather than returning a code, and the
            // page should say what broke rather than showing a 500.
            return ['ok' => false, 'output' => $e->getMessage()];
        }
    }

    // ------------------------------------------------------------------- git

    /**
     * Stage and commit `design/`, and nothing else.
     *
     * The paths are given to `git commit` as well as to `git add`, so whatever
     * else the designer happens to have staged stays staged and uncommitted.
     */
    public function commit(string $message): array
    {
        $add = $this->git(['add', '-A', '--', self::PATH]);

        if (! $add['ok']) {
            return $add;
        }

        return $this->git(['commit', '-m', $message, '--', self::PATH]);
    }

    /**
     * Push the current branch to origin, setting it up to track if it does not
     * already. Given longer than the rest because it is the one that goes out
     * over a network.
     */
    public function push(string $branch): array
    {
        return $this->git(['push', '-u', 'origin', $branch], 120);
    }

    /**
     * Everything the page needs to say what a button would do, and what would
     * stop it. Read-only: nothing here changes a thing.
     */
    public function status(): array
    {
        if (! $this->isRepository()) {
            return ['git' => false, 'changes' => [], 'blockers' => ['This is not a git repository, so nothing can be committed.']];
        }

        $branch = trim($this->git(['rev-parse', '--abbrev-ref', 'HEAD'])['output']);
        $detached = $branch === 'HEAD' || $branch === '';
        $changes = $this->changes();
        $remote = $this->git(['remote', 'get-url', 'origin'])['ok'];
        $identity = $this->identity();

        return [
            'git' => true,
            'path' => self::PATH,
            'branch' => $detached ? null : $branch,
            'detached' => $detached,
            'changes' => $changes,
            'clean' => $changes === [],
            'remote' => $remote,
            'identity' => $identity,
            'ahead' => $this->ahead(),
            'last_commit' => $this->lastCommit(),
            'blockers' => $this->blockers($detached, $remote, $identity),
        ];
    }

    /**
     * Why a commit or a push could not happen, said before the button is pressed
     * rather than as a failure afterwards.
     *
     * @return list<string>
     */
    private function blockers(bool $detached, bool $remote, ?array $identity): array
    {
        $blockers = [];

        if ($detached) {
            $blockers[] = 'HEAD is detached, so there is no branch to commit to. Check out a branch first.';
        }

        if ($identity === null) {
            $blockers[] = 'git has no user.name and user.email set, so it cannot write a commit. Set them and reload.';
        }

        if (! $remote) {
            $blockers[] = 'There is no "origin" remote, so a commit can be made but not pushed.';
        }

        return $blockers;
    }

    /**
     * The changed files under design/, as git reports them. Untracked files are
     * included, because a new scenario is a new file and leaving it out would
     * make the page say nothing had changed.
     *
     * @return list<array{status: string, path: string}>
     */
    public function changes(): array
    {
        $result = $this->git(['status', '--porcelain', '--', self::PATH]);

        if (! $result['ok'] || trim($result['output']) === '') {
            return [];
        }

        return array_values(array_filter(array_map(function (string $line): ?array {
            // XY then the path. Matched rather than cut at fixed offsets: the
            // two status columns are " M", "??", "MM" or "R ", and a rename
            // carries an arrow, so counting characters is how a path loses one.
            if (! preg_match('/^\s*(\S{1,2})\s+(.+)$/', $line, $m)) {
                return null;
            }

            // "old -> new" on a rename: the new name is the one that matters.
            $path = str_contains($m[2], ' -> ') ? explode(' -> ', $m[2])[1] : $m[2];

            return ['status' => $m[1], 'path' => trim($path, " \t\"")];
        }, preg_split('/\r\n|\r|\n/', $result['output']))));
    }

    /** Commits on this branch that origin has not got, or null when it cannot be told. */
    private function ahead(): ?int
    {
        $result = $this->git(['rev-list', '--count', '@{u}..HEAD']);

        return $result['ok'] ? (int) trim($result['output']) : null;
    }

    private function lastCommit(): ?array
    {
        $result = $this->git(['log', '-1', '--format=%h%x1f%s%x1f%ar']);

        if (! $result['ok'] || trim($result['output']) === '') {
            return null;
        }

        [$hash, $subject, $when] = array_pad(explode("\x1f", trim($result['output'])), 3, '');

        return ['hash' => $hash, 'subject' => $subject, 'when' => $when];
    }

    /** Who git would sign a commit as, or null when it has not been told. */
    private function identity(): ?array
    {
        $name = $this->git(['config', 'user.name']);
        $email = $this->git(['config', 'user.email']);

        if (! $name['ok'] || ! $email['ok']) {
            return null;
        }

        return ['name' => trim($name['output']), 'email' => trim($email['output'])];
    }

    public function isRepository(): bool
    {
        return $this->git(['rev-parse', '--is-inside-work-tree'])['ok'];
    }

    /**
     * One git command. An array of arguments and no shell, so nothing the
     * designer types can become part of the command.
     *
     * @param  list<string>  $args
     * @return array{ok: bool, output: string}
     */
    private function git(array $args, int $timeout = 30): array
    {
        try {
            $process = new Process(['git', ...$args], $this->root, null, null, $timeout);
            $process->run();

            // Both streams, because git says most of what matters on stderr.
            // Only the right-hand end is trimmed: `status --porcelain` puts a
            // space in the first column for an unstaged change, and trimming
            // both ends would eat it and take a character off the path with it.
            $output = implode("\n", array_filter([
                rtrim($process->getOutput()),
                rtrim($process->getErrorOutput()),
            ], fn (string $part): bool => $part !== ''));

            return ['ok' => $process->isSuccessful(), 'output' => $output];
        } catch (\Throwable $e) {
            // No git binary at all, or it could not be started.
            return ['ok' => false, 'output' => $e->getMessage()];
        }
    }
}
