<?php

declare(strict_types=1);

namespace App\Devis\Chiffrage;

use App\Shared\Contrat\DemandeExtraite;
use App\Shared\Contrat\FamilleProduit;
use LogicException;

/**
 * Point d'entrée unique du chiffrage : reçoit une demande extraite, choisit le
 * calculateur, et refuse de deviner ce qui manque.
 *
 * C'est ici que passe la frontière entre l'IA et l'argent. En amont, un modèle
 * de langage a lu du texte ; à partir d'ici, tout est déterministe, entier et
 * testé. Aucun champ absent n'est comblé par une valeur par défaut — le refus
 * est explicite.
 */
final readonly class ChiffreurDeDemande
{
    public function __construct(
        private ChiffreurTable $table,
        private ChiffreurMur $mur,
    ) {}

    public function chiffre(DemandeExtraite $demande): Chiffrage
    {
        if (!$demande->estExploitable()) {
            throw new DemandeIncomplete($demande);
        }

        return match ($demande->famille) {
            FamilleProduit::Table => $this->table->chiffre(new DemandeTable(
                longueurMm: $this->exige($demande->longueurMm, 'longueur'),
                largeurMm: $this->exige($demande->largeurMm, 'largeur'),
                hauteurMm: $this->exige($demande->hauteurMm, 'hauteur'),
                distanceLivraisonKm: $this->exige($demande->distanceKm, 'distance'),
            )),
            FamilleProduit::Mur => $this->mur->chiffre(new DemandeMur(
                surfaceMetresCarres: $this->exige($demande->surfaceMetresCarres, 'surface'),
                distanceKm: $this->exige($demande->distanceKm, 'distance'),
            )),
            null => throw new DemandeIncomplete($demande),
        };
    }

    /**
     * `estExploitable()` a déjà vérifié la présence de ces champs ; cette
     * méthode rend la garantie lisible par l'analyse statique plutôt que de
     * forcer le type avec un commentaire.
     */
    private function exige(?int $valeur, string $champ): int
    {
        if (null === $valeur) {
            throw new LogicException(
                "Le champ « {$champ} » est absent alors que la demande se déclare exploitable.",
            );
        }

        return $valeur;
    }
}
