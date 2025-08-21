<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use GlobIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SplFileInfo;

use function dirname;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function unlink;

#[CoversClass(Translator::class)]
final class TranslatorTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = dirname(__DIR__).'/fixtures/translations';

        if (!is_dir($this->fixturesDir)) {
            mkdir($this->fixturesDir, 0777, true);
        }

        file_put_contents($this->fixturesDir.'/en.php', "<?php return ['name' => 'Name', 'email' => 'Email'];");
        file_put_contents($this->fixturesDir.'/fr.php', "<?php return ['name' => 'Nom'];");
    }

    protected function tearDown(): void
    {
        /** @var SplFileInfo $fileInfo */
        foreach (new GlobIterator($this->fixturesDir.'/*.php') as $fileInfo) {
            unlink($fileInfo->getRealPath());
        }

        rmdir($this->fixturesDir);
        rmdir(dirname($this->fixturesDir));
    }

    #[Test]
    private function translator(string $locale, string $fallback = 'en'): Translator
    {
        return new Translator($locale, $fallback, $this->fixturesDir);
    }

    #[Test]
    public function it_can_translate_from_a_key(): void
    {
        self::assertSame('Name', $this->translator('en')->translate('name'));
    }

    #[Test]
    public function it_uses_the_fallback_locale_if_present(): void
    {
        self::assertSame('Email', $this->translator('fr')->translate('email'));
    }

    #[Test]
    public function it_returns_the_original_text_in_absence_of_a_fallback_translation(): void
    {
        self::assertSame('unknown_key', $this->translator('fr')->translate('unknown_key'));
    }

    #[Test]
    public function it_translate_the_array_keys(): void
    {
        $input = [
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ];

        $output = $this->translator('fr')->translateArrayKeys($input);

        self::assertSame([
            'Nom' => 'Alice',
            'Email' => 'alice@example.com',
        ], $output);
    }

    #[Test]
    public function it_fails_to_instantiate_if_the_directory_does_not_exists(): void
    {
        $this->expectException(InvalidArgument::class);

        new Translator('en', 'en', __DIR__.'/does_not_exist');
    }
}
