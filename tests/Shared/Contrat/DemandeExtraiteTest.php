<?php

declare(strict_types=1);

namespace App\Tests\Shared\Contrat;

use App\Shared\Contrat\DemandeExtraite;
use App\Shared\Contrat\FamilleProduit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `estExploitable()` est la garde qui empêche un devis d'être calculé sur une
 * donnée inventée. Elle est donc éprouvée champ par champ : retirer n'importe
 * lequel des champs requis doit suffire à bloquer le chiffrage.
 */
final class DemandeExtraiteTest extends TestCase
{
    public function testUneTableComplèteEstExploitable(): void
    {
        self::assertTrue(self::table()->estExploitable());
    }

    public function testUnMurCompletEstExploitable(): void
    {
        self::assertTrue(self::mur()->estExploitable());
    }

    #[DataProvider('champsRequisDUneTable')]
    public function testUneTableAmputeeDUnChampNEstPasExploitable(string $champAbsent): void
    {
        self::assertFalse(self::table(sans: $champAbsent)->estExploitable());
    }

    /** @return iterable<string, array{string}> */
    public static function champsRequisDUneTable(): iterable
    {
        yield 'sans longueur' => ['longueur'];
        yield 'sans largeur' => ['largeur'];
        yield 'sans hauteur' => ['hauteur'];
        yield 'sans distance' => ['distance'];
    }

    #[DataProvider('champsRequisDUnMur')]
    public function testUnMurAmputeDUnChampNEstPasExploitable(string $champAbsent): void
    {
        self::assertFalse(self::mur(sans: $champAbsent)->estExploitable());
    }

    /** @return iterable<string, array{string}> */
    public static function champsRequisDUnMur(): iterable
    {
        yield 'sans surface' => ['surface'];
        yield 'sans distance' => ['distance'];
    }

    public function testSansFamilleRienNEstExploitable(): void
    {
        $demande = new DemandeExtraite(
            famille: null,
            longueurMm: 1_200,
            largeurMm: 600,
            hauteurMm: 350,
            surfaceMetresCarres: 6,
            distanceKm: 100,
        );

        self::assertFalse($demande->estExploitable());
    }

    public function testUnChampManquantDeclareSuffitABloquer(): void
    {
        // Toutes les données sont là, mais le modèle signale un doute :
        // on lui fait confiance dans ce sens-là.
        self::assertFalse(
            self::table(champsManquants: ['finition'])->estExploitable(),
        );
    }

    public function testUneDemandeExploitableNaPasDeQuestionAPoser(): void
    {
        self::assertSame('', self::table()->questionsAPoser());
    }

    public function testSansPrecisionLaQuestionResteGenerique(): void
    {
        // Le modèle n'a pas nommé ce qui manque : on ne peut pas être précis,
        // mais on ne doit pas non plus prétendre que tout va bien.
        self::assertSame(
            'Il manque : les informations nécessaires au chiffrage.',
            self::table(sans: 'hauteur')->questionsAPoser(),
        );
    }

    /** @param list<string> $champsManquants */
    private static function table(?string $sans = null, array $champsManquants = []): DemandeExtraite
    {
        return new DemandeExtraite(
            famille: FamilleProduit::Table,
            longueurMm: 'longueur' === $sans ? null : 1_200,
            largeurMm: 'largeur' === $sans ? null : 600,
            hauteurMm: 'hauteur' === $sans ? null : 350,
            surfaceMetresCarres: null,
            distanceKm: 'distance' === $sans ? null : 100,
            champsManquants: $champsManquants,
        );
    }

    /** @param list<string> $champsManquants */
    private static function mur(?string $sans = null, array $champsManquants = []): DemandeExtraite
    {
        return new DemandeExtraite(
            famille: FamilleProduit::Mur,
            longueurMm: null,
            largeurMm: null,
            hauteurMm: null,
            surfaceMetresCarres: 'surface' === $sans ? null : 6,
            distanceKm: 'distance' === $sans ? null : 200,
            champsManquants: $champsManquants,
        );
    }
}
