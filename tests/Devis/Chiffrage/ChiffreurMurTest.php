<?php

declare(strict_types=1);

namespace App\Tests\Devis\Chiffrage;

use App\Devis\Chiffrage\BaremeNuitees;
use App\Devis\Chiffrage\ChiffreurMur;
use App\Devis\Chiffrage\CommandeSousLeMinimum;
use App\Devis\Chiffrage\DemandeMur;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ChiffreurMurTest extends TestCase
{
    public function testUnMurDeSixMetresCarresADeuxCentsKilometres(): void
    {
        $chiffrage = $this->chiffreur()->chiffre(
            new DemandeMur(surfaceMetresCarres: 6, distanceKm: 200),
        );

        self::assertSame(270_000, $chiffrage->lignes[0]->montant->centimes); // 6 × 450 €
        self::assertSame(10_000, $chiffrage->lignes[1]->montant->centimes);  // camionnette
        self::assertSame(45_000, $chiffrage->lignes[2]->montant->centimes);  // 5 nuitées × 90 €
        self::assertSame(28_000, $chiffrage->lignes[3]->montant->centimes);  // 400 km × 0,70 €
        self::assertSame(353_000, $chiffrage->totalHorsTaxes->centimes);     // 3 530 €
    }

    public function testLeDeplacementNEstJamaisOffertMemeSurUnGrosChantier(): void
    {
        // 20 m² = 9 000 € : très au-dessus du seuil qui offrirait une livraison
        // de table. Un mur se pose, il ne se livre pas.
        $chiffrage = $this->chiffreur()->chiffre(
            new DemandeMur(surfaceMetresCarres: 20, distanceKm: 50),
        );

        self::assertSame(10_000, $chiffrage->lignes[1]->montant->centimes);
        self::assertSame(90_000, $chiffrage->lignes[2]->montant->centimes); // 10 nuitées
        self::assertSame(7_000, $chiffrage->lignes[3]->montant->centimes);
    }

    #[DataProvider('paliersDeNuitees')]
    public function testLeNombreDeNuiteesSuitLaSurface(int $surfaceM2, int $nuiteesAttendues): void
    {
        self::assertSame($nuiteesAttendues, new BaremeNuitees()->pour($surfaceM2));
    }

    /** @return iterable<string, array{int, int}> */
    public static function paliersDeNuitees(): iterable
    {
        yield '1 m²' => [1, 5];
        yield '6 m², juste sous le premier palier' => [6, 5];
        yield '7 m², le palier bascule' => [7, 8];
        yield '13 m²' => [13, 8];
        yield '14 m², second palier' => [14, 10];
        yield '40 m²' => [40, 10];
    }

    public function testLesLibellesDecriventCeQuiEstFacture(): void
    {
        // Les libellés figurent tels quels sur le devis envoyé au client :
        // ils sont aussi vérifiés que les montants.
        $chiffrage = $this->chiffreur()->chiffre(
            new DemandeMur(surfaceMetresCarres: 6, distanceKm: 200),
        );

        self::assertSame('Mur effet roche — 6 m² posés', $chiffrage->lignes[0]->libelle);
        self::assertSame('Location utilitaire — 1 journée', $chiffrage->lignes[1]->libelle);
        self::assertSame('Hébergement sur place — 5 nuitées', $chiffrage->lignes[2]->libelle);
        self::assertSame('Déplacement — 400 km aller-retour', $chiffrage->lignes[3]->libelle);
    }

    public function testAssujettiUnMurPorteLaTvaEtPerdLaMentionDeFranchise(): void
    {
        $chiffreur = new ChiffreurMur(BaremeDeReference::creer(tauxTvaCentiemes: 2_000));

        $chiffrage = $chiffreur->chiffre(new DemandeMur(surfaceMetresCarres: 6, distanceKm: 200));

        self::assertSame(353_000, $chiffrage->totalHorsTaxes->centimes);
        self::assertSame(70_600, $chiffrage->tva->centimes);
        self::assertNull($chiffrage->mentionFranchiseTva);
    }

    public function testUneSurfaceNulleNEstPasUnChantier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Surface invalide : 0 m².');

        new DemandeMur(surfaceMetresCarres: 0, distanceKm: 10);
    }

    public function testUnTropPetitChantierEstRefuse(): void
    {
        // 1 m² = 450 € + 100 € + 450 € de nuitées + 0 km = 1 000 €, sous le minimum.
        $this->expectException(CommandeSousLeMinimum::class);

        $this->chiffreur()->chiffre(new DemandeMur(surfaceMetresCarres: 1, distanceKm: 0));
    }

    private function chiffreur(): ChiffreurMur
    {
        return new ChiffreurMur(BaremeDeReference::creer());
    }
}
