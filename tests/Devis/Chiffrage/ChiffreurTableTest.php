<?php

declare(strict_types=1);

namespace App\Tests\Devis\Chiffrage;

use App\Devis\Chiffrage\ChiffreurTable;
use App\Devis\Chiffrage\CommandeSousLeMinimum;
use App\Devis\Chiffrage\DemandeTable;
use PHPUnit\Framework\TestCase;

final class ChiffreurTableTest extends TestCase
{
    public function testLaTableDeReferenceSeVend1800Euros(): void
    {
        // 120 × 60 cm = 7 200 cm², à 2 500 €/m².
        $chiffrage = $this->chiffreur()->chiffre(
            new DemandeTable(longueurMm: 1_200, largeurMm: 600, hauteurMm: 350, distanceLivraisonKm: 50),
        );

        self::assertSame(180_000, $chiffrage->lignes[0]->montant->centimes);
        self::assertSame('1 800,00 €', (string) $chiffrage->lignes[0]->montant);
    }

    public function testLaLivraisonEstOfferteAuDessusDeDeuxMilleEurosEtSousMilleKilometres(): void
    {
        $chiffrage = $this->chiffreur()->chiffre(
            new DemandeTable(longueurMm: 1_400, largeurMm: 700, hauteurMm: 350, distanceLivraisonKm: 999),
        );

        // 9 800 cm² × 2 500 €/m² = 2 450 € : au-dessus du seuil.
        self::assertSame(245_000, $chiffrage->lignes[0]->montant->centimes);
        self::assertSame(0, $chiffrage->lignes[1]->montant->centimes);
        self::assertStringContainsString('offerte', $chiffrage->lignes[1]->libelle);
    }

    public function testAuDelaDeMilleKilometresLaLivraisonRedevientPayanteMemeSurUneGrosseCommande(): void
    {
        $chiffrage = $this->chiffreur()->chiffre(
            new DemandeTable(longueurMm: 1_400, largeurMm: 700, hauteurMm: 350, distanceLivraisonKm: 1_001),
        );

        // 1 001 km aller-retour = 2 002 km × 0,70 € = 1 401,40 €.
        self::assertSame(140_140, $chiffrage->lignes[1]->montant->centimes);
        self::assertSame(385_140, $chiffrage->totalHorsTaxes->centimes);
    }

    public function testSousLeSeuilLaLivraisonEstFactureeAuKilometre(): void
    {
        $chiffrage = $this->chiffreur()->chiffre(
            new DemandeTable(longueurMm: 1_000, largeurMm: 600, hauteurMm: 350, distanceLivraisonKm: 100),
        );

        // 6 000 cm² = 1 500 € : sous le seuil de 2 000 €.
        self::assertSame(150_000, $chiffrage->lignes[0]->montant->centimes);
        // 200 km × 0,70 € = 140 €.
        self::assertSame(14_000, $chiffrage->lignes[1]->montant->centimes);
    }

    public function testUneTableTropPetitePasseSousLeMinimumDeCommande(): void
    {
        // 80 × 50 cm = 4 000 cm² = 1 000 €, sous le minimum de 1 200 €.
        $this->expectException(CommandeSousLeMinimum::class);
        $this->expectExceptionMessage('La commande atteint 1 000,00 €, le minimum est de 1 200,00 €.');

        $this->chiffreur()->chiffre(
            new DemandeTable(longueurMm: 800, largeurMm: 500, hauteurMm: 350, distanceLivraisonKm: 10),
        );
    }

    public function testEnFranchiseAucuneTvaNEstAjoutee(): void
    {
        $chiffrage = $this->chiffreur()->chiffre(
            new DemandeTable(longueurMm: 1_200, largeurMm: 600, hauteurMm: 350, distanceLivraisonKm: 50),
        );

        self::assertSame(0, $chiffrage->tva->centimes);
        self::assertTrue($chiffrage->totalHorsTaxes->equivaut($chiffrage->totalToutesTaxes));
        self::assertSame('TVA non applicable, art. 293 B du CGI', $chiffrage->mentionFranchiseTva);
    }

    public function testAssujettiLaTvaEstAjouteeEtLaMentionDisparait(): void
    {
        $chiffreur = new ChiffreurTable(BaremeDeReference::creer(tauxTvaCentiemes: 2_000));

        $chiffrage = $chiffreur->chiffre(
            new DemandeTable(longueurMm: 1_200, largeurMm: 600, hauteurMm: 350, distanceLivraisonKm: 50),
        );

        // 1 800 € + 70 € de livraison = 1 870 € HT, TVA 20 % = 374 €.
        self::assertSame(187_000, $chiffrage->totalHorsTaxes->centimes);
        self::assertSame(37_400, $chiffrage->tva->centimes);
        self::assertSame(224_400, $chiffrage->totalToutesTaxes->centimes);
        self::assertNull($chiffrage->mentionFranchiseTva);
    }

    private function chiffreur(): ChiffreurTable
    {
        return new ChiffreurTable(BaremeDeReference::creer());
    }
}
