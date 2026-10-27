<?php

declare(strict_types=1);

namespace App\Extraction\Cout;

/**
 * Ce que coûte un jeton, par modèle.
 *
 * **Unité : le nano-dollar** (un milliardième de dollar), en entier. Trois
 * raisons :
 *
 * 1. les tarifs d'Anthropic sont libellés en dollars — convertir en euros
 *    exigerait un taux de change, donc une source extérieure, une mise à jour
 *    et une imprécision, pour un chiffre qui ne sert qu'à mesurer ;
 * 2. l'entier évite les flottants, comme partout ailleurs dans ce dépôt ;
 * 3. le nano-dollar permet d'écrire sans arrondi les tarifs fractionnaires :
 *    l'écriture en cache coûte 1,25 fois un jeton d'entrée, soit 6 250 pour
 *    Opus 5 — un nombre rond ici, impossible en micro-dollars.
 *
 * Un appel typique d'extraction revient à environ 15 millions de nano-dollars,
 * soit 1,5 centime de dollar.
 */
final class GrilleTarifaire
{
    /**
     * Tarifs au 26/10/2026, en nano-dollars par jeton.
     *
     * @var array<string, array{entree: int, sortie: int, cacheEcriture: int, cacheLecture: int}>
     */
    private const array TARIFS = [
        // 5 $ / 25 $ par million de jetons.
        'claude-opus-5' => ['entree' => 5_000, 'sortie' => 25_000, 'cacheEcriture' => 6_250, 'cacheLecture' => 500],
        // 2 $ / 10 $ par million.
        'claude-sonnet-5' => ['entree' => 2_000, 'sortie' => 10_000, 'cacheEcriture' => 2_500, 'cacheLecture' => 200],
        // 1 $ / 5 $ par million.
        'claude-haiku-4-5' => ['entree' => 1_000, 'sortie' => 5_000, 'cacheEcriture' => 1_250, 'cacheLecture' => 100],
    ];

    /**
     * @throws ModeleInconnu plutôt que de renvoyer zéro : un coût silencieusement
     *                       faux est pire qu'une absence de coût, parce qu'on le
     *                       croit
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

    /** Rend « 0,0152 $ » — pour l'affichage seulement. */
    public static function enDollars(int $nanoDollars): string
    {
        return number_format($nanoDollars / 1_000_000_000, 4, ',', ' ') . ' $';
    }
}
