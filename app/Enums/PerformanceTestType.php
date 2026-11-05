<?php

namespace App\Enums;

enum PerformanceTestType: string
{
    case Backtest = 'backtest';
    case ForwardTest = 'forward_test';
}
