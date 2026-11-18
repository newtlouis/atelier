<?php

declare(strict_types=1);

namespace App\Tests\Devis\Chiffrage;

use App\Devis\Chiffrage\FabriqueDeBareme;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class FabriqueDeBaremeTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function configurationReelle(): array
    {
        $fichier = Yaml::parseFile(\dirname(__DIR__, 3) . '/config/packages/atelier.yaml');
        self::assertIsArray($fichier);
        self::assertIsArray($fichier['parameters'] ?? null);
        $bareme = $fichier['parameters']['atelier.bareme'] ?? null;
        self::assertIsArray($bareme);

        /** @var array<string, mixed> $bareme */
        return $bareme;
    }

    public function testLeRetraitDeLaTapisserieCoute300EurosHorsTaxes(): void
    {
        $bareme = FabriqueDeBareme::creer(self::configurationReelle());

        self::assertSame(30_000, $bareme->fraisRetraitTapisserie->centimes);
    }

    public function testUnBaremeSansFraisDeTapisserieEmpecheLeDemarrage(): void
    {
        $configuration = self::configurationReelle();
        unset($configuration['frais_retrait_tapisserie_centimes']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('la clé « frais_retrait_tapisserie_centimes » est absente');

        FabriqueDeBareme::creer($configuration);
    }
}
