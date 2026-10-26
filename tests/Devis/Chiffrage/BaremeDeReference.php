<?php

declare(strict_types=1);

namespace App\Tests\Devis\Chiffrage;

use App\Devis\Chiffrage\Bareme;
use App\Shared\Argent\Montant;

/**
 * Le barème réel de Louis Michel Design, au 26/10/2026.
 *
 * Les tests s'appuient dessus pour que leurs chiffres soient vérifiables à la
 * main : une table de 120 × 60 se vend 1 800 €, et ça doit se lire dans le test.
 */
final class BaremeDeReference
{
    public static function creer(int $tauxTvaCentiemes = 0): Bareme
    {
        return new Bareme(
            // 1 800 € pour 7 200 cm² = 0,25 €/cm² = 2 500 €/m².
            prixMetreCarrePlateau: Montant::enEuros(2_500),
            prixMetreCarreMur: Montant::enEuros(450),
            minimumDeCommande: Montant::enEuros(1_200),
            seuilLivraisonOfferte: Montant::enEuros(2_000),
            distanceMaxLivraisonOfferteKm: 1_000,
            tarifKilometrique: Montant::enCentimes(70),
            coutJourneeCamionnette: Montant::enEuros(100),
            coutNuitee: Montant::enEuros(90),
            tauxTvaCentiemes: $tauxTvaCentiemes,
            mentionFranchiseTva: 0 === $tauxTvaCentiemes
                ? 'TVA non applicable, art. 293 B du CGI'
                : null,
        );
    }
}
