<?php

declare(strict_types=1);

namespace App\Devis\Entite;

use Doctrine\ORM\EntityManagerInterface;

final readonly class LectureRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function pour(Demande $demande): ?LectureDeDemande
    {
        return $this->em->getRepository(LectureDeDemande::class)->findOneBy(['demande' => $demande]);
    }
}
