<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

enum AggregationType: string
{
    case Average = 'average';
    case Median = 'median';
    case Minimum = 'minimum';
    case Maximum  = 'maximum';
    case Sum  = 'sum';
    case Range = 'range';
    case Variance = 'variance';
    case StdDev = 'stdDev';
    case CoefVar = 'coef_var';
}
