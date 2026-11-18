<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\SaisieDeDistance;
use App\Application\SaisirLesDistancesCommand;
use App\Devis\Chiffrage\ChiffreurDeDemande;
use App\Devis\Chiffrage\ChiffreurMur;
use App\Devis\Chiffrage\ChiffreurTable;
use App\Devis\Entite\Demande;
use App\Devis\Entite\Devis;
use App\Devis\Entite\DevisRepository;
use App\Devis\Entite\EtatDevis;
use App\Devis\Entite\LectureDeDemande;
use App\Devis\Entite\LectureRepository;
use App\Shared\Contrat\DemandeExtraite;
use App\Shared\Contrat\FamilleProduit;
use App\Shared\Contrat\SupportMur;
use App\Tests\Devis\Chiffrage\BaremeDeReference;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SaisirLesDistancesTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;
        $this->em->getConnection()->executeStatement('TRUNCATE facture, ligne_devis, devis, demande CASCADE');
    }

    /** La vraie demande, une fois lue : 160 × 80, hauteur standard, ville connue. */
    private function vraieDemandeLue(string $recueLe = '2026-09-07', string $ville = 'Colmar'): LectureDeDemande
    {
        return $this->lue(new DemandeExtraite(
            famille: FamilleProduit::Table,
            longueurMm: 1_600,
            largeurMm: 800,
            hauteurMm: 350,
            surfaceMetresCarres: null,
            distanceKm: null,
            champsManquants: ['distance'],
            villeLivraison: $ville,
            hauteurStandard: true,
        ), $recueLe);
    }

    private function lue(DemandeExtraite $extraite, string $recueLe = '2026-09-07'): LectureDeDemande
    {
        $demande = new Demande('Message : 1600*800 mm', recueLe: new DateTimeImmutable($recueLe));
        $lecture = LectureDeDemande::de($demande, $extraite);
        $this->em->persist($demande);
        $this->em->persist($lecture);
        $this->em->flush();

        return $lecture;
    }

    private function saisie(): SaisieDeDistance
    {
        $bareme = BaremeDeReference::creer();

        return new SaisieDeDistance(
            $this->em,
            new ChiffreurDeDemande(new ChiffreurTable($bareme), new ChiffreurMur($bareme)),
            new DevisRepository($this->em),
        );
    }

    private function commande(): CommandTester
    {
        return new CommandTester(new SaisirLesDistancesCommand(new LectureRepository($this->em), $this->saisie(), 'Strasbourg'));
    }

    private static function affichage(CommandTester $commande): string
    {
        $texte = preg_replace('/\s+/', ' ', $commande->getDisplay());
        self::assertIsString($texte);

        return $texte;
    }

    public function testLaVraieDemandeDevientUnDevisEnBrouillonUneFoisLaDistanceSaisie(): void
    {
        $this->vraieDemandeLue();
        $commande = $this->commande();
        $commande->setInputs(['75']);

        self::assertSame(0, $commande->execute([]));

        $affichage = self::affichage($commande);
        self::assertStringContainsString('Demande du 07/09/2026 — table 160 × 80 cm', $affichage);
        self::assertStringContainsString('https://www.google.com/maps/dir/?api=1&origin=Strasbourg&destination=Colmar', $affichage);
        $devis = $this->em->getRepository(Devis::class)->findAll();
        self::assertCount(1, $devis);
        self::assertSame(EtatDevis::Brouillon, $devis[0]->etat());
        // 1,28 m² à 2 500 € = 3 200 €, au-dessus du seuil : livraison offerte.
        self::assertSame(320_000, $devis[0]->totalHorsTaxes()->centimes);
        self::assertStringContainsString('Devis en brouillon : 3 200,00 € HT.', $affichage);
    }

    public function testUneReponseVidePasseLaDemandeSansDevis(): void
    {
        $this->vraieDemandeLue();
        $commande = $this->commande();
        $commande->setInputs(['']);

        $commande->execute([]);

        self::assertStringContainsString('Passée.', self::affichage($commande));
        self::assertCount(0, $this->em->getRepository(Devis::class)->findAll());
        self::assertCount(1, new LectureRepository($this->em)->enAttenteDeDistance());
    }

    public function testUneReponseQuiNEstPasUnNombreEstRedemandee(): void
    {
        $this->vraieDemandeLue();
        $commande = $this->commande();
        $commande->setInputs(['75 km', '75']);

        $commande->execute([]);

        self::assertStringContainsString('Un nombre entier de kilomètres, sans unité.', self::affichage($commande));
        self::assertCount(1, $this->em->getRepository(Devis::class)->findAll());
    }

    public function testLesDemandesSontServiesDansLOrdreDArriveeEtDisparaissentUneFoisChiffrees(): void
    {
        $this->vraieDemandeLue('2026-10-01', 'Mulhouse');
        $this->vraieDemandeLue('2026-09-01', 'Colmar');

        $enAttente = new LectureRepository($this->em)->enAttenteDeDistance();
        self::assertSame(['Colmar', 'Mulhouse'], array_map(static fn(LectureDeDemande $l): ?string => $l->villeLivraison, $enAttente));

        $this->saisie()->chiffre($enAttente[0], 75);

        self::assertCount(1, new LectureRepository($this->em)->enAttenteDeDistance());
    }

    public function testSeulesLesDemandesEnAttenteDeDistanceSontListees(): void
    {
        $this->lue(new DemandeExtraite(
            famille: FamilleProduit::Mur,
            longueurMm: null,
            largeurMm: null,
            hauteurMm: null,
            surfaceMetresCarres: 6,
            distanceKm: null,
            champsManquants: ['distance'],
            villeLivraison: 'Colmar',
            support: SupportMur::Bois,
        ));
        $commande = $this->commande();

        $commande->execute([]);

        self::assertStringContainsString('Aucune demande n\'attend de distance.', self::affichage($commande));
    }

    public function testUnMurAfficheSaSurface(): void
    {
        $this->lue(new DemandeExtraite(
            famille: FamilleProduit::Mur,
            longueurMm: null,
            largeurMm: null,
            hauteurMm: null,
            surfaceMetresCarres: 6,
            distanceKm: null,
            champsManquants: ['distance'],
            villeLivraison: 'Colmar',
            support: SupportMur::Placo,
        ));
        $commande = $this->commande();
        $commande->setInputs(['']);

        $commande->execute([]);

        self::assertStringContainsString('mur de 6 m²', self::affichage($commande));
    }

    public function testUnePetiteTableSousLeMinimumEstSignaleeSansDevis(): void
    {
        $this->lue(new DemandeExtraite(
            famille: FamilleProduit::Table,
            longueurMm: 800,
            largeurMm: 500,
            hauteurMm: 350,
            surfaceMetresCarres: null,
            distanceKm: null,
            champsManquants: ['distance'],
            villeLivraison: 'Colmar',
        ));
        $commande = $this->commande();
        $commande->setInputs(['10']);

        $commande->execute([]);

        self::assertStringContainsString('minimum', self::affichage($commande));
        self::assertCount(0, $this->em->getRepository(Devis::class)->findAll());
    }

    public function testUneDemandeDejaChiffreeNeLEstPasDeuxFois(): void
    {
        $lecture = $this->vraieDemandeLue();
        $this->saisie()->chiffre($lecture, 75);

        $this->expectException(LogicException::class);

        $this->saisie()->chiffre($lecture, 80);
    }
}
