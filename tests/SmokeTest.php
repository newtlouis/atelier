<?php

declare(strict_types=1);

namespace App\Tests;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Le test du lot 0 : l'application démarre et parle à sa base.
 *
 * Il ne teste aucun comportement métier — il n'y en a pas encore. Son rôle est
 * d'échouer bruyamment si le conteneur de services ou la connexion cassent,
 * ce qui est précisément ce qu'on veut savoir en premier dans une CI.
 */
final class SmokeTest extends KernelTestCase
{
    public function testLeConteneurDeServicesDemarre(): void
    {
        $kernel = self::bootKernel();

        self::assertSame('test', $kernel->getEnvironment());
        self::assertTrue(self::getContainer()->has('doctrine'));
    }

    public function testLaBaseDeDonneesRepond(): void
    {
        self::bootKernel();

        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);

        self::assertEquals(1, $connection->executeQuery('SELECT 1')->fetchOne());
    }
}
