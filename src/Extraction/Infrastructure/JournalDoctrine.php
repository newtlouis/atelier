<?php

declare(strict_types=1);

namespace App\Extraction\Infrastructure;

use App\Extraction\Cout\AppelLlm;
use App\Extraction\Cout\GrilleTarifaire;
use App\Extraction\Cout\JournalDesAppels;
use App\Extraction\Cout\ModeleInconnu;
use App\Extraction\Cout\UsageAppel;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Writes the trace to the database.
 *
 * **Tracing must never fail an extraction.** If writing the measurement fails,
 * it is logged and we carry on: losing a row of statistics has no consequence,
 * losing a customer quote does. This is the reverse of the hierarchy applied to
 * invoicing.
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
            // A model with no rate deserves an alert, not a crash: most often
            // it is simply a new model to add to the table.
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
