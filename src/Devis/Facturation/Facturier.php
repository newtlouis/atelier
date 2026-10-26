<?php

declare(strict_types=1);

namespace App\Devis\Facturation;

use App\Devis\Entite\Devis;
use App\Devis\Entite\Facture;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Émet la facture d'un devis accepté.
 *
 * Le numéro et la facture sont écrits dans la même transaction : si
 * l'enregistrement échoue, le numéro n'est pas consommé. C'est tout l'intérêt
 * du compteur en base plutôt que d'une séquence.
 */
final readonly class Facturier
{
    public function __construct(
        private EntityManagerInterface $em,
        private NumeroteurDeFactures $numeroteur,
    ) {}

    public function emettre(Devis $devis, ?DateTimeImmutable $date = null): Facture
    {
        $date ??= new DateTimeImmutable();

        // Hors transaction, volontairement : un devis non accepté est un refus
        // métier courant, et il ne doit pas fermer l'EntityManager. Seul ce qui
        // doit être atomique entre dans la transaction.
        $devis->verifieFacturable();

        return $this->em->wrapInTransaction(
            function () use ($devis, $date): Facture {
                $devis->marquerFacture();

                $facture = Facture::emettre(
                    $devis,
                    $this->numeroteur->prochainNumero((int) $date->format('Y')),
                    $date,
                );

                $this->em->persist($facture);
                $this->em->flush();

                return $facture;
            },
        );
    }
}
