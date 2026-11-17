<?php

declare(strict_types=1);

namespace App\Tests\Extraction;

use App\Extraction\DimensionsLues;
use App\Extraction\LecteurDeDimensions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LecteurDeDimensionsTest extends TestCase
{
    public function testLaSeuleDemandeReelleSeLitSansModele(): void
    {
        // Le message exact reçu par le formulaire le 17/11/2026.
        self::assertEquals(
            new DimensionsLues(longueurMm: 1_600, largeurMm: 800),
            new LecteurDeDimensions()->lit('160 × 80 cm'),
        );
    }

    /** @return iterable<string, array{string, int, int, ?int}> */
    public static function formatsReconnus(): iterable
    {
        yield 'x minuscule, sans espaces' => ['160x80cm', 1_600, 800, null];
        yield 'étoile' => ['160 * 80 cm', 1_600, 800, null];
        yield 'majuscules et espaces autour' => ['  160 X 80 CM ', 1_600, 800, null];
        yield 'trois côtés : la hauteur' => ['120 x 60 x 35 cm', 1_200, 600, 350];
        yield 'mètres décimaux à virgule' => ['1,60 x 0,80 m', 1_600, 800, null];
        yield 'mètres décimaux à point' => ['1.6 x 0.8 m', 1_600, 800, null];
        yield 'millimètres' => ['1600 x 800 mm', 1_600, 800, null];
        yield 'notation 1m60' => ['1m60 x 80 cm', 1_600, 800, null];
        yield 'notation 1m5 : cinquante centimètres' => ['1m5 x 80 cm', 1_500, 800, null];
        yield 'unité sur chaque côté' => ['160cm x 80cm', 1_600, 800, null];
        yield 'unités mélangées' => ['1,6 m x 80 cm', 1_600, 800, null];
        yield 'le plus grand côté est la longueur' => ['80 x 160 cm', 1_600, 800, null];
        yield 'demi-centimètre' => ['160,5 x 80 cm', 1_605, 800, null];
        yield 'exactement la borne' => ['10000 x 80 mm', 10_000, 80, null];
        yield 'un millimètre' => ['1 x 1 mm', 1, 1, null];
    }

    #[DataProvider('formatsReconnus')]
    public function testLitLesFormatsCourants(string $message, int $longueur, int $largeur, ?int $hauteur): void
    {
        self::assertEquals(
            new DimensionsLues($longueur, $largeur, $hauteur),
            new LecteurDeDimensions()->lit($message),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function messagesLaissesAuModele(): iterable
    {
        yield 'sans unité : cm ou mm ?' => ['160 x 80'];
        yield 'une unité sur un seul côté' => ['160 cm x 80'];
        yield 'une unité seulement dans la notation 1m60' => ['2 x 1m60'];
        yield 'autre chose que des dimensions' => ['160 x 80 cm, plutôt en roche claire'];
        yield 'une phrase' => ['Bonjour, une table de 160 x 80 cm'];
        yield 'un seul côté' => ['160 cm'];
        yield 'quatre côtés' => ['160 x 80 x 35 x 10 cm'];
        yield 'entre deux millimètres' => ['160,25 x 80 cm'];
        yield 'des mètres au lieu de centimètres' => ['160 x 80 m'];
        yield 'juste au-dessus de la borne' => ['10001 x 80 mm'];
        yield 'un côté nul' => ['160 x 0 cm'];
        yield 'vide' => [''];
    }

    #[DataProvider('messagesLaissesAuModele')]
    public function testNeDevineRien(string $message): void
    {
        self::assertNull(new LecteurDeDimensions()->lit($message));
    }
}
