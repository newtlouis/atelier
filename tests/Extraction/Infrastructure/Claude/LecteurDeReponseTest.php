<?php

declare(strict_types=1);

namespace App\Tests\Extraction\Infrastructure\Claude;

use App\Extraction\ExtractionImpossible;
use App\Extraction\Infrastructure\Claude\LecteurDeReponse;
use App\Shared\Contrat\FamilleProduit;
use App\Shared\Contrat\IssueDeLecture;
use App\Shared\Contrat\NatureDemande;
use App\Shared\Contrat\SupportMur;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LecteurDeReponseTest extends TestCase
{
    public function testUneReponseCompleteDevientUneDemandeExploitable(): void
    {
        $demande = new LecteurDeReponse()->lit(<<<'JSON'
            {
                "famille": "table",
                "nature": "projet",
                "support": null,
                "longueur_mm": 1200,
                "largeur_mm": 600,
                "hauteur_mm": 350,
                "surface_m2": null,
                "distance_km": 120,
                "ville_livraison": "Rennes",
                "champs_manquants": []
            }
            JSON);

        self::assertSame(FamilleProduit::Table, $demande->famille);
        self::assertSame(1_200, $demande->longueurMm);
        self::assertSame('Rennes', $demande->villeLivraison);
        self::assertTrue($demande->estExploitable());
    }

    public function testUneHauteurAbsenteRendLaDemandeInexploitable(): void
    {
        $demande = new LecteurDeReponse()->lit(<<<'JSON'
            {
                "famille": "table",
                "nature": "projet",
                "support": null,
                "longueur_mm": 1200,
                "largeur_mm": 600,
                "hauteur_mm": null,
                "surface_m2": null,
                "distance_km": 120,
                "ville_livraison": null,
                "champs_manquants": ["hauteur"]
            }
            JSON);

        self::assertFalse($demande->estExploitable());
        self::assertSame('Il manque : hauteur.', $demande->questionsAPoser());
    }

    public function testUneVilleSansDistanceNEstPasExploitable(): void
    {
        // Le cas le plus courant : « livraison à Bordeaux » sans kilométrage.
        $demande = new LecteurDeReponse()->lit(<<<'JSON'
            {
                "famille": "mur",
                "nature": "projet",
                "support": null,
                "longueur_mm": null,
                "largeur_mm": null,
                "hauteur_mm": null,
                "surface_m2": 9,
                "distance_km": null,
                "ville_livraison": "Bordeaux",
                "champs_manquants": ["distance"]
            }
            JSON);

        self::assertSame('Bordeaux', $demande->villeLivraison);
        self::assertFalse($demande->estExploitable());
    }

    public function testUnModeleQuiSeContrediNEstPasCru(): void
    {
        // Le modèle déclare n'avoir rien oublié, mais la largeur est absente.
        // On croit les données, pas la déclaration.
        $demande = new LecteurDeReponse()->lit(<<<'JSON'
            {
                "famille": "table",
                "nature": "projet",
                "support": null,
                "longueur_mm": 1200,
                "largeur_mm": null,
                "hauteur_mm": 350,
                "surface_m2": null,
                "distance_km": 10,
                "ville_livraison": null,
                "champs_manquants": []
            }
            JSON);

        self::assertFalse($demande->estExploitable());
    }

    #[DataProvider('reponsesMalformees')]
    public function testUneReponseInvalideEstRejeteeAvecUnMotif(string $json, string $motifAttendu): void
    {
        $this->expectException(ExtractionImpossible::class);
        $this->expectExceptionMessageMatches('/' . preg_quote($motifAttendu, '/') . '/');

        new LecteurDeReponse()->lit($json);
    }

    /** @return iterable<string, array{string, string}> */
    public static function reponsesMalformees(): iterable
    {
        yield 'JSON tronqué' => ['{"famille": "table"', 'JSON illisible'];
        yield 'tableau au lieu d objet' => ['"texte"', 'un objet était attendu'];
        yield 'famille inventée' => [
            '{"famille": "escalier", "champs_manquants": []}',
            'famille inconnue',
        ];
        yield 'dimension négative' => [
            '{"famille": "table", "longueur_mm": -500, "champs_manquants": []}',
            'vaut -500, ce qui est impossible',
        ];
        yield 'dimension absurde : erreur d unité probable' => [
            '{"famille": "table", "longueur_mm": 120000, "champs_manquants": []}',
            'au-delà du maximum plausible',
        ];
        yield 'dimension en texte' => [
            '{"famille": "table", "longueur_mm": "1200", "champs_manquants": []}',
            'doit être un entier ou null',
        ];
        yield 'champs_manquants mal typé' => [
            '{"famille": "table", "champs_manquants": "hauteur"}',
            'doit être une liste',
        ];
        yield 'champs_manquants contenant un objet' => [
            '{"famille": "table", "champs_manquants": [{"champ": "hauteur"}]}',
            'que des chaînes',
        ];
    }

    public function testUneDistanceNulleEstValideContrairementAUneLongueurNulle(): void
    {
        // Livraison sur place : 0 km est une vraie valeur.
        $demande = new LecteurDeReponse()->lit(<<<'JSON'
            {
                "famille": "table",
                "nature": "projet",
                "support": null,
                "longueur_mm": 1200,
                "largeur_mm": 600,
                "hauteur_mm": 350,
                "surface_m2": null,
                "distance_km": 0,
                "ville_livraison": null,
                "champs_manquants": []
            }
            JSON);

        self::assertSame(0, $demande->distanceKm);
        self::assertTrue($demande->estExploitable());
    }

    /** Le spam reçu le 12/09/2026, résumé : la nature suffit, aucun champ n'est demandé. */
    public function testUnHorsSujetSignaleParLeModeleNAppelleRien(): void
    {
        $demande = new LecteurDeReponse()->lit(<<<'JSON'
            {
                "famille": null,
                "nature": "hors_sujet",
                "support": null,
                "longueur_mm": null,
                "largeur_mm": null,
                "hauteur_mm": null,
                "surface_m2": null,
                "distance_km": null,
                "ville_livraison": null,
                "champs_manquants": []
            }
            JSON);

        self::assertSame(NatureDemande::HorsSujet, $demande->nature);
        self::assertSame(IssueDeLecture::HorsSujet, $demande->issue());
    }

    public function testUnMurSurDuBoisSignaleParLeModeleEstIrrealisable(): void
    {
        $demande = new LecteurDeReponse()->lit(<<<'JSON'
            {
                "famille": "mur",
                "nature": "projet",
                "support": "bois",
                "longueur_mm": null,
                "largeur_mm": null,
                "hauteur_mm": null,
                "surface_m2": 8,
                "distance_km": 30,
                "ville_livraison": null,
                "champs_manquants": []
            }
            JSON);

        self::assertSame(SupportMur::Bois, $demande->support);
        self::assertSame(IssueDeLecture::Irrealisable, $demande->issue());
    }

    /** @return iterable<string, array{string, string}> */
    public static function natureOuSupportInvalides(): iterable
    {
        yield 'nature absente' => ['{"famille": null, "champs_manquants": []}', '« nature » doit être une chaîne'];
        yield 'nature inconnue' => ['{"famille": null, "nature": "urgent", "champs_manquants": []}', 'nature inconnue : « urgent »'];
        yield 'support mal typé' => ['{"famille": "mur", "nature": "projet", "support": 3, "champs_manquants": []}', '« support » doit être une chaîne ou null'];
        yield 'support inconnu' => ['{"famille": "mur", "nature": "projet", "support": "verre", "champs_manquants": []}', 'support inconnu : « verre »'];
    }

    #[DataProvider('natureOuSupportInvalides')]
    public function testUneNatureOuUnSupportInvalideEstRejete(string $json, string $motif): void
    {
        $this->expectException(ExtractionImpossible::class);
        $this->expectExceptionMessage($motif);

        new LecteurDeReponse()->lit($json);
    }

    /** Found in security review: a city too long for its column. */
    public function testALongCityIsCutToItsColumn(): void
    {
        $json = json_encode([
            'famille' => 'table', 'nature' => 'projet', 'support' => null,
            'longueur_mm' => 1600, 'largeur_mm' => 800, 'hauteur_mm' => null,
            'surface_m2' => null, 'distance_km' => null,
            'ville_livraison' => str_repeat('é', 130), 'champs_manquants' => ['distance'],
        ], \JSON_THROW_ON_ERROR);

        self::assertSame(str_repeat('é', 120), new LecteurDeReponse()->lit($json)->villeLivraison);
    }
}
