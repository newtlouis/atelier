<?php

declare(strict_types=1);

namespace App\Devis\Chiffrage;

use App\Shared\Argent\Montant;
use DomainException;

final class CommandeSousLeMinimum extends DomainException
{
    public function __construct(
        public readonly Montant $montantDemande,
        public readonly Montant $minimum,
    ) {
        parent::__construct(
            \sprintf(
                'La commande atteint %s, le minimum est de %s.',
                $montantDemande,
                $minimum,
            ),
        );
    }
}
