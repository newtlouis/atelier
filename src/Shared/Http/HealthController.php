<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Point de santé : dit si l'application répond et si sa base est joignable.
 *
 * Renvoie 503 quand la base ne répond pas, pour qu'un répartiteur de charge ou
 * une sonde puisse retirer l'instance du service sans intervention humaine.
 */
final class HealthController extends AbstractController
{
    #[Route('/', name: 'root', methods: ['GET'])]
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function __invoke(Connection $connection): JsonResponse
    {
        try {
            $connection->executeQuery('SELECT 1');
            $base = 'ok';
        } catch (DbalException) {
            $base = 'injoignable';
        }

        return new JsonResponse(
            [
                'application' => 'atelier',
                'statut' => 'ok' === $base ? 'ok' : 'degrade',
                'base_de_donnees' => $base,
            ],
            'ok' === $base ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }
}
