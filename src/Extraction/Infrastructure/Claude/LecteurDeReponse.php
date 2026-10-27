<?php

declare(strict_types=1);

namespace App\Extraction\Infrastructure\Claude;

use App\Extraction\ExtractionImpossible;
use App\Shared\Contrat\DemandeExtraite;
use App\Shared\Contrat\FamilleProduit;
use JsonException;

/**
 * Transforme la réponse JSON du modèle en objet du domaine.
 *
 * Séparé de l'appel réseau pour une raison précise : c'est ici que se trouve
 * tout ce qui peut mal tourner — un champ absent, un type inattendu, une
 * famille inconnue, une dimension négative. Isolé, ce code se teste sur des
 * dizaines de réponses malformées sans dépenser un centime ni dépendre du
 * réseau.
 *
 * Une sortie structurée *garantit* la forme, mais pas la cohérence : rien
 * n'empêche un modèle de renvoyer une longueur de -500 mm. La validation reste
 * nécessaire.
 */
final class LecteurDeReponse
{
    private const int DIMENSION_MAX_MM = 10_000;
    private const int SURFACE_MAX_M2 = 1_000;
    private const int DISTANCE_MAX_KM = 20_000;

    public function lit(string $json): DemandeExtraite
    {
        try {
            $brut = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw ExtractionImpossible::reponseHorsSchema('JSON illisible — ' . $e->getMessage());
        }

        if (!\is_array($brut)) {
            throw ExtractionImpossible::reponseHorsSchema('un objet était attendu');
        }

        return new DemandeExtraite(
            famille: $this->famille($brut['famille'] ?? null),
            longueurMm: $this->entierPositif($brut['longueur_mm'] ?? null, 'longueur_mm', self::DIMENSION_MAX_MM),
            largeurMm: $this->entierPositif($brut['largeur_mm'] ?? null, 'largeur_mm', self::DIMENSION_MAX_MM),
            hauteurMm: $this->entierPositif($brut['hauteur_mm'] ?? null, 'hauteur_mm', self::DIMENSION_MAX_MM),
            surfaceMetresCarres: $this->entierPositif($brut['surface_m2'] ?? null, 'surface_m2', self::SURFACE_MAX_M2),
            distanceKm: $this->entierPositif($brut['distance_km'] ?? null, 'distance_km', self::DISTANCE_MAX_KM, zeroAutorise: true),
            champsManquants: $this->listeDeTextes($brut['champs_manquants'] ?? []),
            villeLivraison: $this->texteOuNull($brut['ville_livraison'] ?? null),
        );
    }

    private function famille(mixed $valeur): ?FamilleProduit
    {
        if (null === $valeur) {
            return null;
        }

        if (!\is_string($valeur)) {
            throw ExtractionImpossible::reponseHorsSchema('« famille » doit être une chaîne ou null');
        }

        return FamilleProduit::tryFrom($valeur)
            ?? throw ExtractionImpossible::reponseHorsSchema("famille inconnue : « {$valeur} »");
    }

    private function entierPositif(mixed $valeur, string $champ, int $max, bool $zeroAutorise = false): ?int
    {
        if (null === $valeur) {
            return null;
        }

        if (!\is_int($valeur)) {
            throw ExtractionImpossible::reponseHorsSchema("« {$champ} » doit être un entier ou null");
        }

        if ($valeur < ($zeroAutorise ? 0 : 1)) {
            throw ExtractionImpossible::reponseHorsSchema("« {$champ} » vaut {$valeur}, ce qui est impossible");
        }

        // Une valeur absurde est plus probablement une hallucination ou une
        // erreur d'unité qu'une commande réelle : mieux vaut un échec visible
        // qu'un devis de 40 000 €.
        if ($valeur > $max) {
            throw ExtractionImpossible::reponseHorsSchema(
                "« {$champ} » vaut {$valeur}, au-delà du maximum plausible de {$max}",
            );
        }

        return $valeur;
    }

    /** @return list<string> */
    private function listeDeTextes(mixed $valeur): array
    {
        if (!\is_array($valeur)) {
            throw ExtractionImpossible::reponseHorsSchema('« champs_manquants » doit être une liste');
        }

        $textes = [];
        foreach ($valeur as $element) {
            if (!\is_string($element)) {
                throw ExtractionImpossible::reponseHorsSchema('« champs_manquants » ne doit contenir que des chaînes');
            }

            $textes[] = $element;
        }

        return $textes;
    }

    private function texteOuNull(mixed $valeur): ?string
    {
        if (null === $valeur) {
            return null;
        }

        if (!\is_string($valeur)) {
            throw ExtractionImpossible::reponseHorsSchema('« ville_livraison » doit être une chaîne ou null');
        }

        return '' === trim($valeur) ? null : $valeur;
    }
}
