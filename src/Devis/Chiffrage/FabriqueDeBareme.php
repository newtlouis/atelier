<?php

declare(strict_types=1);

namespace App\Devis\Chiffrage;

use App\Shared\Montant;
use InvalidArgumentException;

/**
 * Construit le barème à partir de la configuration.
 *
 * Valide sévèrement : une clé absente ou mal typée fait échouer le démarrage
 * de l'application, pas le premier devis. Une erreur de configuration
 * découverte en production par un client qui reçoit un prix faux est le pire
 * scénario possible pour ce projet.
 */
final class FabriqueDeBareme
{
    /** @param array<string, mixed> $configuration */
    public static function creer(array $configuration): Bareme
    {
        return new Bareme(
            prixMetreCarrePlateau: self::montant($configuration, 'prix_m2_plateau_centimes'),
            prixMetreCarreMur: self::montant($configuration, 'prix_m2_mur_centimes'),
            minimumDeCommande: self::montant($configuration, 'minimum_commande_centimes'),
            seuilLivraisonOfferte: self::montant($configuration, 'seuil_livraison_offerte_centimes'),
            distanceMaxLivraisonOfferteKm: self::entier($configuration, 'distance_max_livraison_offerte_km'),
            tarifKilometrique: self::montant($configuration, 'tarif_kilometrique_centimes'),
            coutJourneeCamionnette: self::montant($configuration, 'cout_journee_camionnette_centimes'),
            coutNuitee: self::montant($configuration, 'cout_nuitee_centimes'),
            tauxTvaCentiemes: self::entier($configuration, 'taux_tva_centiemes'),
            mentionFranchiseTva: self::texteOuNull($configuration, 'mention_franchise_tva'),
        );
    }

    /** @param array<string, mixed> $configuration */
    private static function montant(array $configuration, string $cle): Montant
    {
        return Montant::enCentimes(self::entier($configuration, $cle));
    }

    /** @param array<string, mixed> $configuration */
    private static function entier(array $configuration, string $cle): int
    {
        $valeur = $configuration[$cle] ?? throw new InvalidArgumentException(
            "Barème incomplet : la clé « {$cle} » est absente de la configuration.",
        );

        if (!\is_int($valeur)) {
            throw new InvalidArgumentException(
                "Barème invalide : « {$cle} » doit être un entier.",
            );
        }

        return $valeur;
    }

    /** @param array<string, mixed> $configuration */
    private static function texteOuNull(array $configuration, string $cle): ?string
    {
        $valeur = $configuration[$cle] ?? null;

        if (null === $valeur) {
            return null;
        }

        if (!\is_string($valeur)) {
            throw new InvalidArgumentException("Barème invalide : « {$cle} » doit être une chaîne.");
        }

        return $valeur;
    }
}
