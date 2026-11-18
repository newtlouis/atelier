<?php

declare(strict_types=1);

namespace App\Devis\Entite;

use App\Shared\Contrat\IssueDeLecture;
use Doctrine\ORM\EntityManagerInterface;

final readonly class LectureRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Les demandes qui n'attendent que leurs kilomètres et n'ont pas encore de
     * devis, de la plus ancienne à la plus récente : la première arrivée est
     * la première servie.
     *
     * @return list<LectureDeDemande>
     */
    public function enAttenteDeDistance(): array
    {
        /** @var list<LectureDeDemande> */
        return $this->em->createQueryBuilder()
            ->select('l')
            ->from(LectureDeDemande::class, 'l')
            ->join('l.demande', 'd')
            ->where('l.issue = :issue')
            ->andWhere('NOT EXISTS (SELECT v.id FROM ' . Devis::class . ' v WHERE v.demande = l.demande)')
            ->setParameter('issue', IssueDeLecture::EnAttenteDeDistance->value)
            ->orderBy('d.recueLe', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function pour(Demande $demande): ?LectureDeDemande
    {
        return $this->em->getRepository(LectureDeDemande::class)->findOneBy(['demande' => $demande]);
    }
}
