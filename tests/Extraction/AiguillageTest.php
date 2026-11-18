<?php

declare(strict_types=1);

namespace App\Tests\Extraction;

use App\Extraction\Aiguillage;
use App\Extraction\Infrastructure\ExtracteurFactice;
use App\Extraction\LecteurDeDimensions;
use App\Shared\Contrat\DemandeExtraite;
use App\Shared\Contrat\EnvoiFormulaire;
use App\Shared\Contrat\FamilleProduit;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AiguillageTest extends TestCase
{
    private static function envoi(string $message, ?string $projet = 'table-custom', ?string $ville = null): EnvoiFormulaire
    {
        return new EnvoiFormulaire(
            idNetlify: 'x',
            formulaire: 'devis',
            recuLe: new DateTimeImmutable('2026-09-07'),
            emailClient: null,
            message: $message,
            projet: $projet,
            ville: $ville,
        );
    }

    private static function reponseDuModele(): DemandeExtraite
    {
        return new DemandeExtraite(
            famille: FamilleProduit::Table,
            longueurMm: 1_400,
            largeurMm: 700,
            hauteurMm: 380,
            surfaceMetresCarres: null,
            distanceKm: null,
            champsManquants: ['distance'],
        );
    }

    public function testLaSeuleVraieDemandeSeLitSansAppelerLeModele(): void
    {
        $extracteur = new ExtracteurFactice();

        $demande = new Aiguillage(new LecteurDeDimensions(), $extracteur)
            ->lit(self::envoi('1600*800 mm', ville: 'Colmar'));

        self::assertEquals(
            new DemandeExtraite(
                famille: FamilleProduit::Table,
                longueurMm: 1_600,
                largeurMm: 800,
                hauteurMm: null,
                surfaceMetresCarres: null,
                distanceKm: null,
                champsManquants: ['distance'],
                villeLivraison: 'Colmar',
            ),
            $demande,
        );
        self::assertSame([], $extracteur->textesRecus());
    }

    public function testUneHauteurEcriteDansLeMessageEstGardee(): void
    {
        $demande = new Aiguillage(new LecteurDeDimensions(), new ExtracteurFactice())
            ->lit(self::envoi('120 x 60 x 40 cm'));

        self::assertSame(400, $demande->hauteurMm);
    }

    public function testUnMessageEnPhrasesPartVersLeModeleAvecLesChamps(): void
    {
        $extracteur = ExtracteurFactice::renvoyant(self::reponseDuModele());

        $demande = new Aiguillage(new LecteurDeDimensions(), $extracteur)
            ->lit(self::envoi('Une table de 140 sur 70, en roche claire', ville: 'Colmar'));

        self::assertEquals(self::reponseDuModele(), $demande);
        self::assertSame(
            ["Projet : table-custom\nVille : Colmar\nMessage : Une table de 140 sur 70, en roche claire"],
            $extracteur->textesRecus(),
        );
    }

    /**
     * Une pièce du catalogue a un prix connu : « 140×70×38 » y décrit une
     * taille du catalogue, pas une commande sur mesure à chiffrer au m².
     */
    public function testHorsTableSurMesureLesDimensionsNeSuffisentPas(): void
    {
        $extracteur = ExtracteurFactice::renvoyant(self::reponseDuModele());

        new Aiguillage(new LecteurDeDimensions(), $extracteur)
            ->lit(self::envoi('140 x 70 x 38 cm', projet: 'table-standard'));

        self::assertCount(1, $extracteur->textesRecus());
    }

    public function testSansProjetLeModeleDecide(): void
    {
        $extracteur = ExtracteurFactice::renvoyant(self::reponseDuModele());

        new Aiguillage(new LecteurDeDimensions(), $extracteur)->lit(self::envoi('3 x 2 m', projet: null));

        self::assertCount(1, $extracteur->textesRecus());
    }
}
