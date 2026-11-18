<?php

declare(strict_types=1);

namespace App\Tests\Shared\Contrat;

use App\Shared\Contrat\EnvoiFormulaire;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EnvoiFormulaireTest extends TestCase
{
    private function envoiReel(): EnvoiFormulaire
    {
        // La seule vraie demande reçue, anonymisée : identifiant et e-mail fictifs.
        return new EnvoiFormulaire(
            idNetlify: 'envoi-40',
            formulaire: 'devis',
            recuLe: new DateTimeImmutable('2026-09-07 10:00:00'),
            emailClient: 'client@example.org',
            message: '1600*800 mm',
            projet: 'table-custom',
        );
    }

    public function testUnChampVideOuFaitDEspacesVautAbsence(): void
    {
        $envoi = new EnvoiFormulaire(
            idNetlify: 'x',
            formulaire: 'devis',
            recuLe: new DateTimeImmutable(),
            emailClient: '  ',
            message: "  1600*800 mm\n",
            projet: '',
            ville: ' Strasbourg ',
        );

        self::assertNull($envoi->emailClient);
        self::assertNull($envoi->projet);
        self::assertSame('Strasbourg', $envoi->ville);
        self::assertSame('1600*800 mm', $envoi->message);
    }

    public function testLeTexteClientReprendLesChampsRenseignesPuisLeMessage(): void
    {
        self::assertSame(
            "Projet : table-custom\nMessage : 1600*800 mm",
            $this->envoiReel()->texteClient(),
        );
    }

    public function testLeTexteClientSuitUnOrdreFixe(): void
    {
        $envoi = new EnvoiFormulaire(
            idNetlify: 'x',
            formulaire: 'devis-mur',
            recuLe: new DateTimeImmutable(),
            emailClient: null,
            message: 'Pour un salon',
            surface: '12 m²',
            support: 'placo',
            ville: 'Colmar',
        );

        self::assertSame(
            "Surface : 12 m²\nSupport : placo\nVille : Colmar\nMessage : Pour un salon",
            $envoi->texteClient(),
        );
    }

    public function testLesChampsDUnePieceEtDUnProfessionnelSontRepris(): void
    {
        $envoi = new EnvoiFormulaire(
            idNetlify: 'x',
            formulaire: 'devis-piece',
            recuLe: new DateTimeImmutable(),
            emailClient: null,
            message: '',
            piece: 'travertin',
            taille: 'XL',
            besoin: 'une table pour un hall',
        );

        self::assertSame(
            "Pièce : travertin\nTaille : XL\nBesoin : une table pour un hall",
            $envoi->texteClient(),
        );
    }

    /**
     * Le texte client part chez le modèle et dans les journaux : l'adresse
     * e-mail, qui sert seulement à répondre, n'y figure jamais.
     */
    public function testLeTexteClientNeContientPasLAdresseEMail(): void
    {
        self::assertStringNotContainsString('client@example.org', $this->envoiReel()->texteClient());
    }

    public function testUnMessageSeulEstLeTexteClient(): void
    {
        $envoi = new EnvoiFormulaire(
            idNetlify: 'x',
            formulaire: 'devis',
            recuLe: new DateTimeImmutable(),
            emailClient: null,
            message: '1600*800 mm',
        );

        self::assertSame('Message : 1600*800 mm', $envoi->texteClient());
    }

    /** Found in security review: what Louis reads must be what was sent. */
    public function testTerminalControlCharactersAreDropped(): void
    {
        $envoi = new EnvoiFormulaire(
            idNetlify: 'x',
            formulaire: 'devis',
            recuLe: new DateTimeImmutable(),
            emailClient: null,
            message: "160 x 80 cm\x1b[31m\nen roche claire\tmerci\r",
            ville: "Lyon\x1b[2K\rStrasbourg\u{202E}",
        );

        self::assertSame('Lyon [2K Strasbourg', $envoi->ville);
        self::assertSame("160 x 80 cm[31m\nen roche claire\tmerci", $envoi->message);
    }

    public function testInvalidUtf8DoesNotEmptyAField(): void
    {
        $envoi = new EnvoiFormulaire(
            idNetlify: 'x',
            formulaire: 'devis',
            recuLe: new DateTimeImmutable(),
            emailClient: null,
            message: "Colmar\xff",
            ville: "Colmar\xff",
        );

        self::assertSame('Colmar?', $envoi->ville);
        self::assertSame('Colmar?', $envoi->message);
    }

    /**
     * Found in security review: a 200-character address made every import
     * fail on the same submission, and the ones behind it never arrived.
     */
    public function testFieldsFitTheirColumns(): void
    {
        $envoi = new EnvoiFormulaire(
            idNetlify: 'x',
            formulaire: 'devis',
            recuLe: new DateTimeImmutable(),
            emailClient: str_repeat('a', 169) . '@example.org',
            message: '1600*800 mm',
            ville: str_repeat('é', 121),
        );

        self::assertNull($envoi->emailClient);
        self::assertSame(str_repeat('é', 120), $envoi->ville);
    }

    public function testAnAddressThatFitsIsKept(): void
    {
        $adresse = str_repeat('a', 168) . '@example.org';

        $envoi = new EnvoiFormulaire(
            idNetlify: 'x',
            formulaire: 'devis',
            recuLe: new DateTimeImmutable(),
            emailClient: $adresse,
            message: '1600*800 mm',
            ville: str_repeat('é', 120),
        );

        self::assertSame($adresse, $envoi->emailClient);
        self::assertSame(str_repeat('é', 120), $envoi->ville);
    }

    public function testUnEnvoiSansIdentifiantEstRefuse(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EnvoiFormulaire(
            idNetlify: ' ',
            formulaire: 'devis',
            recuLe: new DateTimeImmutable(),
            emailClient: null,
            message: '1600*800 mm',
        );
    }

    public function testUnEnvoiSansFormulaireEstRefuse(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EnvoiFormulaire(
            idNetlify: 'x',
            formulaire: '',
            recuLe: new DateTimeImmutable(),
            emailClient: null,
            message: '1600*800 mm',
        );
    }
}
