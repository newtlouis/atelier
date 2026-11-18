<?php

declare(strict_types=1);

namespace App\Extraction\Cout;

/**
 * What one token costs, per model.
 *
 * **Unit: the nano-dollar** (one billionth of a dollar), as an integer. Three
 * reasons:
 *
 * 1. Anthropic prices are quoted in dollars — converting to euros would demand
 *    an exchange rate, therefore an outside source, an update and an
 *    imprecision, all for a number whose only job is to measure;
 * 2. integers keep floats out, as everywhere else in this repository;
 * 3. the nano-dollar expresses fractional rates without rounding: a cache write
 *    costs 1.25 times an input token, i.e. 125 for Haiku 5.5 — a round number
 *    here, impossible in micro-dollars.
 *
 * A typical extraction call on Opus 5.5 comes to about 12 million nano-dollars,
 * that is 1.2 US cents.
 */
final class GrilleTarifaire
{
    /**
     * Rates in nano-dollars per token.
     *
     * Previous-generation models stay in the table: calls already logged carry
     * their identifier, and their cost must remain computable. Removing a row
     * would erase history.
     *
     * @var array<string, array{entree: int, sortie: int, cacheEcriture: int, cacheLecture: int}>
     */
    private const array TARIFS = [
        // Generation 5.5, rates as of 2026-11-17.
        // $4 / $20 per million; cache reads are $0.20, a twentieth.
        'claude-opus-5-5' => ['entree' => 4_000, 'sortie' => 20_000, 'cacheEcriture' => 5_000, 'cacheLecture' => 200],
        // $2 / $10 per million; cache reads at $0.10.
        'claude-sonnet-5-5' => ['entree' => 2_000, 'sortie' => 10_000, 'cacheEcriture' => 2_500, 'cacheLecture' => 100],
        // $0.10 / $0.50 per million, for a request under 100,000 tokens.
        'claude-haiku-5-5' => ['entree' => 100, 'sortie' => 500, 'cacheEcriture' => 125, 'cacheLecture' => 10],

        // Previous generation, rates as of 2026-10-26.
        // $5 / $25 per million tokens.
        'claude-opus-5' => ['entree' => 5_000, 'sortie' => 25_000, 'cacheEcriture' => 6_250, 'cacheLecture' => 500],
        // $2 / $10 per million.
        'claude-sonnet-5' => ['entree' => 2_000, 'sortie' => 10_000, 'cacheEcriture' => 2_500, 'cacheLecture' => 200],
        // $1 / $5 per million.
        'claude-haiku-4-5' => ['entree' => 1_000, 'sortie' => 5_000, 'cacheEcriture' => 1_250, 'cacheLecture' => 100],
    ];

    /**
     * @throws ModeleInconnu rather than returning zero: a silently wrong cost is
     *                       worse than no cost at all, because it gets believed
     */
    public function coutEnNanoDollars(UsageAppel $usage): int
    {
        $tarif = self::TARIFS[$usage->modele] ?? throw new ModeleInconnu($usage->modele);

        return $usage->tokensEntree * $tarif['entree']
            + $usage->tokensSortie * $tarif['sortie']
            + $usage->tokensCacheEcriture * $tarif['cacheEcriture']
            + $usage->tokensCacheLecture * $tarif['cacheLecture'];
    }

    /** @return list<string> */
    public static function modelesConnus(): array
    {
        return array_keys(self::TARIFS);
    }

    /** Renders "0,0152 $" (French formatting) — for display only. */
    public static function enDollars(int $nanoDollars): string
    {
        return number_format($nanoDollars / 1_000_000_000, 4, ',', ' ') . ' $';
    }
}
