<?php

declare(strict_types=1);

namespace App\Tests\Devis\Chiffrage;

use App\Devis\Chiffrage\ChiffreurDeDemande;
use App\Devis\Chiffrage\ChiffreurMur;
use App\Devis\Chiffrage\ChiffreurTable;
use App\Devis\Chiffrage\DemandeIncomplete;
use App\Shared\Contrat\DemandeExtraite;
use App\Shared\Contrat\FamilleProduit;
use PHPUnit\Framework\TestCase;

/**
 * La jonction entre ce qu'un modèle a compris et ce que l'atelier facture.
 */
final class ChiffreurDeDemandeTest extends TestCase
{
    public function testUneTableExtraiteEstChiffreeCommeSiElleAvaitEteSaisieALaMain(): void
    {
        $chiffrage = $this->chiffreur()->chiffre(new DemandeExtraite(
            famille: FamilleProduit::Table,
            longueurMm: 1_200,
            largeurMm: 600,
            hauteurMm: 350,
            surfaceMetresCarres: null,
            distanceKm: 100,
            champsManquants: [],
        ));

        self::assertSame(194_000, $chiffrage->totalHorsTaxes->centimes);
    }

    public function testUnMurExtraitEstChiffreAvecSonDeplacement(): void
    {
        $chiffrage = $this->chiffreur()->chiffre(new DemandeExtraite(
            famille: FamilleProduit::Mur,
            longueurMm: null,
            largeurMm: null,
            hauteurMm: null,
            surfaceMetresCarres: 6,
            distanceKm: 200,
            champsManquants: [],
        ));

        self::assertSame(353_000, $chiffrage->totalHorsTaxes->centimes);
        self::assertCount(4, $chiffrage->lignes);
    }

    public function testUnChampManquantArreteLeChiffrageAvecLaQuestionAPoser(): void
    {
        $this->expectException(DemandeIncomplete::class);
        $this->expectExceptionMessage('Il manque : hauteur, distance.');

        $this->chiffreur()->chiffre(new DemandeExtraite(
            famille: FamilleProduit::Table,
            longueurMm: 1_200,
            largeurMm: 600,
            hauteurMm: null,
            surfaceMetresCarres: null,
            distanceKm: null,
            champsManquants: ['hauteur', 'distance'],
        ));
    }

    public function testUneFamilleIndeterminableNEstPasDevinee(): void
    {
        // « Bonjour, combien coûte une pièce chez vous ? » — rien n'autorise à
        // supposer qu'il s'agit d'une table plutôt que d'un mur.
        $this->expectException(DemandeIncomplete::class);

        $this->chiffreur()->chiffre(new DemandeExtraite(
            famille: null,
            longueurMm: null,
            largeurMm: null,
            hauteurMm: null,
            surfaceMetresCarres: null,
            distanceKm: null,
            champsManquants: ['famille'],
        ));
    }

    private function chiffreur(): ChiffreurDeDemande
    {
        $bareme = BaremeDeReference::creer();

        return new ChiffreurDeDemande(new ChiffreurTable($bareme), new ChiffreurMur($bareme));
    }
}
