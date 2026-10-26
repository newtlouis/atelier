<?php

declare(strict_types=1);

namespace App\Tests\Devis\Facturation;

use App\Devis\Chiffrage\ChiffreurTable;
use App\Devis\Chiffrage\DemandeTable;
use App\Devis\Entite\Demande;
use App\Devis\Entite\Devis;
use App\Devis\Entite\Facture;
use App\Devis\Entite\TransitionInterdite;
use App\Devis\Facturation\Facturier;
use App\Devis\Facturation\NumeroteurDeFactures;
use App\Tests\Devis\Chiffrage\BaremeDeReference;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Error;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test d'intégration : contre une vraie base PostgreSQL, pas un bouchon.
 *
 * La numérotation sans trou et l'immuabilité sont des propriétés de la base
 * autant que du code. Les vérifier avec un dépôt en mémoire ne prouverait rien.
 */
final class FacturierTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Facturier $facturier;

    protected function setUp(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;

        // Instancié à la main : le Facturier n'est injecté nulle part encore, donc
        // le conteneur l'élimine à la compilation. Le rendre public juste pour le
        // test reviendrait à modifier la production pour satisfaire le test.
        $this->facturier = new Facturier($em, new NumeroteurDeFactures($em));

        // Table rase avant chaque test : l'ordre des numéros est l'objet du test.
        $this->em->getConnection()->executeStatement(
            'TRUNCATE facture, ligne_devis, devis, demande, compteur_factures CASCADE',
        );
    }

    public function testLesNumerosSeSuiventSansTrou(): void
    {
        $numeros = [];
        for ($i = 0; $i < 3; ++$i) {
            $numeros[] = $this->facturier->emettre(
                $this->devisAccepte(),
                new DateTimeImmutable('2026-03-15'),
            )->numero;
        }

        self::assertSame(['FA-2026-0001', 'FA-2026-0002', 'FA-2026-0003'], $numeros);
    }

    public function testLaSequenceRepartAUnChaqueAnnee(): void
    {
        $this->facturier->emettre($this->devisAccepte(), new DateTimeImmutable('2026-12-31'));
        $deuxiemeAnnee = $this->facturier->emettre(
            $this->devisAccepte(),
            new DateTimeImmutable('2027-01-02'),
        );

        self::assertSame('FA-2027-0001', $deuxiemeAnnee->numero);
    }

    public function testUnDevisNonAccepteNEstPasFacturableEtNeConsommePasDeNumero(): void
    {
        $brouillon = $this->devis();
        $this->em->persist($brouillon->demande());
        $this->em->persist($brouillon);
        $this->em->flush();

        try {
            $this->facturier->emettre($brouillon);
            self::fail('Un devis en brouillon ne devrait pas être facturable.');
        } catch (TransitionInterdite) {
            // attendu
        }

        // Le point important, double : l'échec n'a pas brûlé le numéro 1, et
        // l'EntityManager reste utilisable — le refus a eu lieu avant la
        // transaction.
        self::assertTrue($this->em->isOpen());
        $facture = $this->facturier->emettre(
            $this->devisAccepte(),
            new DateTimeImmutable('2026-03-15'),
        );

        self::assertSame('FA-2026-0001', $facture->numero);
    }

    public function testUneFactureEmiseNePeutPlusEtreModifiee(): void
    {
        $facture = $this->facturier->emettre(
            $this->devisAccepte(),
            new DateTimeImmutable('2026-03-15'),
        );

        // Aucun mutateur n'existe ; on force par réflexion, comme le ferait un
        // développeur pressé. PHP lui-même refuse : l'immuabilité est garantie
        // par le langage, avant même d'atteindre Doctrine.
        $champ = new ReflectionProperty(Facture::class, 'totalHorsTaxesCentimes');

        self::assertTrue($champ->isReadOnly());

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot modify readonly property');

        $champ->setValue($facture, 1);
    }

    public function testLaFactureRecopieLesMontantsDuDevis(): void
    {
        $facture = $this->facturier->emettre(
            $this->devisAccepte(),
            new DateTimeImmutable('2026-03-15'),
        );

        self::assertSame(194_000, $facture->totalHorsTaxes()->centimes);
        self::assertSame(0, $facture->tva()->centimes);
        self::assertSame('TVA non applicable, art. 293 B du CGI', $facture->mentionFranchiseTva);
    }

    private function devisAccepte(): Devis
    {
        $devis = $this->devis();
        $devis->envoyer();
        $devis->accepter();

        $this->em->persist($devis->demande());
        $this->em->persist($devis);
        $this->em->flush();

        return $devis;
    }

    private function devis(): Devis
    {
        $chiffrage = new ChiffreurTable(BaremeDeReference::creer())->chiffre(
            new DemandeTable(longueurMm: 1_200, largeurMm: 600, hauteurMm: 350, distanceLivraisonKm: 100),
        );

        return new Devis(new Demande('Une table basse effet roche, 120 × 60.'), $chiffrage);
    }
}
