<?php

declare(strict_types=1);

namespace App\Extraction;

use RuntimeException;
use Throwable;

/**
 * L'extraction a échoué — service indisponible, réponse hors schéma, budget
 * dépassé.
 *
 * À distinguer d'une extraction réussie mais incomplète : celle-ci revient
 * normalement, avec ses `champsManquants`. Ici, il n'y a pas de réponse du
 * tout, et le message doit repartir en file de réessai.
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
