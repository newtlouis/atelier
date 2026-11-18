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
 *
 * `hauteurStandard` dit que la hauteur vient de la règle des tables basses et
 * non du client : le devis le lui annonce, pour qu'il puisse en demander une autre.
 */
final readonly class DemandeTable
{
    public function __construct(
        public int $longueurMm,
        public int $largeurMm,
        public int $hauteurMm,
        public int $distanceLivraisonKm,
        public bool $hauteurStandard = false,
    ) {
        if ($distanceLivraisonKm < 0) {
            throw new InvalidArgumentException("Distance négative : {$distanceLivraisonKm} km.");
        }
    }
}
