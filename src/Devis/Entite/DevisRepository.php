<?php

declare(strict_types=1);

namespace App\Devis\Entite;

use Doctrine\ORM\EntityManagerInterface;

final readonly class DevisRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Sert l'idempotence : un message redélivré ne doit pas produire un second
     * devis pour la même demande.
     */
    public function existePourDemande(Demande $demande): bool
    {
        $compte = $this->em->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(Devis::class, 'd')
            ->where('d.demande = :demande')
            ->setParameter('demande', $demande)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $compte > 0;
    }
}
