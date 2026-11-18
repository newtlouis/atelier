<?php

declare(strict_types=1);

namespace App\Shared\Contrat;

/**
 * Ce sur quoi le mur effet roche se pose.
 *
 * Le revêtement est minéral : il tient sur un support inorganique et jamais
 * sur du bois, qui travaille (règle de Louis, 17/11/2026). Le formulaire ne
 * propose pas le bois ; il ne peut venir que du message du client. La
 * correspondance avec les valeurs du formulaire vit dans l'Aiguillage.
 */
enum SupportMur: string
{
    case Maconne = 'maconne';
    case Enduit = 'enduit';
    case Placo = 'placo';
    /** Réalisable après retrait de la tapisserie, facturé en plus. */
    case Tapisserie = 'tapisserie';
    case Bois = 'bois';

    public function estRealisable(): bool
    {
        return self::Bois !== $this;
    }
}
