<?php

declare(strict_types=1);

namespace App\Devis\Chiffrage;

use App\Shared\Contrat\DemandeExtraite;
use DomainException;

/**
 * La demande n'a pas assez d'information pour être chiffrée.
 *
 * Ce n'est pas une panne : c'est le cas normal quand un client écrit « je
 * voudrais une table ». La bonne réponse est de lui poser les questions
 * manquantes, pas de deviner.
 */
final class DemandeIncomplete extends DomainException
{
    public function __construct(public readonly DemandeExtraite $demande)
    {
        parent::__construct($demande->questionsAPoser());
    }
}
