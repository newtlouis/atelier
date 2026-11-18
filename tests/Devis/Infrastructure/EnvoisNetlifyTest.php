<?php

declare(strict_types=1);

namespace App\Tests\Devis\Infrastructure;

use App\Devis\Infrastructure\EnvoisNetlify;
use App\Shared\Contrat\EnvoiFormulaire;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use UnexpectedValueException;

/**
 * Contre une réponse enregistrée : la forme est celle de l'API Netlify Forms
 * (relevée le 18/11/2026), le contenu est entièrement fictif.
 */
final class EnvoisNetlifyTest extends TestCase
{
    private static function reponseEnregistree(): string
    {
        $json = file_get_contents(__DIR__ . '/reponses/soumissions-netlify.json');
        self::assertIsString($json);

        return $json;
    }

    public function testLesDemandesSontLuesEtLaCommandeEnLigneIgnoree(): void
    {
        $http = new MockHttpClient([new MockResponse(self::reponseEnregistree())]);

        $envois = new EnvoisNetlify($http, 'jeton', 'site-1')->envois();

        self::assertSame(
            ['000000000000000000000004', '000000000000000000000003', '000000000000000000000001'],
            array_map(static fn(EnvoiFormulaire $e): string => $e->idNetlify, $envois),
        );
    }

    public function testUnEnvoiReprendLesChampsUtilesEtRienDAutre(): void
    {
        $http = new MockHttpClient([new MockResponse(self::reponseEnregistree())]);

        $envois = new EnvoisNetlify($http, 'jeton', 'site-1')->envois();

        self::assertEquals(
            new EnvoiFormulaire(
                idNetlify: '000000000000000000000003',
                formulaire: 'devis',
                recuLe: new DateTimeImmutable('2026-09-07T11:52:22.167Z'),
                emailClient: 'client@example.org',
                message: '1600*800 mm',
                projet: 'table-custom',
            ),
            $envois[1],
        );
        self::assertSame("Surface : 8 m²\nSupport : tapisserie\nVille : Colmar\nMessage : Pour le salon", $envois[2]->texteClient());
    }

    public function testLaRequeteViseLeSiteAvecLeJeton(): void
    {
        $http = new MockHttpClient(static function (string $methode, string $url, array $options): MockResponse {
            self::assertSame('GET', $methode);
            self::assertSame('https://api.netlify.com/api/v1/sites/site-1/submissions?page=1&per_page=100', $url);
            self::assertIsArray($options['headers'] ?? null);
            self::assertContains('Authorization: Bearer jeton', $options['headers']);

            return new JsonMockResponse([]);
        });

        self::assertSame([], new EnvoisNetlify($http, 'jeton', 'site-1')->envois());
    }

    public function testToutesLesPagesSontLues(): void
    {
        $pagePleine = [];
        for ($i = 0; $i < 100; ++$i) {
            $pagePleine[] = ['id' => "p1-{$i}", 'form_name' => 'devis', 'created_at' => '2026-09-07T11:52:22.167Z', 'data' => ['message' => 'x']];
        }
        $urls = [];
        $reponses = [
            new JsonMockResponse($pagePleine),
            new JsonMockResponse([['id' => 'p2-0', 'form_name' => 'devis', 'created_at' => '2026-09-07T11:52:22.167Z', 'data' => []]]),
        ];
        $http = new MockHttpClient(static function (string $methode, string $url) use (&$urls, &$reponses): MockResponse {
            $urls[] = $url;

            return array_shift($reponses) ?? new JsonMockResponse([]);
        });

        $envois = new EnvoisNetlify($http, 'jeton', 'site-1')->envois();

        self::assertCount(101, $envois);
        self::assertSame('p2-0', $envois[100]->idNetlify);
        self::assertSame([
            'https://api.netlify.com/api/v1/sites/site-1/submissions?page=1&per_page=100',
            'https://api.netlify.com/api/v1/sites/site-1/submissions?page=2&per_page=100',
        ], $urls);
    }

    /** @return iterable<string, array{array<mixed>}> */
    public static function envoisIllisibles(): iterable
    {
        yield 'sans identifiant' => [['form_name' => 'devis', 'created_at' => '2026-09-07T11:52:22.167Z']];
        yield 'sans formulaire' => [['id' => 'x', 'created_at' => '2026-09-07T11:52:22.167Z']];
        yield 'date illisible' => [['id' => 'x', 'form_name' => 'devis', 'created_at' => 'hier']];
    }

    /**
     * Une réponse qui change de forme doit arrêter l'import, pas le laisser
     * perdre des demandes en silence.
     *
     * @param array<mixed> $soumission
     */
    #[DataProvider('envoisIllisibles')]
    public function testUnEnvoiIllisibleArreteLImport(array $soumission): void
    {
        $this->expectException(UnexpectedValueException::class);

        EnvoisNetlify::envoiDepuis($soumission);
    }

    public function testUnEnvoiQuiNEstPasUnObjetArreteLImport(): void
    {
        $this->expectException(UnexpectedValueException::class);

        new EnvoisNetlify(new MockHttpClient([new JsonMockResponse(['texte'])]), 'jeton', 'site-1')->envois();
    }

    public function testDesChampsAbsentsOuMalTypesValentAbsence(): void
    {
        $envoi = EnvoisNetlify::envoiDepuis(['id' => 'x', 'form_name' => 'contact-pro', 'created_at' => '2026-09-07T11:52:22.167Z', 'data' => ['besoin' => 3]]);

        self::assertInstanceOf(EnvoiFormulaire::class, $envoi);
        self::assertNull($envoi->besoin);
        self::assertSame('', $envoi->message);
        self::assertNull($envoi->emailClient);
    }
}
