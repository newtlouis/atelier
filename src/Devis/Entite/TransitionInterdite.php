<?php

declare(strict_types=1);

namespace App\Devis\Entite;

use DomainException;

final class TransitionInterdite extends DomainException
{
    public function __construct(
        public readonly EtatDevis $etatActuel,
        public readonly EtatDevis $etatAttendu,
        public readonly string $action,
    ) {
        parent::__construct(
            \sprintf(
                'Impossible de %s un devis « %s » : il doit être « %s ».',
                $action,
                $etatActuel->value,
                $etatAttendu->value,
            ),
        );
    }
}
