<?php

declare(strict_types=1);

namespace App\Tests\Shared\Http;

use App\Shared\Http\HealthController;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ConnectionException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le cas dégradé, que le test fonctionnel ne peut pas produire : on ne va pas
 * éteindre PostgreSQL au milieu de la suite. La base est donc remplacée par une
 * doublure qui échoue.
 *
 * Sans ce test, supprimer purement et simplement l'appel à la base laisserait
 * le point de santé répondre « ok » en permanence — c'est Infection qui l'a
 * signalé, en montrant que la suppression de cet appel ne cassait rien.
 */
final class HealthControllerDegradeTest extends TestCase
{
    public function testQuandLaBaseNeRepondPasLePointDeSanteRenvoie503(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('executeQuery')
            ->willThrowException($this->createStub(ConnectionException::class));

        $reponse = new HealthController()($connection);

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $reponse->getStatusCode());

        $charge = json_decode((string) $reponse->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(
            ['application' => 'atelier', 'statut' => 'degrade', 'base_de_donnees' => 'injoignable'],
            $charge,
        );
    }
}
