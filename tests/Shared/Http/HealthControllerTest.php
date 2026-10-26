<?php

declare(strict_types=1);

namespace App\Tests\Shared\Http;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class HealthControllerTest extends WebTestCase
{
    public function testLePointDeSanteRepondQueToutVaBien(): void
    {
        $client = self::createClient();
        $client->request('GET', '/health');

        self::assertResponseIsSuccessful();

        $charge = json_decode(
            (string) $client->getResponse()->getContent(),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );

        self::assertSame(
            ['application' => 'atelier', 'statut' => 'ok', 'base_de_donnees' => 'ok'],
            $charge,
        );
    }

    public function testLaRacineRenvoieLeMemeEtat(): void
    {
        $client = self::createClient();
        $client->request('GET', '/');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
    }
}
