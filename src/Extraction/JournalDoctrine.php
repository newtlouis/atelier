<?php

declare(strict_types=1);

namespace App\Extraction;

use App\Extraction\Entite\AppelLlm;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Écrit la trace en base.
 *
 * **Le traçage ne doit jamais faire échouer l'extraction.** Si l'écriture de la
 * mesure échoue, on le consigne dans les journaux et on continue : perdre une
 * ligne de statistique est sans conséquence, perdre un devis client ne l'est
 * pas. C'est la hiérarchie inverse de celle qu'on applique à la facturation.
 */
final readonly class JournalDoctrine implements JournalDesAppels
{
    public function __construct(
        private EntityManagerInterface $em,
        private GrilleTarifaire $grille,
        private LoggerInterface $logger,
    ) {}

    public function enregistre(UsageAppel $usage): void
    {
        try {
            $cout = $this->grille->coutEnNanoDollars($usage);
        } catch (ModeleInconnu $e) {
            // Un modèle non tarifé mérite une alerte, pas un plantage : il
            // s'agit le plus souvent d'un nouveau modèle à ajouter à la grille.
            $this->logger->warning('Appel au modèle non tarifé', [
                'modele' => $usage->modele,
                'exception' => $e,
            ]);

            $cout = 0;
        }

        try {
            $this->em->persist(AppelLlm::depuis($usage, $cout));
            $this->em->flush();
        } catch (Throwable $e) {
            $this->logger->error("Trace d'appel au modèle non enregistrée", [
                'modele' => $usage->modele,
                'exception' => $e,
            ]);
        }
    }
}
