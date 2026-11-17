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
 *    l'écriture en cache coûte 1,25 fois un jeton d'entrée, soit 125 pour
 *    Haiku 5.5 — un nombre rond ici, impossible en micro-dollars.
 *
 * Un appel typique d'extraction sur Opus 5.5 revient à environ 12 millions de
 * nano-dollars, soit 1,2 centime de dollar.
 */
final class GrilleTarifaire
{
    /**
     * Tarifs en nano-dollars par jeton.
     *
     * Les modèles de la génération précédente restent dans la grille : les
     * appels déjà journalisés portent leur identifiant, et leur coût doit
     * rester calculable. Retirer une ligne effacerait l'historique.
     *
     * @var array<string, array{entree: int, sortie: int, cacheEcriture: int, cacheLecture: int}>
     */
    private const array TARIFS = [
        // Génération 5.5, tarifs au 17/11/2026.
        // 4 $ / 20 $ par million ; la relecture du cache est à 0,20 $, un vingtième.
        'claude-opus-5-5' => ['entree' => 4_000, 'sortie' => 20_000, 'cacheEcriture' => 5_000, 'cacheLecture' => 200],
        // 2 $ / 10 $ par million ; relecture à 0,10 $.
        'claude-sonnet-5-5' => ['entree' => 2_000, 'sortie' => 10_000, 'cacheEcriture' => 2_500, 'cacheLecture' => 100],
        // 0,10 $ / 0,50 $ par million, pour une requête de moins de 100 000 jetons.
        'claude-haiku-5-5' => ['entree' => 100, 'sortie' => 500, 'cacheEcriture' => 125, 'cacheLecture' => 10],

        // Génération précédente, tarifs au 26/10/2026.
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
