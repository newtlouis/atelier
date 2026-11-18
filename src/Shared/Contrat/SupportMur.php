<?php

declare(strict_types=1);

namespace App\Shared\Contrat;

/**
 * Ce sur quoi le mur effet roche se pose.
 *
 * Le revêtement est minéral : il tient sur un support inorganique et jamais
 * sur du bois, qui travaille (règle de Louis, 17/11/2026). Le formulaire ne
 * propose pas le bois ; il ne peut venir que du message du client.
 */
enum SupportMur: string
{
    case Maconne = 'maconne';
    case Enduit = 'enduit';
    case Placo = 'placo';
    /** Réalisable après retrait de la tapisserie, facturé en plus. */
    case Tapisserie = 'tapisserie';
    case Bois = 'bois';

    /**
     * Les valeurs du champ « support » du formulaire du site. « autre » et
     * « je ne sais pas » (valeur vide) ne disent rien : null, donc une question.
     */
    public static function depuisFormulaire(?string $valeur): ?self
    {
        return match ($valeur) {
            'mur-maconne' => self::Maconne,
            'enduit-peinture' => self::Enduit,
            'cloison' => self::Placo,
            'tapisserie' => self::Tapisserie,
            default => null,
        };
    }

    public function estRealisable(): bool
    {
        return self::Bois !== $this;
    }
}
