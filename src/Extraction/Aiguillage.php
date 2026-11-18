<?php

declare(strict_types=1);

namespace App\Extraction;

use App\Shared\Contrat\DemandeExtraite;
use App\Shared\Contrat\EnvoiFormulaire;
use App\Shared\Contrat\FamilleProduit;

/**
 * Choisit comment lire un envoi de formulaire : sans modèle quand c'est sûr,
 * avec le modèle sinon.
 *
 * La voie sans modèle est volontairement étroite : une table sur mesure dont le
 * message se réduit à des dimensions avec leur unité, comme la seule vraie
 * demande reçue (« 1600*800 mm »). Le champ projet dit que c'est une table, le
 * message dit sa taille, rien n'est à interpréter. Tout le reste, y compris un
 * message qui ajoute un seul mot, part vers le modèle avec les champs du
 * formulaire : mieux vaut un appel de trop qu'une précision perdue.
 */
final readonly class Aiguillage
{
    /** Valeur du champ projet du formulaire `devis` pour une table sur mesure. */
    private const string TABLE_SUR_MESURE = 'table-custom';

    public function __construct(
        private LecteurDeDimensions $lecteur,
        private Extracteur $extracteur,
    ) {}

    /**
     * @throws ExtractionImpossible si l'envoi part vers le modèle et que l'appel échoue
     */
    public function lit(EnvoiFormulaire $envoi): DemandeExtraite
    {
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
