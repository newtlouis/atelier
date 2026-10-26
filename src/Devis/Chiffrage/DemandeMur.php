<?php

declare(strict_types=1);

namespace App\Devis\Chiffrage;

use InvalidArgumentException;

final readonly class DemandeMur
{
    public function __construct(
        public int $surfaceMetresCarres,
        public int $distanceKm,
    ) {
        if ($surfaceMetresCarres <= 0) {
            throw new InvalidArgumentException("Surface invalide : {$surfaceMetresCarres} m².");
        }

        if ($distanceKm < 0) {
            throw new InvalidArgumentException("Distance négative : {$distanceKm} km.");
        }
    }
}
