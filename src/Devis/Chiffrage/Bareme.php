<?php

declare(strict_types=1);

namespace App\Devis\Chiffrage;

use App\Shared\Montant;
use InvalidArgumentException;

/**
 * Le barème : toutes les valeurs commerciales, en un seul endroit.
 *
 * Rien de tout ceci n'est écrit en dur dans les calculateurs. Un tarif change
 * sans toucher au code et sans redéployer — c'est une donnée de configuration
 * (voir config/packages/atelier.yaml), pas une règle de programmation.
 */
final readonly class Bareme
{
    public function __construct(
        /** Prix du mètre carré de plateau, en centimes. */
        public Montant $prixMetreCarrePlateau,
        /** Prix du mètre carré de mur posé, en centimes. */
        public Montant $prixMetreCarreMur,
        /** En dessous de ce total, la commande est refusée. */
        public Montant $minimumDeCommande,
        /** À partir de ce total, la livraison d'une table est offerte… */
        public Montant $seuilLivraisonOfferte,
        /** …à condition de ne pas dépasser cette distance. */
        public int $distanceMaxLivraisonOfferteKm,
        /** Tarif facturé au kilomètre, appliqué à l'aller-retour. */
        public Montant $tarifKilometrique,
        /** Location d'un utilitaire, par journée entamée. */
        public Montant $coutJourneeCamionnette,
        /** Une nuitée sur place, hébergement compris. */
        public Montant $coutNuitee,
        /** Taux de TVA en centièmes de point : 0 en franchise, 2000 pour 20 %. */
        public int $tauxTvaCentiemes,
        /** Mention à porter sur le devis quand le taux est nul. */
        public ?string $mentionFranchiseTva,
    ) {
        if ($tauxTvaCentiemes < 0) {
            throw new InvalidArgumentException("Taux de TVA négatif : {$tauxTvaCentiemes}.");
        }

        if (0 === $tauxTvaCentiemes && null === $mentionFranchiseTva) {
            throw new InvalidArgumentException(
                'Un taux de TVA nul exige une mention légale justifiant la franchise.',
            );
        }
    }

    public function estEnFranchiseDeTva(): bool
    {
        return 0 === $this->tauxTvaCentiemes;
    }
}
