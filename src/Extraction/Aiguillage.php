<?php

declare(strict_types=1);

namespace App\Extraction;

use App\Extraction\Lecture\LecteurDeDimensions;
use App\Shared\Contrat\DemandeExtraite;
use App\Shared\Contrat\EnvoiFormulaire;
use App\Shared\Contrat\FamilleProduit;
use App\Shared\Contrat\NatureDemande;
use App\Shared\Contrat\SupportMur;

/**
 * Decides how to read a form submission: without the model when that is safe,
 * with the model otherwise.
 *
 * Two model-free paths, deliberately narrow:
 *
 * - **a catalogue order** (project "table-standard", or the "devis-piece"
 *   form): its price is known, it never gets quoted. The form says so; no need
 *   to pay for a call to rediscover it;
 * - **a bespoke table whose message reduces to dimensions**, like the only real
 *   request received so far ("1600*800 mm").
 *
 * Everything else, including a message that adds a single word, goes to the
 * model along with the form fields: one call too many beats one detail lost.
 * The mounting surface ticked on the form then completes the reading, whichever
 * path was taken.
 */
final readonly class Aiguillage
{
    /** Valeurs du champ projet du formulaire `devis`. */
    private const string TABLE_SUR_MESURE = 'table-custom';
    private const string TABLE_DU_CATALOGUE = 'table-standard';
    /** The order form for a catalogue piece. */
    private const string FORMULAIRE_PIECE = 'devis-piece';

    public function __construct(
        private LecteurDeDimensions $lecteur,
        private Extracteur $extracteur,
    ) {}

    /**
     * @throws ExtractionImpossible if the submission goes to the model and the call fails
     */
    public function lit(EnvoiFormulaire $envoi): DemandeExtraite
    {
        // The distance is always Louis's measurement, never the client's claim.
        return $this->lecture($envoi)
            ->avecSupport(self::supportDuFormulaire($envoi->support))
            ->sansDistance();
    }

    /**
     * The values of the site form's "support" field. "autre" and "je ne sais
     * pas" (empty value) say nothing: null, hence a question to the client.
     * The site's vocabulary stays here, with table-custom and devis-piece.
     */
    private static function supportDuFormulaire(?string $valeur): ?SupportMur
    {
        return match ($valeur) {
            'mur-maconne' => SupportMur::Maconne,
            'enduit-peinture' => SupportMur::Enduit,
            'cloison' => SupportMur::Placo,
            'tapisserie' => SupportMur::Tapisserie,
            default => null,
        };
    }

    private function lecture(EnvoiFormulaire $envoi): DemandeExtraite
    {
        if (self::FORMULAIRE_PIECE === $envoi->formulaire || self::TABLE_DU_CATALOGUE === $envoi->projet) {
            return new DemandeExtraite(
                famille: self::TABLE_DU_CATALOGUE === $envoi->projet ? FamilleProduit::Table : null,
                longueurMm: null,
                largeurMm: null,
                hauteurMm: null,
                surfaceMetresCarres: null,
                distanceKm: null,
                villeLivraison: $envoi->ville,
                nature: NatureDemande::CommandeCatalogue,
            );
        }

        $dimensions = self::TABLE_SUR_MESURE === $envoi->projet
            ? $this->lecteur->lit($envoi->message)
            : null;

        if (null === $dimensions) {
            return $this->extracteur->extrait($envoi->texteClient());
        }

        // Distance is never read here: the form gives a town at best, and the
        // kilometres are entered by hand afterwards.
        return new DemandeExtraite(
            famille: FamilleProduit::Table,
            nature: NatureDemande::Projet,
            longueurMm: $dimensions->longueurMm,
            largeurMm: $dimensions->largeurMm,
            hauteurMm: $dimensions->hauteurMm,
            surfaceMetresCarres: null,
            distanceKm: null,
            champsManquants: ['distance'],
            villeLivraison: $envoi->ville,
        );
    }
}
