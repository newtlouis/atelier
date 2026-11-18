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
 * Choisit comment lire un envoi de formulaire : sans modèle quand c'est sûr,
 * avec le modèle sinon.
 *
 * Deux voies sans modèle, volontairement étroites :
 *
 * - **une commande du catalogue** (projet « table-standard », ou formulaire
 *   « devis-piece ») : son prix est connu, elle ne se chiffre jamais. Le
 *   formulaire le dit ; nul besoin de payer un appel pour le redécouvrir ;
 * - **une table sur mesure dont le message se réduit à des dimensions**, comme
 *   la seule vraie demande reçue (« 1600*800 mm »).
 *
 * Tout le reste, y compris un message qui ajoute un seul mot, part vers le
 * modèle avec les champs du formulaire : mieux vaut un appel de trop qu'une
 * précision perdue. Le support coché dans le formulaire complète ensuite la
 * lecture, quelle que soit la voie.
 */
final readonly class Aiguillage
{
    /** Valeurs du champ projet du formulaire `devis`. */
    private const string TABLE_SUR_MESURE = 'table-custom';
    private const string TABLE_DU_CATALOGUE = 'table-standard';
    /** Le formulaire de commande d'une pièce du catalogue. */
    private const string FORMULAIRE_PIECE = 'devis-piece';

    public function __construct(
        private LecteurDeDimensions $lecteur,
        private Extracteur $extracteur,
    ) {}

    /**
     * @throws ExtractionImpossible si l'envoi part vers le modèle et que l'appel échoue
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

        // La distance n'est jamais lue ici : le formulaire donne au mieux une
        // ville, et c'est Louis qui saisit les kilomètres.
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
