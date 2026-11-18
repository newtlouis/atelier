<?php

declare(strict_types=1);

namespace App\Extraction\Lecture;

/**
 * Ce qu'un message réduit à des dimensions permet de lire, en millimètres.
 * La hauteur est absente quand le client n'a donné que deux côtés.
 */
final readonly class DimensionsLues
{
    public function __construct(
        public int $longueurMm,
        public int $largeurMm,
        public ?int $hauteurMm = null,
    ) {}
}
