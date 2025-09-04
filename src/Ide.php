<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use function http_build_query;
use function is_string;
use function ltrim;
use function strtolower;

enum Ide: string
{
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

    public function uri(CallLocation $location, ?string $project = null): string
    {
        return match ($this) {
            self::JetBrains => 'jetbrains://php-storm/navigate/reference?'.http_build_query(['project' => $project, 'path' => $location->path, 'line' => $location->line]),
            self::VSCode => 'vscode://file/'.ltrim((string) $location->path, '/').':'.$location->line,
            default => 'phpstorm://open?'.http_build_query(['file' => $location->path, 'line' => $location->line]),
        };
    }

    public function path(CallLocation $location): string
    {
        return $location->path.':'.$location->line;
    }

    public function link(CallLocation $location, ?string $project = null, AnsiStyle ...$styles): string
    {
        $path = $this->path($location);
        if (Environment::current()->isCli()) {
            return $path;
        }

        return '<a class="'.AnsiStyle::inlineClasses(...$styles).'" href="'.$this->uri($location, $project).'">'.$path.'</a>';
    }
}
