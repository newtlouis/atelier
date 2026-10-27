<?php

declare(strict_types=1);

namespace App\Extraction\Infrastructure;

use App\Extraction\Extracteur;
use App\Shared\Contrat\DemandeExtraite;
use LogicException;
use Throwable;

/**
 * Un extracteur qui ne parle à personne : il rejoue des réponses préparées.
 *
 * Sert aux tests, mais aussi au développement et aux démonstrations sans clé
 * d'API. Toute la chaîne — réception, chiffrage, devis — se met au point sans
 * dépenser un centime ; l'appel réel n'intervient qu'au tout dernier moment.
 *
 * Il vit dans src/ et non dans tests/ pour cette raison : c'est un composant
 * utilisable en développement, pas seulement un accessoire de test.
 */
final class ExtracteurFactice implements Extracteur
{
    /** @var list<DemandeExtraite|Throwable> */
    private array $reponses;

    /** @var list<string> */
    private array $textesRecus = [];

    /** @param list<DemandeExtraite|Throwable> $reponses */
    public function __construct(array $reponses = [])
    {
        $this->reponses = $reponses;
    }

    public static function renvoyant(DemandeExtraite ...$reponses): self
    {
        return new self(array_values($reponses));
    }

    public static function echouantAvec(Throwable $erreur): self
    {
        return new self([$erreur]);
    }

    public function extrait(string $texteClient): DemandeExtraite
    {
        $this->textesRecus[] = $texteClient;

        $reponse = array_shift($this->reponses);

        if (null === $reponse) {
            throw new LogicException(
                'ExtracteurFactice : aucune réponse préparée pour cet appel.',
            );
        }

        if ($reponse instanceof Throwable) {
            throw $reponse;
        }

        return $reponse;
    }

    /** @return list<string> Ce qui a réellement été soumis, dans l'ordre. */
    public function textesRecus(): array
    {
        return $this->textesRecus;
    }
}
