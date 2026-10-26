<?php

declare(strict_types=1);

namespace App\Extraction;

use App\Shared\Contrat\DemandeExtraite;

/**
 * Transforme une demande en texte libre en données structurées.
 *
 * Cette interface est l'unique point par lequel un modèle de langage entre
 * dans l'application. Tout le reste du code ignore qu'il existe — c'est ce qui
 * permet d'affirmer que le modèle ne touche jamais au calcul d'un prix, et ce
 * qui rend les tests exécutables sans appel réseau ni dépense.
 */
interface Extracteur
{
    /**
     * @throws ExtractionImpossible si le service est indisponible ou si la
     *                              réponse ne respecte pas le schéma attendu
     */
    public function extrait(string $texteClient): DemandeExtraite;
}
