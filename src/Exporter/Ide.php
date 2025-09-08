<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Exporter;

use Bakame\Stackwatch\CallLocation;

use function http_build_query;
use function is_string;
use function ltrim;
use function str_replace;
use function strtolower;

enum Ide: string
{
    private const PROJECT = 'UNKNOWN_PROJECT';

    case PhpStorm = 'phpstorm';
    case JetBrains = 'jetbrains';
    case VSCode = 'vscode';

    public static function fromEnv(?string $default = null): self
    {
        $value = $default;
        if (isset($_GET['ide']) && is_string($_GET['ide'])) {
            $value = $_GET['ide'];
        } elseif (isset($_ENV['IDE']) && is_string($_ENV['IDE'])) {
            $value = $_ENV['IDE'];
        }

        return self::tryFrom(strtolower((string) $value)) ?? self::PhpStorm;
    }

    public function uri(CallLocation $location): string
    {
        return match ($this) {
            self::JetBrains => 'jetbrains://php-storm/navigate/reference?'.http_build_query(['project' => self::PROJECT, 'path' => $location->path, 'line' => $location->line]),
            self::VSCode => 'vscode://file/'.ltrim((string) $location->path, '/').':'.$location->line,
            default => 'phpstorm://open?'.http_build_query(['file' => $location->path, 'line' => $location->line]),
        };
    }

    public function path(CallLocation $location): string
    {
        return str_replace('\\', '/', (string) $location->path).':'.$location->line;
    }
}
