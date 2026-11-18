<?php

declare(strict_types=1);

namespace App\Tests\Extraction;

use App\Extraction\Aiguillage;
use App\Extraction\Infrastructure\ExtracteurFactice;
use App\Extraction\LecteurDeDimensions;
use App\Shared\Contrat\DemandeExtraite;
use App\Shared\Contrat\EnvoiFormulaire;
use App\Shared\Contrat\FamilleProduit;
use App\Shared\Contrat\IssueDeLecture;
use App\Shared\Contrat\NatureDemande;
use App\Shared\Contrat\SupportMur;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AiguillageTest extends TestCase
{
    private static function envoi(
        string $message,
        ?string $projet = 'table-custom',
        ?string $ville = null,
        string $formulaire = 'devis',
        ?string $support = null,
    ): EnvoiFormulaire {
        return new EnvoiFormulaire(
            idNetlify: 'x',
            formulaire: $formulaire,
            recuLe: new DateTimeImmutable('2026-09-07'),
            emailClient: null,
            message: $message,
            projet: $projet,
            support: $support,
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
     * Le test de Louis du 29/06/2026, anonymisé : la Travertin XL est au
     * catalogue à 2 700 €. Chiffrée au m², elle sortirait à 2 450 €.
     */
    public function testUneTableDuCatalogueNEstNiLueNiEnvoyeeAuModele(): void
    {
        $extracteur = new ExtracteurFactice();

        $demande = new Aiguillage(new LecteurDeDimensions(), $extracteur)->lit(self::envoi(
            'Bonjour, je souhaite commander la table Travertin en taille XL (140×70×38, 2 700 €).',
            projet: 'table-standard',
            ville: 'Colmar',
        ));

        self::assertSame([], $extracteur->textesRecus());
        self::assertSame(NatureDemande::CommandeCatalogue, $demande->nature);
        self::assertSame(FamilleProduit::Table, $demande->famille);
        self::assertSame('Colmar', $demande->villeLivraison);
        self::assertNull($demande->longueurMm);
        self::assertSame(IssueDeLecture::CommandeCatalogue, $demande->issue());
    }

    public function testUnePieceDuCatalogueNEstPasEnvoyeeAuModele(): void
    {
        $extracteur = new ExtracteurFactice();

        $demande = new Aiguillage(new LecteurDeDimensions(), $extracteur)
            ->lit(self::envoi('', projet: null, formulaire: 'devis-piece'));

        self::assertSame([], $extracteur->textesRecus());
        self::assertSame(NatureDemande::CommandeCatalogue, $demande->nature);
        self::assertNull($demande->famille);
    }

    public function testLeSupportCocheDansLeFormulaireCompleteLaLectureDuModele(): void
    {
        $murSansSupport = new DemandeExtraite(
            famille: FamilleProduit::Mur,
            longueurMm: null,
            largeurMm: null,
            hauteurMm: null,
            surfaceMetresCarres: 6,
            distanceKm: null,
            champsManquants: ['distance', 'support'],
            villeLivraison: 'Colmar',
        );

        $demande = new Aiguillage(new LecteurDeDimensions(), ExtracteurFactice::renvoyant($murSansSupport))
            ->lit(self::envoi('6 m² dans le salon', projet: null, ville: 'Colmar', formulaire: 'devis-mur', support: 'tapisserie'));

        self::assertSame(SupportMur::Tapisserie, $demande->support);
        self::assertSame(IssueDeLecture::EnAttenteDeDistance, $demande->issue());
    }

    public function testSansProjetLeModeleDecide(): void
    {
        $extracteur = ExtracteurFactice::renvoyant(self::reponseDuModele());

        new Aiguillage(new LecteurDeDimensions(), $extracteur)->lit(self::envoi('3 x 2 m', projet: null));

        self::assertCount(1, $extracteur->textesRecus());
    }
}
