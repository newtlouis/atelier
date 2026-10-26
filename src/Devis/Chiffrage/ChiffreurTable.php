<?php

declare(strict_types=1);

namespace App\Devis\Chiffrage;

use App\Shared\Montant;
use App\Shared\Surface;

/**
 * Chiffre une table : la surface du plateau, puis la livraison.
 *
 * La livraison est offerte si deux conditions sont réunies — un montant
 * suffisant ET une distance raisonnable. Les deux, pas l'une ou l'autre :
 * offrir 1 500 km de transport sur une table à 2 000 € coûterait plus cher
 * que la marge.
 */
final readonly class ChiffreurTable
{
    public function __construct(private Bareme $bareme) {}

    public function chiffre(DemandeTable $demande): Chiffrage
    {
        $plateau = Surface::rectangleEnMillimetres($demande->longueurMm, $demande->largeurMm);
        $prixTable = $this->prixDuPlateau($plateau);

        if ($prixTable->estInferieurA($this->bareme->minimumDeCommande)) {
            throw new CommandeSousLeMinimum($prixTable, $this->bareme->minimumDeCommande);
        }

        $lignes = [
            new LigneChiffree(
                \sprintf(
                    'Table effet roche %d × %d × %d cm — plateau %d cm²',
                    intdiv($demande->longueurMm, 10),
                    intdiv($demande->largeurMm, 10),
                    intdiv($demande->hauteurMm, 10),
                    $plateau->centimetresCarres,
                ),
                $prixTable,
            ),
        ];

        $lignes[] = $this->ligneDeLivraison($prixTable, $demande->distanceLivraisonKm);

        return new Chiffrage(
            $lignes,
            $this->bareme->tauxTvaCentiemes,
            $this->bareme->estEnFranchiseDeTva() ? $this->bareme->mentionFranchiseTva : null,
        );
    }

    /**
     * Le barème est au mètre carré, la surface en centimètres carrés :
     * 1 m² = 10 000 cm². L'arrondi est au centime le plus proche.
     */
    private function prixDuPlateau(Surface $plateau): Montant
    {
        return Montant::enCentimes(
            intdiv(
                $plateau->centimetresCarres * $this->bareme->prixMetreCarrePlateau->centimes + 5_000,
                10_000,
            ),
        );
    }

    private function ligneDeLivraison(Montant $prixTable, int $distanceKm): LigneChiffree
    {
        $offerte = $prixTable->estSuperieurOuEgalA($this->bareme->seuilLivraisonOfferte)
            && $distanceKm <= $this->bareme->distanceMaxLivraisonOfferteKm;

        if ($offerte) {
            return new LigneChiffree(
                \sprintf('Livraison offerte (%d km)', $distanceKm),
                Montant::zero(),
            );
        }

        // Aller-retour : le trajet est fait deux fois.
        return new LigneChiffree(
            \sprintf('Livraison — %d km aller-retour', $distanceKm * 2),
            $this->bareme->tarifKilometrique->multiplieParEntier($distanceKm * 2),
        );
    }
}
