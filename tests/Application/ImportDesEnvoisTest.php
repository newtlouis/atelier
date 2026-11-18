<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\BilanImport;
use App\Application\ExtraireEtChiffrer;
use App\Application\ImportDesEnvois;
use App\Application\ImporterLesEnvoisCommand;
use App\Devis\Entite\Demande;
use App\Devis\SourceDesEnvois;
use App\Shared\Contrat\EnvoiFormulaire;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class ImportDesEnvoisTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private InMemoryTransport $file;

    protected function setUp(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;
        $file = self::getContainer()->get('messenger.transport.extraction');
        self::assertInstanceOf(InMemoryTransport::class, $file);
        $this->file = $file;

        $this->em->getConnection()->executeStatement('TRUNCATE facture, ligne_devis, devis, demande CASCADE');
    }

    private static function envoi(string $id, string $message = '1600*800 mm'): EnvoiFormulaire
    {
        return new EnvoiFormulaire(
            idNetlify: $id,
            formulaire: 'devis',
            recuLe: new DateTimeImmutable('2026-09-07'),
            emailClient: null,
            message: $message,
            projet: 'table-custom',
        );
    }

    private function import(EnvoiFormulaire ...$envois): ImportDesEnvois
    {
        $source = new class (array_values($envois)) implements SourceDesEnvois {
            /** @param list<EnvoiFormulaire> $envois */
            public function __construct(private array $envois) {}

            public function envois(): array
            {
                return $this->envois;
            }
        };
        $bus = self::getContainer()->get(MessageBusInterface::class);
        self::assertInstanceOf(MessageBusInterface::class, $bus);

        return new ImportDesEnvois($source, $this->em, $bus);
    }

    public function testChaqueEnvoiNouveauDevientUneDemandeMiseEnFile(): void
    {
        $bilan = $this->import(self::envoi('a'), self::envoi('b'))->importe();

        self::assertEquals(new BilanImport(nouveaux: 2, dejaConnus: 0, vides: 0), $bilan);
        $demande = $this->em->getRepository(Demande::class)->findOneBy(['idNetlify' => 'a']);
        self::assertInstanceOf(Demande::class, $demande);
        $messages = array_map(static fn(Envelope $e): object => $e->getMessage(), $this->file->getSent());
        self::assertCount(2, $messages);
        self::assertEquals(new ExtraireEtChiffrer($demande->id), $messages[0]);
    }

    public function testRelancerLImportNeCreeNiDemandeNiMessage(): void
    {
        $this->import(self::envoi('a'))->importe();

        $bilan = $this->import(self::envoi('a'), self::envoi('b'))->importe();

        self::assertEquals(new BilanImport(nouveaux: 1, dejaConnus: 1, vides: 0), $bilan);
        self::assertSame(2, $this->em->getRepository(Demande::class)->count([]));
        self::assertCount(2, $this->file->getSent());
    }

    public function testUnEnvoiVideEstIgnore(): void
    {
        $vide = new EnvoiFormulaire(
            idNetlify: 'v',
            formulaire: 'devis',
            recuLe: new DateTimeImmutable(),
            emailClient: 'x@example.org',
            message: '  ',
        );

        $bilan = $this->import($vide, self::envoi('a'))->importe();

        self::assertEquals(new BilanImport(nouveaux: 1, dejaConnus: 0, vides: 1), $bilan);
        self::assertCount(1, $this->file->getSent());
    }

    public function testLaCommandeAnnonceLeBilanSansDonneePersonnelle(): void
    {
        $commande = new CommandTester(new ImporterLesEnvoisCommand($this->import(self::envoi('a'))));

        self::assertSame(0, $commande->execute([]));
        // SymfonyStyle coupe les lignes à la largeur du terminal.
        $affichage = preg_replace('/\s+/', ' ', $commande->getDisplay());
        self::assertIsString($affichage);
        self::assertStringContainsString('1 nouvel(s) envoi(s) importé(s) et mis en file, 0 déjà connu(s), 0 vide(s) ignoré(s).', $affichage);
    }
}
