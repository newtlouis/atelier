<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\ExtraireEtChiffrer;
use App\Application\ExtraireEtChiffrerHandler;
use App\Devis\Chiffrage\ChiffreurDeDemande;
use App\Devis\Chiffrage\ChiffreurMur;
use App\Devis\Chiffrage\ChiffreurTable;
use App\Devis\Entite\Demande;
use App\Devis\Entite\Devis;
use App\Devis\Entite\DevisRepository;
use App\Devis\Entite\EtatDevis;
use App\Devis\Entite\LectureDeDemande;
use App\Devis\Entite\LectureRepository;
use App\Extraction\Aiguillage;
use App\Extraction\ExtractionImpossible;
use App\Extraction\Infrastructure\ExtracteurFactice;
use App\Extraction\LecteurDeDimensions;
use App\Shared\Contrat\DemandeExtraite;
use App\Shared\Contrat\EnvoiFormulaire;
use App\Shared\Contrat\FamilleProduit;
use App\Shared\Contrat\IssueDeLecture;
use App\Shared\Contrat\NatureDemande;
use App\Tests\Devis\Chiffrage\BaremeDeReference;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * La chaîne complète, contre une vraie base, sans un seul appel réseau :
 * l'extracteur est remplacé par une doublure qui rejoue des réponses.
 */
final class ExtraireEtChiffrerHandlerTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;

        $this->em->getConnection()->executeStatement(
            'TRUNCATE facture, ligne_devis, devis, demande CASCADE',
        );
    }

    public function testUneDemandeComplèteDevientUnDevisEnBrouillon(): void
    {
        $demande = $this->demandePersistee('Une table basse 120 × 60, hauteur 35, livrée à 100 km.');

        $this->handler(ExtracteurFactice::renvoyant($this->tableComplete()))(
            new ExtraireEtChiffrer($demande->id),
        );

        $devis = $this->em->getRepository(Devis::class)->findOneBy(['demande' => $demande]);

        self::assertInstanceOf(Devis::class, $devis);
        self::assertSame(EtatDevis::Brouillon, $devis->etat());
        self::assertSame(194_000, $devis->totalHorsTaxes()->centimes);
    }

    public function testLeMemeMessageDeuxFoisNeProduitQuUnSeulDevis(): void
    {
        // Messenger peut redélivrer un message : c'est le cas normal d'une
        // file, pas une anomalie.
        $demande = $this->demandePersistee('Une table basse 120 × 60, hauteur 35, à 100 km.');
        $message = new ExtraireEtChiffrer($demande->id);

        $extracteur = ExtracteurFactice::renvoyant($this->tableComplete(), $this->tableComplete());
        $handler = $this->handler($extracteur);

        $handler($message);
        $handler($message);

        self::assertCount(1, $this->em->getRepository(Devis::class)->findAll());
        // Le second passage n'a même pas rappelé le modèle : pas de dépense inutile.
        self::assertCount(1, $extracteur->textesRecus());
    }

    public function testUneDemandeIncompleteNeCreeAucunDevisEtNeFaitPasEchouerLeMessage(): void
    {
        $demande = $this->demandePersistee('Bonjour, je voudrais une table.');

        $incomplete = new DemandeExtraite(
            famille: FamilleProduit::Table,
            longueurMm: null,
            largeurMm: null,
            hauteurMm: null,
            surfaceMetresCarres: null,
            distanceKm: null,
            champsManquants: ['dimensions', 'distance'],
        );

        // Ne lève pas : réessayer ne ferait pas apparaître une dimension que
        // le client n'a pas donnée.
        $this->handler(ExtracteurFactice::renvoyant($incomplete))(
            new ExtraireEtChiffrer($demande->id),
        );

        self::assertCount(0, $this->em->getRepository(Devis::class)->findAll());
    }

    public function testUnePanneDuServiceRemonteEtDeclencheUnReessai(): void
    {
        $demande = $this->demandePersistee('Une table 120 × 60, hauteur 35, à 100 km.');

        $this->expectException(ExtractionImpossible::class);

        $this->handler(ExtracteurFactice::echouantAvec(
            ExtractionImpossible::serviceIndisponible('502'),
        ))(new ExtraireEtChiffrer($demande->id));
    }

    public function testUneDemandeDisparueNEstPasUneErreur(): void
    {
        $extracteur = ExtracteurFactice::renvoyant($this->tableComplete());

        $this->handler($extracteur)(new ExtraireEtChiffrer(\Symfony\Component\Uid\Uuid::v7()));

        self::assertSame([], $extracteur->textesRecus());
    }

    public function testUneDemandeSansHauteurEstChiffreeALaHauteurStandard(): void
    {
        $demande = $this->demandePersistee('Une table basse 120 × 60, livrée à 100 km.');
        $sansHauteur = new DemandeExtraite(
            famille: FamilleProduit::Table,
            longueurMm: 1_200,
            largeurMm: 600,
            hauteurMm: null,
            surfaceMetresCarres: null,
            distanceKm: 100,
            champsManquants: ['hauteur'],
        );

        $this->handler(ExtracteurFactice::renvoyant($sansHauteur))(new ExtraireEtChiffrer($demande->id));

        $devis = $this->em->getRepository(Devis::class)->findOneBy(['demande' => $demande]);
        self::assertInstanceOf(Devis::class, $devis);
        self::assertSame(194_000, $devis->totalHorsTaxes()->centimes);
    }

    public function testLaVraieDemandeDuFormulaireSeLitSansModeleEtAttendSaDistance(): void
    {
        $demande = Demande::depuisFormulaire(new EnvoiFormulaire(
            idNetlify: 'envoi-40',
            formulaire: 'devis',
            recuLe: new DateTimeImmutable('2026-09-07'),
            emailClient: null,
            message: '1600*800 mm',
            projet: 'table-custom',
        ));
        $this->em->persist($demande);
        $this->em->flush();
        $extracteur = new ExtracteurFactice();

        $this->handler($extracteur)(new ExtraireEtChiffrer($demande->id));

        self::assertSame([], $extracteur->textesRecus());
        // Sans distance, pas de devis : c'est à Louis de la saisir.
        self::assertCount(0, $this->em->getRepository(Devis::class)->findAll());
        $lecture = $this->lectureDe($demande);
        self::assertSame(IssueDeLecture::QuestionsAuClient, $lecture->issue); // ni distance ni ville
        self::assertSame(350, $lecture->hauteurMm);
        self::assertTrue($lecture->hauteurStandard);
    }

    public function testAvecSaVilleLaVraieDemandeAttendSaDistance(): void
    {
        $demande = Demande::depuisFormulaire(new EnvoiFormulaire(
            idNetlify: 'envoi-40',
            formulaire: 'devis',
            recuLe: new DateTimeImmutable('2026-09-07'),
            emailClient: null,
            message: '1600*800 mm',
            projet: 'table-custom',
            ville: 'Colmar',
        ));
        $this->em->persist($demande);
        $this->em->flush();

        $this->handler(new ExtracteurFactice())(new ExtraireEtChiffrer($demande->id));

        $lecture = $this->lectureDe($demande);
        self::assertSame(IssueDeLecture::EnAttenteDeDistance, $lecture->issue);
        self::assertSame('Colmar', $lecture->villeLivraison);
    }

    public function testUnHorsSujetEstEnregistreSansDevis(): void
    {
        $demande = $this->demandePersistee('We build mobile apps. Book a call!');
        $spam = new DemandeExtraite(
            famille: null,
            longueurMm: null,
            largeurMm: null,
            hauteurMm: null,
            surfaceMetresCarres: null,
            distanceKm: null,
            nature: NatureDemande::HorsSujet,
        );

        $this->handler(ExtracteurFactice::renvoyant($spam))(new ExtraireEtChiffrer($demande->id));

        self::assertSame(IssueDeLecture::HorsSujet, $this->lectureDe($demande)->issue);
        self::assertCount(0, $this->em->getRepository(Devis::class)->findAll());
    }

    /**
     * Une demande déjà lue n'est jamais relue : rejouer le message après une
     * panne du chiffrage ne coûte pas un second appel.
     */
    public function testUneDemandeDejaLueNeRappellePasLeModele(): void
    {
        $demande = $this->demandePersistee('Une table basse 120 × 60, livrée à 100 km.');
        $extracteur = ExtracteurFactice::renvoyant($this->tableComplete());
        $handler = $this->handler($extracteur);
        $handler(new ExtraireEtChiffrer($demande->id));
        $this->em->getConnection()->executeStatement('TRUNCATE ligne_devis, devis CASCADE');

        $handler(new ExtraireEtChiffrer($demande->id));

        self::assertCount(1, $extracteur->textesRecus());
        self::assertCount(1, $this->em->getRepository(Devis::class)->findAll());
        self::assertCount(1, $this->em->getRepository(LectureDeDemande::class)->findAll());
    }

    private function lectureDe(Demande $demande): LectureDeDemande
    {
        $lecture = new LectureRepository($this->em)->pour($demande);
        self::assertInstanceOf(LectureDeDemande::class, $lecture);

        return $lecture;
    }

    private function tableComplete(): DemandeExtraite
    {
        return new DemandeExtraite(
            famille: FamilleProduit::Table,
            longueurMm: 1_200,
            largeurMm: 600,
            hauteurMm: 350,
            surfaceMetresCarres: null,
            distanceKm: 100,
            champsManquants: [],
        );
    }

    private function demandePersistee(string $texte): Demande
    {
        $demande = new Demande($texte);
        $this->em->persist($demande);
        $this->em->flush();

        return $demande;
    }

    private function handler(ExtracteurFactice $extracteur): ExtraireEtChiffrerHandler
    {
        $bareme = BaremeDeReference::creer();

        return new ExtraireEtChiffrerHandler(
            $this->em,
            $extracteur,
            new Aiguillage(new LecteurDeDimensions(), $extracteur),
            new ChiffreurDeDemande(new ChiffreurTable($bareme), new ChiffreurMur($bareme)),
            new DevisRepository($this->em),
            new LectureRepository($this->em),
            new NullLogger(),
        );
    }
}
