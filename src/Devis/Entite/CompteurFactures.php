<?php

declare(strict_types=1);

namespace App\Devis\Entite;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Le dernier numéro attribué, par année.
 *
 * Pourquoi une table et pas une séquence PostgreSQL : une séquence ne revient
 * jamais en arrière. Si la transaction qui a consommé le numéro 42 échoue, la
 * suivante prend 43 et le 42 n'existera jamais — un trou. C'est le comportement
 * voulu pour une clé technique, et interdit pour une numérotation de factures
 * (art. 242 nonies A du CGI).
 *
 * Avec une ligne verrouillée en base, le numéro est consommé *dans* la même
 * transaction que la facture : si l'une échoue, l'autre est annulée aussi.
 */
#[ORM\Entity]
#[ORM\Table(name: 'compteur_factures')]
class CompteurFactures
{
    #[ORM\Id]
    #[ORM\Column(type: Types::SMALLINT)]
    private int $annee;

    #[ORM\Column(type: Types::INTEGER)]
    private int $dernierNumero = 0;

    public function __construct(int $annee)
    {
        $this->annee = $annee;
    }

    public function annee(): int
    {
        return $this->annee;
    }

    public function dernierNumero(): int
    {
        return $this->dernierNumero;
    }

    public function numeroSuivant(): int
    {
        return ++$this->dernierNumero;
    }
}
