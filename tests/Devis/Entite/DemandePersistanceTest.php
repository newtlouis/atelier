<?php

declare(strict_types=1);

namespace App\Tests\Devis\Entite;

use App\Devis\Entite\Demande;
use App\Shared\Contrat\EnvoiFormulaire;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Ce que seule la base garantit : l'envoi relu tel quel après un aller-retour
 * en JSON, et l'unicité de l'identifiant Netlify, filet de l'import.
 */
final class DemandePersistanceTest extends KernelTestCase
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

    private static function envoi(): EnvoiFormulaire
    {
        return new EnvoiFormulaire(
            idNetlify: 'envoi-40',
            formulaire: 'devis',
            recuLe: new DateTimeImmutable('2026-09-07 10:00:00'),
            emailClient: 'client@example.org',
            message: '1600*800 mm',
            projet: 'table-custom',
        );
    }

    public function testLEnvoiSeRelitDepuisLaBase(): void
    {
        $demande = Demande::depuisFormulaire(self::envoi());
        $this->em->persist($demande);
        $this->em->flush();
        $this->em->clear();

        $relue = $this->em->find(Demande::class, $demande->id);

        self::assertInstanceOf(Demande::class, $relue);
        self::assertEquals(self::envoi(), $relue->envoiFormulaire());
    }

    public function testDeuxDemandesDuMemeEnvoiSontRefuseesParLaBase(): void
    {
        $this->em->persist(Demande::depuisFormulaire(self::envoi()));
        $this->em->persist(Demande::depuisFormulaire(self::envoi()));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->em->flush();
    }

    public function testLesDemandesSaisiesALaMainNeSeGenentPas(): void
    {
        $this->em->persist(Demande::depuisTexte('Une table basse 120 × 60.'));
        $this->em->persist(Demande::depuisTexte('Un mur de 6 m².'));
        $this->em->flush();

        self::assertSame(2, $this->em->getRepository(Demande::class)->count([]));
    }
}
