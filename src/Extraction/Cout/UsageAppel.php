<?php

declare(strict_types=1);

namespace App\Extraction\Cout;

/**
 * What one call to the model consumed.
 *
 * Deliberately free of any notion of cost: tokens are a fact, price is a rate
 * card that changes. The second is derived from the first, elsewhere.
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

    public static function echec(
        string $modele,
        string $versionPrompt,
        string $erreur,
        int $latenceMs,
    ): self {
        return new self(
            modele: $modele,
            versionPrompt: $versionPrompt,
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
     * Did the cache actually get used?
     *
     * The question is not rhetorical: below a certain number of tokens a prefix
     * is not cached at all, and nothing says so. Without this measurement,
     * "we turned caching on" is a belief, not a fact.
     */
    public function cacheUtilise(): bool
    {
        return $this->tokensCacheLecture > 0;
    }
}
