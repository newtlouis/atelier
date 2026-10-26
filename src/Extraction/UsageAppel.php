<?php

declare(strict_types=1);

namespace App\Extraction;

/**
 * Ce qu'a consommé un appel au modèle.
 *
 * Volontairement sans notion de coût : les jetons sont un fait, le prix est une
 * grille tarifaire qui change. Le second se calcule à partir du premier, ailleurs.
 */
final readonly class UsageAppel
{
    public function __construct(
        public string $modele,
        public string $versionPrompt,
        public int $tokensEntree,
        public int $tokensSortie,
        public int $tokensCacheEcriture,
        public int $tokensCacheLecture,
        public int $latenceMs,
        public bool $succes,
        public ?string $erreur = null,
    ) {}

    public static function echec(string $modele, string $erreur, int $latenceMs): self
    {
        return new self(
            modele: $modele,
            versionPrompt: SchemaExtraction::VERSION,
            tokensEntree: 0,
            tokensSortie: 0,
            tokensCacheEcriture: 0,
            tokensCacheLecture: 0,
            latenceMs: $latenceMs,
            succes: false,
            erreur: $erreur,
        );
    }

    /**
     * Le cache a-t-il réellement servi ?
     *
     * La question n'est pas rhétorique : en dessous d'un certain nombre de
     * jetons, un préfixe n'est pas mis en cache et rien ne le signale. Sans
     * cette mesure, « on a activé le cache » est une croyance.
     */
    public function cacheUtilise(): bool
    {
        return $this->tokensCacheLecture > 0;
    }
}
