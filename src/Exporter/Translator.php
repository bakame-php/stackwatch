<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Exporter;

use Bakame\Stackwatch\InvalidArgument;

use function file_exists;
use function is_dir;
use function is_readable;
use function trim;

final class Translator
{
    public readonly string $locale;
    public readonly string $fallbackLocale;
    /** @var non-empty-string */
    public readonly string $rootDirectory;
    /** @var array<string, array<string, string>> */
    private array $translations = [];

    public function __construct(string $locale = 'en', string $fallback = 'en', string $rootDirectory = '')
    {
        $this->locale = trim($locale);
        $this->fallbackLocale = trim($fallback);
        $rootDirectory = trim($rootDirectory);
        if ('' === $rootDirectory) {
            $rootDirectory = dirname(__DIR__).'/Resources/translations';
        }

        (is_dir($rootDirectory) && is_readable($rootDirectory)) || throw new InvalidArgument('The root directory "'.$rootDirectory.'" does not exist or is not readable.');
        $this->rootDirectory = $rootDirectory;
        $this->load($this->locale);
        $this->load($this->fallbackLocale);
    }

    private function load(string $locale): void
    {
        if (!isset($this->translations[$locale])) {
            $file = $this->rootDirectory."/{$locale}.php";

            /** @var array<string, string> $data */
            $data = file_exists($file) ? require $file : [];

            $this->translations[$locale] = $data;
        }
    }

    public function translate(string $key): string
    {
        return $this->translations[$this->locale][$key]
            ?? $this->translations[$this->fallbackLocale][$key]
            ?? $key;
    }

    /**
     * @param array<string, string> $input
     *
     * @return array<string, string>
     */
    public function translateArrayKeys(array $input): array
    {
        $output = [];
        foreach ($input as $key => $value) {
            $output[$this->translate($key)] = $value;
        }

        return $output;
    }
}
