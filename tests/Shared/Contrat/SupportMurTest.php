<?php

declare(strict_types=1);

namespace App\Tests\Shared\Contrat;

use App\Shared\Contrat\SupportMur;
use PHPUnit\Framework\TestCase;

final class SupportMurTest extends TestCase
{
    public function testSeulLeBoisEstIrrealisable(): void
    {
        foreach (SupportMur::cases() as $support) {
            self::assertSame(SupportMur::Bois !== $support, $support->estRealisable(), $support->value);
        }
    }
}
