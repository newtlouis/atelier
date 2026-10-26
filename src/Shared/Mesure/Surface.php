<?php

declare(strict_types=1);

namespace App\Shared\Mesure;

use InvalidArgumentException;

/**
 * Une surface, en centimètres carrés entiers.
 *
 * Les dimensions sont saisies en millimètres — un plateau se mesure au
 * millimètre, pas au centimètre — mais la surface est exprimée en cm² : c'est
 * l'unité dans laquelle le barème est défini, et elle reste entière pour toutes
 * les dimensions réalistes (un plateau de 1 200 × 600 mm fait exactement
 * 7 200 cm²).
 */
final readonly class Surface
{
    private function __construct(public int $centimetresCarres)
    {
        if ($centimetresCarres <= 0) {
            throw new InvalidArgumentException("Une surface doit être strictement positive : {$centimetresCarres} cm².");
        }
    }

    public static function enCentimetresCarres(int $centimetresCarres): self
    {
        return new self($centimetresCarres);
    }

    /**
     * Surface d'un rectangle donné en millimètres.
     *
     * 1 cm² = 100 mm². La division est exacte dès que les deux côtés sont des
     * multiples de 10 mm ; dans le cas contraire on arrondit au cm² le plus
     * proche plutôt que de tronquer, pour ne pas sous-facturer systématiquement.
     */
    public static function rectangleEnMillimetres(int $longueurMm, int $largeurMm): self
    {
        if ($longueurMm <= 0 || $largeurMm <= 0) {
            throw new InvalidArgumentException(
                "Dimensions invalides : {$longueurMm} × {$largeurMm} mm.",
            );
        }

        return new self(intdiv($longueurMm * $largeurMm + 50, 100));
    }

    public static function enMetresCarres(int $metresCarres): self
    {
        return new self($metresCarres * 10_000);
    }

    public function estInferieureA(self $autre): bool
    {
        return $this->centimetresCarres < $autre->centimetresCarres;
    }

    public function estSuperieureA(self $autre): bool
    {
        return $this->centimetresCarres > $autre->centimetresCarres;
    }
}
