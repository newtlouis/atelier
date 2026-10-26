<?php

declare(strict_types=1);

namespace App\Tests\Devis\Facturation;

use App\Devis\Chiffrage\ChiffreurTable;
use App\Devis\Chiffrage\DemandeTable;
use App\Devis\Entite\Demande;
use App\Devis\Entite\Devis;
use App\Devis\Entite\Facture;
use App\Devis\Facturation\FactureImmuable;
use App\Tests\Devis\Chiffrage\BaremeDeReference;
use DateTimeImmutable;
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
        $facture = Facture::emettre(
            $this->devisAccepte(),
            'FA-2026-0001',
            new DateTimeImmutable('2026-03-15'),
        );

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

    private function devisAccepte(): Devis
    {
        $chiffrage = new ChiffreurTable(BaremeDeReference::creer())->chiffre(
            new DemandeTable(longueurMm: 1_200, largeurMm: 600, hauteurMm: 350, distanceLivraisonKm: 100),
        );

        $devis = new Devis(new Demande('Une table basse effet roche, 120 × 60.'), $chiffrage);
        $devis->envoyer();
        $devis->accepter();

        return $devis;
    }
}
