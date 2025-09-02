<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

enum AggregatorMode: string
{
    case Average = 'average';
    case Median = 'median';
    case Minimum = 'minimum';
    case Maximum  = 'maximum';
    case Sum  = 'sum';
    case Range = 'range';
}
