<?php

declare(strict_types=1);

namespace App\Tests\Devis\Entite;

use App\Devis\Chiffrage\ChiffreurTable;
use App\Devis\Chiffrage\DemandeTable;
use App\Devis\Entite\Demande;
use App\Devis\Entite\Devis;
use App\Devis\Entite\EtatDevis;
use App\Devis\Entite\TransitionInterdite;
use App\Tests\Devis\Chiffrage\BaremeDeReference;
use PHPUnit\Framework\TestCase;

final class DevisTest extends TestCase
{
    public function testUnDevisNaitBrouillonEtRecopieLeChiffrage(): void
    {
        $devis = $this->devis();

        self::assertSame(EtatDevis::Brouillon, $devis->etat());
        self::assertCount(2, $devis->lignes());
        // 1 800 € de table + 140 € de livraison (100 km aller-retour = 200 km × 0,70 €).
        self::assertSame(194_000, $devis->totalHorsTaxes()->centimes);
        self::assertSame('TVA non applicable, art. 293 B du CGI', $devis->mentionFranchiseTva());
    }

    public function testLeParcoursNormalVaDuBrouillonALaFacture(): void
    {
        $devis = $this->devis();

        $devis->envoyer();
        self::assertSame(EtatDevis::Envoye, $devis->etat());
        self::assertNotNull($devis->envoyeLe());

        $devis->accepter();
        self::assertSame(EtatDevis::Accepte, $devis->etat());

        $devis->marquerFacture();
        self::assertSame(EtatDevis::Facture, $devis->etat());
        self::assertTrue($devis->etat()->estDefinitif());
    }

    public function testOnNAcceptePasUnDevisQuiNAJamaisEteEnvoye(): void
    {
        $this->expectException(TransitionInterdite::class);
        $this->expectExceptionMessage('Impossible de accepter un devis « brouillon » : il doit être « envoye ».');

        $this->devis()->accepter();
    }

    public function testOnNEnvoiePasDeuxFoisLeMemeDevis(): void
    {
        $devis = $this->devis();
        $devis->envoyer();

        $this->expectException(TransitionInterdite::class);
        $devis->envoyer();
    }

    public function testUnDevisRefuseNePeutPlusEtreFacture(): void
    {
        $devis = $this->devis();
        $devis->envoyer();
        $devis->refuser();

        $this->expectException(TransitionInterdite::class);
        $devis->marquerFacture();
    }

    public function testUnBrouillonEstModifiableUnDevisEnvoyeNeLEstPlus(): void
    {
        $devis = $this->devis();
        self::assertTrue($devis->etat()->estModifiable());

        $devis->envoyer();
        self::assertFalse($devis->etat()->estModifiable());
    }

    private function devis(): Devis
    {
        $chiffrage = new ChiffreurTable(BaremeDeReference::creer())->chiffre(
            new DemandeTable(longueurMm: 1_200, largeurMm: 600, hauteurMm: 350, distanceLivraisonKm: 100),
        );

        return new Devis(new Demande('Une table basse effet roche, 120 × 60.'), $chiffrage);
    }
}
