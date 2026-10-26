<?php

declare(strict_types=1);

namespace App\Devis\Chiffrage;

use InvalidArgumentException;

/**
 * Ce qu'il faut savoir pour chiffrer une table.
 *
 * La hauteur est conservée alors qu'elle n'entre pas dans le prix : elle décrit
 * la pièce, et la question de savoir si elle doit influencer le tarif reste
 * ouverte (voir JOURNAL.md).
 */
final readonly class DemandeTable
{
    public function __construct(
        public int $longueurMm,
        public int $largeurMm,
        public int $hauteurMm,
        public int $distanceLivraisonKm,
    ) {
        if ($distanceLivraisonKm < 0) {
            throw new InvalidArgumentException("Distance négative : {$distanceLivraisonKm} km.");
        }
    }
}
