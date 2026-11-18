<?php

declare(strict_types=1);

namespace App\Extraction;

use RuntimeException;
use Throwable;

/**
 * Extraction failed: service unavailable, response off-schema, budget exceeded.
 *
 * Not to be confused with an extraction that succeeded but came back
 * incomplete: that one returns normally, carrying its `champsManquants`
 * (*missing fields*). Here there is no response at all, and the message has to
 * go back onto the retry queue.
 */
final class ExtractionImpossible extends RuntimeException
{
    public static function reponseHorsSchema(string $detail): self
    {
        return new self("La réponse du modèle ne respecte pas le schéma attendu : {$detail}");
    }

    public static function serviceIndisponible(string $detail, ?Throwable $cause = null): self
    {
        return new self("Le service d'extraction est indisponible : {$detail}", previous: $cause);
    }

    public static function budgetDepasse(int $tokensDemandes, int $plafond): self
    {
        return new self(
            "La demande dépasse le budget alloué : {$tokensDemandes} jetons pour un plafond de {$plafond}.",
        );
    }
}
