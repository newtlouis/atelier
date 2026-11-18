<?php

declare(strict_types=1);

namespace App\Extraction\Cout;

/**
 * Records what each call to the model consumed.
 *
 * An interface, because the extractor has no business knowing whether we write
 * to a database, to a file or nowhere at all — and because the tests must not
 * need a database to assert on a call.
 */
interface JournalDesAppels
{
    public function enregistre(UsageAppel $usage): void;
}
