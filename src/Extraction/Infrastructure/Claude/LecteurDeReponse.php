<?php

declare(strict_types=1);

namespace App\Extraction\Infrastructure\Claude;

use App\Extraction\ExtractionImpossible;
use App\Shared\Contrat\DemandeExtraite;
use App\Shared\Contrat\EnvoiFormulaire;
use App\Shared\Contrat\FamilleProduit;
use App\Shared\Contrat\NatureDemande;
use App\Shared\Contrat\SupportMur;
use JsonException;

/**
 * Turns the model's JSON response into a domain object.
 *
 * Kept apart from the network call for one precise reason: everything that can
 * go wrong lives here — a missing field, an unexpected type, an unknown product
 * family, a negative dimension. Isolated, this code can be tested against dozens
 * of malformed responses without spending a cent or touching the network.
 *
 * A structured output *guarantees* shape, but not coherence: nothing stops a
 * model from returning a length of -500 mm. Validation remains necessary.
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
            nature: $this->nature($brut['nature'] ?? null),
            support: $this->support($brut['support'] ?? null),
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

    private function nature(mixed $valeur): NatureDemande
    {
        if (!\is_string($valeur)) {
            throw ExtractionImpossible::reponseHorsSchema('« nature » doit être une chaîne');
        }

        return NatureDemande::tryFrom($valeur)
            ?? throw ExtractionImpossible::reponseHorsSchema("nature inconnue : « {$valeur} »");
    }

    private function support(mixed $valeur): ?SupportMur
    {
        if (null === $valeur) {
            return null;
        }
        if (!\is_string($valeur)) {
            throw ExtractionImpossible::reponseHorsSchema('« support » doit être une chaîne ou null');
        }

        return SupportMur::tryFrom($valeur)
            ?? throw ExtractionImpossible::reponseHorsSchema("support inconnu : « {$valeur} »");
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

        // An absurd value is more likely a hallucination or a unit mistake than
        // a real order: a visible failure beats a 40,000 EUR quote.
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

        // Cut to the column rather than rejected: a rejected answer is retried
        // by Messenger, and each retry pays for another call.
        return '' === trim($valeur) ? null : mb_substr($valeur, 0, EnvoiFormulaire::VILLE_MAX);
    }
}
