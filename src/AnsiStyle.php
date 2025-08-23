<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

enum AnsiStyle: string
{
    // Text effects
    case Reset = "\033[0m";
    case Bold = "\033[1m";
    case Dim = "\033[2m";
    case Underline = "\033[4m";
    case Blink = "\033[5m";
    case Reverse = "\033[7m";

    // Foreground colors
    case Black = "\033[30m";
    case Red = "\033[31m";
    case Green = "\033[32m";
    case Yellow = "\033[33m";
    case Blue = "\033[34m";
    case Magenta = "\033[35m";
    case Cyan = "\033[36m";
    case White = "\033[37m";

    // Background colors
    case BlackBg = "\033[40m";
    case RedBg = "\033[41m";
    case GreenBg = "\033[42m";
    case YellowBg = "\033[43m";
    case BlueBg = "\033[44m";
    case MagentaBg = "\033[45m";
    case CyanBg = "\033[46m";
    case WhiteBg = "\033[47m";
}
