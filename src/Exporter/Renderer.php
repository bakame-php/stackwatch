<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Exporter;

interface Renderer
{
    /**
     * Format a leader list for console output.
     */
    public function renderCli(): string;

    /**
     * Format a leader list for HTML output.
     */
    public function renderHtml(): string;
}
