<?php

declare(strict_types=1);

namespace App\Tests\Extraction;

use App\Extraction\Entite\AppelLlm;
use App\Extraction\GrilleTarifaire;
use App\Extraction\JournalDoctrine;
use App\Extraction\UsageAppel;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Stringable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class JournalDoctrineTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;

        $this->em->getConnection()->executeStatement('TRUNCATE appel_llm');
    }

    public function testUnAppelEstTraceAvecSonCout(): void
    {
        $this->journal()->enregistre($this->usage());

        // clear() vide l'identity map : ce qui suit vient réellement de
        // PostgreSQL, et prouve que Doctrine sait hydrater des propriétés
        // publiques readonly promues.
        $this->em->clear();

        $appels = $this->em->getRepository(AppelLlm::class)->findAll();

        self::assertCount(1, $appels);
        self::assertSame(14_500_000, $appels[0]->coutNanoDollars);
        self::assertSame('v1', $appels[0]->versionPrompt);
        self::assertTrue($appels[0]->succes);
    }

    public function testUnEchecEstTraceAuMemeTitreQuUnSucces(): void
    {
        // Un taux d'échec invisible est un taux d'échec qu'on ne corrige pas.
        $this->journal()->enregistre(
            UsageAppel::echec('claude-opus-5', 'délai dépassé', latenceMs: 30_000),
        );

        // clear() vide l'identity map : ce qui suit vient réellement de
        // PostgreSQL, et prouve que Doctrine sait hydrater des propriétés
        // publiques readonly promues.
        $this->em->clear();

        $appels = $this->em->getRepository(AppelLlm::class)->findAll();

        self::assertCount(1, $appels);
        self::assertFalse($appels[0]->succes);
        self::assertSame('délai dépassé', $appels[0]->erreur);
        self::assertSame(0, $appels[0]->coutNanoDollars);
    }

    public function testUnModeleNonTarifeEstTraceSansCoutEtSignale(): void
    {
        $alertes = new CollecteurDAlertes();

        new JournalDoctrine($this->em, new GrilleTarifaire(), $alertes)
            ->enregistre($this->usage(modele: 'claude-inexistant-9'));

        // clear() vide l'identity map : ce qui suit vient réellement de
        // PostgreSQL, et prouve que Doctrine sait hydrater des propriétés
        // publiques readonly promues.
        $this->em->clear();

        $appels = $this->em->getRepository(AppelLlm::class)->findAll();

        self::assertCount(1, $appels, "la trace est écrite même sans tarif connu");
        self::assertSame(0, $appels[0]->coutNanoDollars);
        self::assertNotSame([], $alertes->avertissements);
    }

    private function journal(): JournalDoctrine
    {
        return new JournalDoctrine($this->em, new GrilleTarifaire(), new NullLogger());
    }

    private function usage(string $modele = 'claude-opus-5'): UsageAppel
    {
        return new UsageAppel(
            modele: $modele,
            versionPrompt: 'v1',
            tokensEntree: 1_400,
            tokensSortie: 300,
            tokensCacheEcriture: 0,
            tokensCacheLecture: 0,
            latenceMs: 850,
            succes: true,
        );
    }
}

/** Un journal applicatif qui retient ce qu'on lui signale, pour le vérifier. */
final class CollecteurDAlertes extends \Psr\Log\AbstractLogger implements LoggerInterface
{
    /** @var list<string> */
    public array $avertissements = [];

    /**
     * @param mixed                   $level
     * @param array<array-key, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (!\is_string($level) || !\in_array($level, ['warning', 'error'], true)) {
            return;
        }

        $this->avertissements[] = (string) $message;
    }
}
