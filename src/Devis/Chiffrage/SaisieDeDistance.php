<?php

declare(strict_types=1);

namespace App\Devis\Chiffrage;

use App\Devis\Entite\Devis;
use App\Devis\Entite\DevisRepository;
use App\Devis\Entite\LectureDeDemande;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;

/**
 * Chiffre une demande qui n'attendait que ses kilomètres.
 *
 * Repart de la lecture enregistrée : le modèle n'est jamais rappelé. Le devis
 * naît en brouillon, comme tous les autres ; Louis le relit avant l'envoi.
 */
final readonly class SaisieDeDistance
{
    public function __construct(
        private EntityManagerInterface $em,
        private ChiffreurDeDemande $chiffreur,
        private DevisRepository $devis,
    ) {}

    /**
     * @throws CommandeSousLeMinimum
     * @throws DemandeIncomplete
     */
    public function chiffre(LectureDeDemande $lecture, int $distanceKm): Devis
    {
        if ($this->devis->existePourDemande($lecture->demande)) {
            throw new LogicException('Cette demande a déjà un devis.');
        }

        $devis = new Devis(
            $lecture->demande,
            $this->chiffreur->chiffre($lecture->demandeExtraite()->avecDistance($distanceKm)),
        );
        $this->em->persist($devis);
        $this->em->flush();

        return $devis;
    }
}
