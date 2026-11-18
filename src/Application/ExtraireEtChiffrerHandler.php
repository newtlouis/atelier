<?php

declare(strict_types=1);

namespace App\Application;

use App\Devis\Chiffrage\ChiffreurDeDemande;
use App\Devis\Chiffrage\CommandeSousLeMinimum;
use App\Devis\Chiffrage\DemandeIncomplete;
use App\Devis\Entite\Demande;
use App\Devis\Entite\Devis;
use App\Devis\Entite\DevisRepository;
use App\Devis\Entite\LectureDeDemande;
use App\Devis\Entite\LectureRepository;
use App\Extraction\Aiguillage;
use App\Extraction\Extracteur;
use App\Shared\Contrat\IssueDeLecture;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Orchestre la chaîne complète : lire la demande, l'extraire, la chiffrer,
 * produire un devis en brouillon.
 *
 * Vit dans `Application` et non dans un domaine, parce qu'il est le seul
 * endroit du code autorisé à connaître à la fois `Extraction` et `Devis`.
 * Deptrac l'exprime : les domaines restent cloisonnés, l'orchestration est
 * explicitement au-dessus d'eux.
 *
 * Trois issues, et c'est la distinction qui compte :
 *
 * - **ça marche** : un devis en brouillon est créé ;
 * - **il manque une information** : ce n'est pas une panne, c'est le cas normal
 *   quand un client écrit « je voudrais une table ». On s'arrête sans réessayer :
 *   réessayer dix fois ne fera pas apparaître une dimension que le client n'a
 *   pas donnée ;
 * - **le service a échoué** : l'exception remonte, Messenger réessaie, puis
 *   range le message dans le transport d'échec.
 */
#[AsMessageHandler]
final readonly class ExtraireEtChiffrerHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private Extracteur $extracteur,
        private Aiguillage $aiguillage,
        private ChiffreurDeDemande $chiffreur,
        private DevisRepository $devis,
        private LectureRepository $lectures,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ExtraireEtChiffrer $message): void
    {
        $demande = $this->em->find(Demande::class, $message->demandeId);

        if (!$demande instanceof Demande) {
            // La demande a disparu entre l'envoi et la consommation. Rien à
            // faire, et surtout rien à réessayer.
            $this->logger->warning('Demande introuvable', ['id' => (string) $message->demandeId]);

            return;
        }

        if ($this->devis->existePourDemande($demande)) {
            // Idempotence : un message redélivré ne crée pas un second devis.
            $this->logger->info('Devis déjà établi, message ignoré', [
                'demande' => (string) $demande->id,
            ]);

            return;
        }

        $lecture = $this->lectures->pour($demande) ?? $this->lireEtEnregistrer($demande);

        if (IssueDeLecture::Chiffrable !== $lecture->issue) {
            // En attente de distance, questions, catalogue, hors sujet ou
            // irréalisable : la lecture est enregistrée, la suite est humaine.
            $this->logger->info('Demande lue, pas de devis automatique', [
                'demande' => (string) $demande->id,
                'issue' => $lecture->issue->value,
            ]);

            return;
        }

        $extraite = $lecture->demandeExtraite();

        try {
            $chiffrage = $this->chiffreur->chiffre($extraite);
        } catch (DemandeIncomplete $e) {
            $this->logger->info('Demande incomplète, chiffrage suspendu', [
                'demande' => (string) $demande->id,
                'questions' => $e->getMessage(),
            ]);

            return;
        } catch (CommandeSousLeMinimum $e) {
            $this->logger->info('Commande sous le minimum', [
                'demande' => (string) $demande->id,
                'motif' => $e->getMessage(),
            ]);

            return;
        }

        $this->em->persist(new Devis($demande, $chiffrage));
        $this->em->flush();
    }

    /**
     * Une demande venue du formulaire passe par l'aiguillage, qui évite le
     * modèle quand c'est sûr ; une demande saisie à la main n'a que son texte.
     * La hauteur standard s'applique dans les deux cas. La lecture est
     * enregistrée aussitôt : si le chiffrage échoue, elle ne sera pas refaite.
     */
    private function lireEtEnregistrer(Demande $demande): LectureDeDemande
    {
        $envoi = $demande->envoiFormulaire();
        $extraite = (
            null !== $envoi
            ? $this->aiguillage->lit($envoi)
            : $this->extracteur->extrait($demande->texteClient)
        )->avecHauteurStandard();

        $lecture = LectureDeDemande::depuis($demande, $extraite);
        $this->em->persist($lecture);
        $this->em->flush();

        return $lecture;
    }
}
