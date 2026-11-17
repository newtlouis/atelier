<?php

declare(strict_types=1);

namespace App\Tests\Extraction\Infrastructure\Claude;

use App\Extraction\Infrastructure\Claude\SchemaExtraction;
use PHPUnit\Framework\TestCase;

final class SchemaExtractionTest extends TestCase
{
    /**
     * L'API refuse un champ qui combine une liste de types et une énumération :
     * la v1 du schéma ne passait aucun appel réel, et aucun test ne le voyait
     * parce que la suite n'appelle jamais l'API. Ce test fige la règle hors ligne.
     */
    public function testAucunChampNeCombineUneListeDeTypesEtUneEnumeration(): void
    {
        $proprietes = SchemaExtraction::schema()['properties'];
        self::assertIsArray($proprietes);

        foreach ($proprietes as $nom => $definition) {
            self::assertIsArray($definition);
            self::assertFalse(
                isset($definition['enum']) && \is_array($definition['type'] ?? null),
                \sprintf('Le champ « %s » combine type multiple et enum : utiliser anyOf.', $nom),
            );
        }
    }

    public function testLaFamilleAccepteUneValeurConnueOuNull(): void
    {
        $proprietes = SchemaExtraction::schema()['properties'];
        self::assertIsArray($proprietes);
        $famille = $proprietes['famille'] ?? null;
        self::assertIsArray($famille);

        self::assertSame(
            [
                ['type' => 'string', 'enum' => ['table', 'mur']],
                ['type' => 'null'],
            ],
            $famille['anyOf'] ?? null,
        );
    }
}
