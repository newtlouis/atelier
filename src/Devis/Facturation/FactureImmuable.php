<?php

declare(strict_types=1);

namespace App\Devis\Facturation;

use App\Devis\Entite\Facture;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use DomainException;

/**
 * Refuse toute modification d'une facture déjà émise.
 *
 * L'entité n'a aucun mutateur, ce qui suffit en pratique ; cet écouteur est la
 * seconde barrière, celle qui tient même si quelqu'un ajoute un setter dans
 * six mois sans savoir pourquoi il n'y en avait pas. Une erreur sur une facture
 * se corrige par un avoir, jamais en réécrivant l'original.
 */
#[AsEntityListener(event: Events::preUpdate, entity: Facture::class)]
final readonly class FactureImmuable
{
    public function preUpdate(Facture $facture, PreUpdateEventArgs $event): void
    {
        $champs = implode(', ', array_keys($event->getEntityChangeSet()));

        throw new DomainException(\sprintf(
            'La facture %s est émise : elle ne peut plus être modifiée (tentative sur : %s). '
            . 'Émettez un avoir.',
            $facture->numero,
            $champs,
        ));
    }
}
