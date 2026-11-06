<?php

namespace App\Tests\Docs;

use PHPUnit\Framework\TestCase;

class DocsConsistencyTest extends TestCase
{
    private const ROOT = __DIR__.'/../..';

    public function testRequiredDocsExist(): void
    {
        foreach (['README.md', 'docs/arborescence.md', 'docs/versions.md', 'docs/docker.md', 'docs/ci-cd.md', 'docs/formations/index.md'] as $file) {
            self::assertFileExists(self::ROOT.'/'.$file);
        }

        // Ce niveau n'a pas de base de données.
        self::assertFileDoesNotExist(self::ROOT.'/docs/base-de-donnees.md');
    }

    public function testRelativeLinksResolve(): void
    {
        $checked = 0;
        $broken = [];
        foreach ($this->markdownFiles() as $file) {
            foreach ($this->relativeLinks($file) as $link) {
                ++$checked;
                if (null === $this->resolve($file, $link)) {
                    $broken[] = $this->fromRoot($file).' -> '.$link;
                }
            }
        }

        self::assertGreaterThan(0, $checked, 'Aucun lien relatif trouvé.');
        self::assertSame([], $broken, 'Liens relatifs cassés.');
    }

    public function testEveryConceptFileLinksToProjectCode(): void
    {
        $files = glob(self::ROOT.'/docs/formations/0*.md') ?: [];
        self::assertNotEmpty($files);

        foreach ($files as $file) {
            $targets = array_map(fn (string $link): ?string => $this->resolve($file, $link), $this->relativeLinks($file));
            $codeLinks = array_filter($targets, static fn (?string $target): bool => null !== $target
                && 'README.md' !== $target
                && !str_starts_with($target, 'docs/'));

            self::assertNotEmpty($codeLinks, $this->fromRoot($file).' ne renvoie vers aucun fichier du projet.');
        }
    }

    public function testVersionsMatchLockFile(): void
    {
        $versions = $this->read('docs/versions.md');
        $composer = json_decode($this->read('composer.json'), true, flags: \JSON_THROW_ON_ERROR);
        $lock = json_decode($this->read('composer.lock'), true, flags: \JSON_THROW_ON_ERROR);

        $locked = array_column([...$lock['packages'], ...$lock['packages-dev']], 'version', 'name');
        $direct = array_keys([...$composer['require'], ...$composer['require-dev']]);
        $packages = array_filter($direct, static fn (string $name): bool => str_contains($name, '/'));
        self::assertNotEmpty($packages);

        foreach ($packages as $package) {
            self::assertArrayHasKey($package, $locked);
            self::assertVersionRow($package, $locked[$package], $versions);
        }

        self::assertSame(1, preg_match('/^FROM dunglas\/frankenphp:([\d.]+)-php([\d.]+)-/m', $this->read('Dockerfile'), $image));
        self::assertVersionRow('FrankenPHP', $image[1], $versions);
        self::assertVersionRow('PHP', $image[2], $versions);
        self::assertVersionRow('Symfony', $composer['extra']['symfony']['require'], $versions);
    }

    /**
     * Cherche une ligne de tableau : le nom et la version doivent être sur la même ligne.
     */
    private static function assertVersionRow(string $name, string $version, string $markdown): void
    {
        $row = sprintf('/^\|\s*`?%s`?\s*\|\s*`?%s`?\s*\|/m', preg_quote($name, '/'), preg_quote($version, '/'));

        self::assertMatchesRegularExpression($row, $markdown, sprintf('versions.md doit indiquer %s en version %s.', $name, $version));
    }

    /**
     * @return list<string>
     */
    private function markdownFiles(): array
    {
        $files = [self::ROOT.'/README.md'];
        if (!is_dir(self::ROOT.'/docs')) {
            return $files;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT.'/docs', \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ('md' === $file->getExtension()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Renvoie les cibles des liens Markdown, sans les URL absolues ni les ancres internes.
     *
     * @return list<string>
     */
    private function relativeLinks(string $file): array
    {
        $markdown = is_file($file) ? file_get_contents($file) : '';
        $withoutFences = preg_replace('/^```.*?^```/ms', '', $markdown);
        preg_match_all('/\]\(([^)\s]*)[^)]*\)/', $withoutFences, $matches);

        $links = [];
        foreach ($matches[1] as $target) {
            if (!preg_match('#^https?://#', $target) && !str_starts_with($target, '#')) {
                $links[] = $target;
            }
        }

        return $links;
    }

    /**
     * Renvoie le chemin de la cible depuis la racine, ou null si elle n'existe pas dans le projet.
     */
    private function resolve(string $file, string $link): ?string
    {
        $target = strtok($link, '#');
        $path = false === $target ? false : realpath(dirname($file).'/'.$target);
        if (false === $path) {
            return null;
        }

        $root = realpath(self::ROOT);
        if ($path !== $root && !str_starts_with($path, $root.'/')) {
            return null;
        }

        return substr($path, strlen($root) + 1);
    }

    private function fromRoot(string $file): string
    {
        return substr(realpath($file) ?: $file, strlen(realpath(self::ROOT)) + 1);
    }

    private function read(string $file): string
    {
        return file_exists(self::ROOT.'/'.$file) ? file_get_contents(self::ROOT.'/'.$file) : '';
    }
}
