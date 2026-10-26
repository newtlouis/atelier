<?php

declare(strict_types=1);

namespace App\Shared\Argent;

use InvalidArgumentException;
use Stringable;

/**
 * Un montant d'argent, en centimes entiers.
 *
 * Pourquoi pas de float : 0.1 + 0.2 !== 0.3 en binaire. Sur un devis de trois
 * lignes l'écart est invisible, sur une facture il est faux, et une facture
 * fausse n'est pas une imprécision — c'est un document comptable erroné.
 * Toute l'arithmétique se fait donc sur des entiers, et l'arrondi n'apparaît
 * qu'à un seul endroit : l'application d'un pourcentage.
 */
final readonly class Montant implements Stringable
{
    private function __construct(public int $centimes)
    {
        if ($centimes < 0) {
            throw new InvalidArgumentException("Un montant ne peut pas être négatif : {$centimes} centimes.");
        }
    }

    public static function enCentimes(int $centimes): self
    {
        return new self($centimes);
    }

    public static function enEuros(int $euros): self
    {
        return new self($euros * 100);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function plus(self $autre): self
    {
        return new self($this->centimes + $autre->centimes);
    }

    public function moins(self $autre): self
    {
        return new self($this->centimes - $autre->centimes);
    }

    public function multiplieParEntier(int $facteur): self
    {
        if ($facteur < 0) {
            throw new InvalidArgumentException("Facteur négatif interdit : {$facteur}.");
        }

        return new self($this->centimes * $facteur);
    }

    /**
     * Applique un pourcentage exprimé en centièmes de point, pour éviter
     * d'introduire un flottant dans le taux lui-même : 20 % s'écrit 2000,
     * 5,5 % s'écrit 550.
     *
     * C'est le seul endroit où un arrondi se produit. Il est au centime le plus
     * proche, et la moitié est arrondie vers le haut — règle usuelle en France
     * pour les prix TTC.
     */
    public function pourcentage(int $centiemesDePoint): self
    {
        if ($centiemesDePoint < 0) {
            throw new InvalidArgumentException("Taux négatif interdit : {$centiemesDePoint}.");
        }

        return new self(intdiv($this->centimes * $centiemesDePoint + 5000, 10000));
    }

    public function estInferieurA(self $autre): bool
    {
        return $this->centimes < $autre->centimes;
    }

    public function estSuperieurOuEgalA(self $autre): bool
    {
        return $this->centimes >= $autre->centimes;
    }

    public function equivaut(self $autre): bool
    {
        return $this->centimes === $autre->centimes;
    }

    /**
     * Rend « 1 800,00 € ». Destiné à l'affichage uniquement : aucun calcul ne
     * doit repartir de cette chaîne.
     */
    public function __toString(): string
    {
        $euros = intdiv($this->centimes, 100);
        $centimes = $this->centimes % 100;

        return \sprintf(
            '%s,%02d €',
            number_format($euros, 0, ',', ' '),
            $centimes,
        );
    }
}
