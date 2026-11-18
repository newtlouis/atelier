<?php

declare(strict_types=1);

namespace App\Extraction;

use App\Shared\Contrat\DemandeExtraite;

/**
 * Turns a free-text request into structured data.
 *
 * This interface is the only point through which a language model enters the
 * application. The rest of the code does not know it exists — which is what
 * lets us state that the model never touches the computation of a price, and
 * what makes the test suite runnable with no network call and no spending.
 */
interface Extracteur
{
    /**
     * @throws ExtractionImpossible if the service is unavailable, or if the
     *                              response does not match the expected schema
     */
    public function extrait(string $texteClient): DemandeExtraite;
}
