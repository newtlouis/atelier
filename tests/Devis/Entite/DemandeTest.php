<?php

declare(strict_types=1);

namespace App\Tests\Devis\Entite;

use App\Devis\Entite\Demande;
use App\Shared\Contrat\EnvoiFormulaire;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DemandeTest extends TestCase
{
    private static function envoi(): EnvoiFormulaire
    {
        // La seule vraie demande, anonymisée ; la ville est fictive.
        return new EnvoiFormulaire(
            idNetlify: 'envoi-40',
            formulaire: 'devis',
            recuLe: new DateTimeImmutable('2026-09-07 10:00:00'),
            emailClient: 'client@example.org',
            message: '1600*800 mm',
            projet: 'table-custom',
            ville: 'Colmar',
        );
    }

    public function testUneDemandeVenueDuFormulaireGardeLEnvoi(): void
    {
        $demande = Demande::depuisFormulaire(self::envoi());

        self::assertSame("Projet : table-custom\nVille : Colmar\nMessage : 1600*800 mm", $demande->texteClient);
        self::assertSame('client@example.org', $demande->emailClient);
        self::assertSame('envoi-40', $demande->idNetlify);
        self::assertSame('devis', $demande->formulaire);
        self::assertSame(
            ['projet' => 'table-custom', 'ville' => 'Colmar', 'message' => '1600*800 mm'],
            $demande->champsFormulaire,
        );
    }

    public function testLaDateEstCelleDeLEnvoiPasCelleDeLImport(): void
    {
        self::assertEquals(
            new DateTimeImmutable('2026-09-07 10:00:00'),
            Demande::depuisFormulaire(self::envoi())->recueLe,
        );
    }

    public function testLEnvoiDOrigineSeRetrouveALIdentique(): void
    {
        self::assertEquals(self::envoi(), Demande::depuisFormulaire(self::envoi())->envoiFormulaire());
    }

    public function testTousLesChampsDUnEnvoiSontGardes(): void
    {
        $envoi = new EnvoiFormulaire(
            idNetlify: 'x',
            formulaire: 'devis-piece',
            recuLe: new DateTimeImmutable('2026-06-29 12:00:00'),
            emailClient: null,
            message: '',
            projet: 'p',
            surface: 's',
            support: 'cloison',
            ville: 'v',
            piece: 'travertin',
            taille: 'XL',
            besoin: 'b',
        );

        self::assertEquals($envoi, Demande::depuisFormulaire($envoi)->envoiFormulaire());
    }

    public function testUneDemandeSaisieALaMainNAPasDEnvoi(): void
    {
        $demande = new Demande('Une table basse 120 × 60 à 100 km.');

        self::assertNull($demande->envoiFormulaire());
        self::assertNull($demande->idNetlify);
        self::assertSame([], $demande->champsFormulaire);
    }

    public function testUneDemandeSansTexteEstRefusee(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Demande('  ');
    }
}
