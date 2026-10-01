<?php

declare(strict_types=1);

namespace EidCloud\Memory\Models;

enum MemoryTier: string
{
    case SHORT_TERM = 'short_term';
    case WORKING = 'working';
    case LONG_TERM = 'long_term';
    case EPISODIC = 'episodic';
}
