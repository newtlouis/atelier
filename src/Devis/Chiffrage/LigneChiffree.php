<?php

declare(strict_types=1);

namespace App\Devis\Chiffrage;

use App\Shared\Montant;

/**
 * Une ligne de devis, telle qu'elle apparaîtra sur le document.
 *
 * Le libellé est calculé avec le montant, et non reconstitué à l'affichage :
 * un devis envoyé doit pouvoir être relu à l'identique dans deux ans, même si
 * le barème a changé entre-temps.
 */
final readonly class LigneChiffree
{
    public function __construct(
        public string $libelle,
        public Montant $montant,
    ) {}
}
