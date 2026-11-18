<?php

declare(strict_types=1);

namespace App\Application;

/** Ce qu'un import a fait, en nombres : rien qui identifie un client. */
final readonly class BilanImport
{
    public function __construct(
        public int $nouveaux,
        public int $dejaConnus,
        public int $vides,
    ) {}
}
