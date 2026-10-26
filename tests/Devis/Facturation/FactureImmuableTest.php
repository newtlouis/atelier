<?php

declare(strict_types=1);

namespace App\Tests\Devis\Facturation;

use App\Devis\Entite\Facture;
use App\Devis\Facturation\FactureImmuable;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use DomainException;
use PHPUnit\Framework\TestCase;

/**
 * L'écouteur est la seconde barrière, derrière `readonly`.
 *
 * Il ne peut pas être déclenché par un test d'intégration — PHP refuse la
 * modification avant que Doctrine n'ait son mot à dire. Il est donc éprouvé
 * directement : le jour où quelqu'un retire un `readonly`, c'est lui qui tient.
 */
final class FactureImmuableTest extends TestCase
{
    public function testIlRefuseToutUpdateEtNommeLesChampsTouches(): void
    {
        $facture = $this->createStub(Facture::class);
        $facture->method('numero')->willReturn('FA-2026-0001');

        $event = $this->createStub(PreUpdateEventArgs::class);
        $event->method('getEntityChangeSet')->willReturn([
            'totalHorsTaxesCentimes' => [194_000, 1],
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'La facture FA-2026-0001 est émise : elle ne peut plus être modifiée '
            . '(tentative sur : totalHorsTaxesCentimes). Émettez un avoir.',
        );

        new FactureImmuable()->preUpdate($facture, $event);
    }
}
