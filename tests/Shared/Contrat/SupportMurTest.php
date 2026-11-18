<?php

declare(strict_types=1);

namespace App\Tests\Shared\Contrat;

use App\Shared\Contrat\SupportMur;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SupportMurTest extends TestCase
{
    /** @return iterable<string, array{?string, ?SupportMur}> */
    public static function valeursDuFormulaire(): iterable
    {
        yield 'maçonné' => ['mur-maconne', SupportMur::Maconne];
        yield 'enduit ou peint' => ['enduit-peinture', SupportMur::Enduit];
        yield 'cloison' => ['cloison', SupportMur::Placo];
        yield 'tapissé' => ['tapisserie', SupportMur::Tapisserie];
        yield 'autre : on demandera' => ['autre', null];
        yield 'je ne sais pas' => ['', null];
        yield 'champ absent' => [null, null];
    }

    #[DataProvider('valeursDuFormulaire')]
    public function testLitLesValeursDuFormulaire(?string $valeur, ?SupportMur $attendu): void
    {
        self::assertSame($attendu, SupportMur::depuisFormulaire($valeur));
    }

    public function testSeulLeBoisEstIrrealisable(): void
    {
        foreach (SupportMur::cases() as $support) {
            self::assertSame(SupportMur::Bois !== $support, $support->estRealisable(), $support->value);
        }
    }
}
