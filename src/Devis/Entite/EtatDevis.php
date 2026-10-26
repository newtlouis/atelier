<?php

declare(strict_types=1);

namespace App\Devis\Entite;

/**
 * Les états possibles d'un devis.
 *
 * Un enum et non des chaînes : une faute de frappe sur « accepte » devient une
 * erreur de compilation au lieu d'un devis invisible dans toutes les requêtes.
 */
enum EtatDevis: string
{
    case Brouillon = 'brouillon';
    case Envoye = 'envoye';
    case Accepte = 'accepte';
    case Refuse = 'refuse';
    case Facture = 'facture';

    /** Un devis non envoyé peut encore être retouché librement. */
    public function estModifiable(): bool
    {
        return self::Brouillon === $this;
    }

    public function estDefinitif(): bool
    {
        return \in_array($this, [self::Refuse, self::Facture], true);
    }
}
