<?php

declare(strict_types=1);

namespace App\Extraction\Infrastructure;

use App\Extraction\Cout\JournalDesAppels;
use App\Extraction\Cout\UsageAppel;

/**
 * Keeps nothing. Useful in tests, and for as long as persistence does not
 * exist: the extractor works without it, tracing is a separate concern.
 */
final class JournalNul implements JournalDesAppels
{
    /** @var list<UsageAppel> */
    private array $usages = [];

    public function enregistre(UsageAppel $usage): void
    {
        $this->usages[] = $usage;
    }

    /** @return list<UsageAppel> What was recorded, for the tests. */
    public function usages(): array
    {
        return $this->usages;
    }
}
