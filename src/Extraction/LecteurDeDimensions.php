<?php

declare(strict_types=1);

namespace App\Extraction;

/**
 * Lit un message réduit à des dimensions — « 160 × 80 cm », « 1m60 x 80 cm » —
 * sans appeler de modèle.
 *
 * La seule demande reçue par le formulaire disait exactement « 160 × 80 cm ».
 * Pour ce format, un modèle de langage coûte un centime et trois secondes pour
 * un résultat qu'une expression régulière donne sans erreur possible. Le modèle
 * reste réservé aux messages rédigés en phrases.
 *
 * Deux règles de prudence :
 *
 * - **le message entier doit être des dimensions.** « 160 x 80 cm, plutôt en
 *   roche claire » contient autre chose : on ne le lit pas ici, sans quoi la
 *   teinte serait perdue sans que personne le sache ;
 * - **sans unité, on ne lit rien.** « 160 x 80 » vaut-il des centimètres ou des
 *   millimètres ? Deviner, c'est ce que CLAUDE.md interdit. Le message part alors
 *   vers le modèle, qui pose la question.
 */
final class LecteurDeDimensions
{
    private const string NOMBRE = '\d+(?:[.,]\d+)?';
    private const string UNITE = '(?:mm|cm|m)';
    private const int DIMENSION_MAX_MM = 10_000;

    /** Millimètres par unité. */
    private const array FACTEURS = ['mm' => 1, 'cm' => 10, 'm' => 1_000];

    public function lit(string $message): ?DimensionsLues
    {
        // strtolower suffit : seules les lettres des unités comptent, et elles sont ASCII.
        $texte = strtolower(trim($message));
        $dimension = '(?:\d+m\d{1,2}|' . self::NOMBRE . '\s*' . self::UNITE . '?)';
        $separateur = '\s*[x×*]\s*';

        // L'unité écrite à la fin (« 160 x 80 cm ») est capturée avec le dernier côté.
        $motif = '/^(' . $dimension . ')' . $separateur . '(' . $dimension . ')'
            . '(?:' . $separateur . '(' . $dimension . '))?$/u';

        if (1 !== preg_match($motif, $texte, $parties)) {
            return null;
        }

        $bruts = [$parties[1], $parties[2]];
        if ('' !== ($parties[3] ?? '')) {
            $bruts[] = $parties[3];
        }

        // « 160 x 80 cm » : l'unité du dernier côté vaut pour ceux qui n'en ont
        // pas. « 2 x 1m60 » n'en donne pas : la notation 1m60 finit par un chiffre.
        $uniteFinale = 1 === preg_match('/(?:mm|cm|m)$/', $bruts[\count($bruts) - 1], $u) ? $u[0] : '';

        $valeurs = [];
        foreach ($bruts as $brut) {
            $millimetres = $this->enMillimetres($brut, $uniteFinale);
            // « 160 x 80 m » se lirait comme 160 mètres : une faute de frappe
            // plausible, une table impossible. Même borne que pour la réponse du modèle.
            if (null === $millimetres || $millimetres <= 0 || $millimetres > self::DIMENSION_MAX_MM) {
                return null;
            }
            $valeurs[] = $millimetres;
        }

        // Une table se décrit longueur d'abord, mais un client peut écrire
        // « 80 x 160 » : la longueur est par définition le plus grand côté.
        [$premier, $second] = $valeurs;

        return new DimensionsLues(
            longueurMm: max($premier, $second),
            largeurMm: min($premier, $second),
            hauteurMm: $valeurs[2] ?? null,
        );
    }

    /**
     * Convertit une dimension en millimètres entiers, sans flottant : la partie
     * décimale est lue comme une chaîne. Rend null si l'unité manque ou si le
     * résultat tomberait entre deux millimètres.
     */
    private function enMillimetres(string $brut, string $uniteFinale): ?int
    {
        $brut = str_replace(' ', '', $brut);

        // « 1m60 » : des mètres, puis des centimètres sur deux chiffres.
        if (1 === preg_match('/^(\d+)m(\d{1,2})$/', $brut, $m)) {
            return (int) $m[1] * 1_000 + (int) str_pad($m[2], 2, '0') * 10;
        }

        // Le motif principal garantit déjà cette forme ; la garde sert l'analyse statique.
        if (1 !== preg_match('/^(\d+)(?:[.,](\d+))?(mm|cm|m)?$/', $brut, $m)) {
            return null;
        }
        $unite = ($m[3] ?? '') !== '' ? $m[3] : $uniteFinale;
        if ('' === $unite) {
            return null;
        }

        $facteur = self::FACTEURS[$unite];
        $decimales = $m[2] ?? '';
        $echelle = 10 ** \strlen($decimales);
        $numerateur = ((int) $m[1] * $echelle + (int) $decimales) * $facteur;

        if (0 !== $numerateur % $echelle) {
            return null;
        }

        return intdiv($numerateur, $echelle);
    }
}
