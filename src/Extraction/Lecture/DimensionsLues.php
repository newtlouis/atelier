<?php

declare(strict_types=1);

namespace App\Extraction\Lecture;

/**
 * What a message that reduces to dimensions yields, in millimetres.
 * Height is absent when the customer gave only two sides.
 */
final readonly class DimensionsLues
{
    public function __construct(
        public int $longueurMm,
        public int $largeurMm,
        public ?int $hauteurMm = null,
    ) {}
}
