<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Montant;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MontantTest extends TestCase
{
    public function testUnMontantNeNaitPasNegatif(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Montant::enCentimes(-1);
    }

    public function testLAdditionResteExacte(): void
    {
        // Le cas qui casse les flottants : 0,10 + 0,20 doit faire exactement 0,30.
        $somme = Montant::enCentimes(10)->plus(Montant::enCentimes(20));

        self::assertSame(30, $somme->centimes);
    }

    public function testLaSoustractionNePeutPasPasserSousZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Montant::enEuros(10)->moins(Montant::enEuros(11));
    }

    #[DataProvider('casDeTva')]
    public function testLaTvaEstArrondieAuCentimeLePlusProche(
        int $centimesHt,
        int $tauxCentiemes,
        int $tvaAttendue,
    ): void {
        self::assertSame(
            $tvaAttendue,
            Montant::enCentimes($centimesHt)->pourcentage($tauxCentiemes)->centimes,
        );
    }

    /** @return iterable<string, array{int, int, int}> */
    public static function casDeTva(): iterable
    {
        yield 'franchise : aucun taux, aucune TVA' => [180_000, 0, 0];
        yield '20 % sur 1 800 €' => [180_000, 2_000, 36_000];
        // 1 234,57 € à 20 % = 246,914 € → arrondi à 246,91 €.
        yield 'arrondi vers le bas' => [123_457, 2_000, 24_691];
        yield '20 % sur 0,25 € : 5 centimes pile, aucun arrondi' => [25, 2_000, 5];
        yield '5,5 % sur 100 €' => [10_000, 550, 550];
        // Avec un taux à 20 % la demi-unité est inatteignable (x/5 ne tombe
        // jamais sur .5) ; il faut un taux comme 5,5 % pour l'éprouver :
        // 1,00 € × 5,5 % = 5,5 centimes → doit monter à 6.
        yield 'exactement une demi-unité : on monte' => [100, 550, 6];
        // 0,99 € × 5,5 % = 5,445 centimes → doit rester à 5.
        yield 'juste sous la demi-unité : on reste' => [99, 550, 5];
    }

    public function testLAffichageEstLisibleParUnHumain(): void
    {
        self::assertSame('1 800,00 €', (string) Montant::enEuros(1_800));
        self::assertSame('0,05 €', (string) Montant::enCentimes(5));
        self::assertSame('12 345,67 €', (string) Montant::enCentimes(1_234_567));
    }
}
