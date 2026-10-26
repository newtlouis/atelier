<?php

declare(strict_types=1);

namespace App\Devis\Chiffrage;

use LogicException;

/**
 * Combien de nuits sur place pour poser N mètres carrés de mur.
 *
 * Trois paliers, parce que le temps de pose ne croît pas linéairement avec la
 * surface : l'installation, les masques et le séchage sont les mêmes pour 3 m²
 * que pour 6 m².
 *
 * ⚠ Les deux paliers hauts sont une interprétation à confirmer (voir JOURNAL.md) :
 * seul « moins de 7 m² → 5 nuitées » a été énoncé sans ambiguïté.
 */
final readonly class BaremeNuitees
{
    /** @var non-empty-list<array{surfaceMaxM2: int|null, nuitees: int}> */
    private array $paliers;

    public function __construct()
    {
        $this->paliers = [
            ['surfaceMaxM2' => 7, 'nuitees' => 5],
            ['surfaceMaxM2' => 14, 'nuitees' => 8],
            ['surfaceMaxM2' => null, 'nuitees' => 10],
        ];
    }

    public function pour(int $surfaceMetresCarres): int
    {
        foreach ($this->paliers as $palier) {
            if (null === $palier['surfaceMaxM2'] || $surfaceMetresCarres < $palier['surfaceMaxM2']) {
                return $palier['nuitees'];
            }
        }

        throw new LogicException('Le dernier palier doit être ouvert.');
    }
}
