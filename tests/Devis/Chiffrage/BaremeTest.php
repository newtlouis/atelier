<?php

declare(strict_types=1);

namespace App\Tests\Devis\Chiffrage;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BaremeTest extends TestCase
{
    public function testEnFranchiseLaMentionLegaleEstPortee(): void
    {
        $bareme = BaremeDeReference::creer();

        self::assertTrue($bareme->estEnFranchiseDeTva());
        self::assertSame('TVA non applicable, art. 293 B du CGI', $bareme->mentionAPorter());
    }

    public function testAssujettiAucuneMentionNEstPortee(): void
    {
        $bareme = BaremeDeReference::creer(tauxTvaCentiemes: 2_000);

        self::assertFalse($bareme->estEnFranchiseDeTva());
        self::assertNull($bareme->mentionAPorter());
    }

    public function testUnTauxNulSansMentionLegaleEstRefuse(): void
    {
        // Une facture à 0 % sans justification est une facture non conforme :
        // l'objet refuse de se construire plutôt que de produire ce document.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mention légale');

        new \App\Devis\Chiffrage\Bareme(
            prixMetreCarrePlateau: \App\Shared\Montant::enEuros(2_500),
            prixMetreCarreMur: \App\Shared\Montant::enEuros(450),
            minimumDeCommande: \App\Shared\Montant::enEuros(1_200),
            seuilLivraisonOfferte: \App\Shared\Montant::enEuros(2_000),
            distanceMaxLivraisonOfferteKm: 1_000,
            tarifKilometrique: \App\Shared\Montant::enCentimes(70),
            coutJourneeCamionnette: \App\Shared\Montant::enEuros(100),
            coutNuitee: \App\Shared\Montant::enEuros(90),
            tauxTvaCentiemes: 0,
            mentionFranchiseTva: null,
        );
    }
}
