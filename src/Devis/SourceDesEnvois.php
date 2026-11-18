<?php

declare(strict_types=1);

namespace App\Devis;

use App\Shared\Contrat\EnvoiFormulaire;

/**
 * D'où viennent les envois du formulaire du site.
 *
 * Un port, parce que la source changera : aujourd'hui une lecture de l'API
 * Netlify lancée à la main, demain un webhook quand l'application sera hébergée.
 */
interface SourceDesEnvois
{
    /**
     * Les envois de demande de devis, du plus récent au plus ancien. Les autres
     * formulaires (commandes en ligne) n'en font pas partie.
     *
     * @return list<EnvoiFormulaire>
     */
    public function envois(): array;
}
