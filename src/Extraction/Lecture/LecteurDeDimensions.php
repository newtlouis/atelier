<?php

declare(strict_types=1);

namespace App\Extraction\Lecture;

/**
 * Reads a message that reduces to dimensions — "1600*800 mm", "1m60 x 80 cm" —
 * without calling a model.
 *
 * The only real request the form has received (2026-09-07) said exactly
 * "1600*800 mm". For that shape, a language model costs a cent and three
 * seconds to produce a result a regular expression gets right every time. The
 * model stays reserved for messages written in sentences.
 *
 * Two rules of caution:
 *
 * - **the whole message must be dimensions.** "160 x 80 cm, rather in pale
 *   rock" carries something else: it is not read here, otherwise the finish
 *   would be lost without anyone noticing;
 * - **no unit, no reading.** Is "160 x 80" centimetres or millimetres? Guessing
 *   is what CLAUDE.md forbids. The message then goes to the model, which asks
 *   the question.
 */
final class LecteurDeDimensions
{
    private const string NOMBRE = '\d+(?:[.,]\d+)?';
    private const string UNITE = '(?:mm|cm|m)';
    private const int DIMENSION_MAX_MM = 10_000;

    /** Millimetres per unit. */
    private const array FACTEURS = ['mm' => 1, 'cm' => 10, 'm' => 1_000];

    public function lit(string $message): ?DimensionsLues
    {
        // strtolower is enough: only the unit letters matter, and they are ASCII.
        $texte = strtolower(trim($message));
        $dimension = '(?:\d+m\d{1,2}|' . self::NOMBRE . '\s*' . self::UNITE . '?)';
        $separateur = '\s*[x×*]\s*';

        // A unit written at the end ("160 x 80 cm") is captured with the last side.
        $motif = '/^(' . $dimension . ')' . $separateur . '(' . $dimension . ')'
            . '(?:' . $separateur . '(' . $dimension . '))?$/u';

        if (1 !== preg_match($motif, $texte, $parties)) {
            return null;
        }

        $bruts = [$parties[1], $parties[2]];
        if ('' !== ($parties[3] ?? '')) {
            $bruts[] = $parties[3];
        }

        // "160 x 80 cm": the unit on the last side applies to the sides without
        // one. "2 x 1m60" gives none: the 1m60 notation ends with a digit.
        $uniteFinale = 1 === preg_match('/(?:mm|cm|m)$/', $bruts[\count($bruts) - 1], $u) ? $u[0] : '';

        $valeurs = [];
        foreach ($bruts as $brut) {
            $millimetres = $this->enMillimetres($brut, $uniteFinale);
            // "160 x 80 m" would read as 160 metres: a plausible typo, an
            // impossible table. Same bound as for the model's response.
            if (null === $millimetres || $millimetres <= 0 || $millimetres > self::DIMENSION_MAX_MM) {
                return null;
            }
            $valeurs[] = $millimetres;
        }

        // A table is described length first, but a customer may write
        // "80 x 160": length is by definition the longer side.
        [$premier, $second] = $valeurs;

        return new DimensionsLues(
            longueurMm: max($premier, $second),
            largeurMm: min($premier, $second),
            hauteurMm: $valeurs[2] ?? null,
        );
    }

    /**
     * Converts a dimension to whole millimetres, with no float: the decimal part
     * is read as a string. Returns null if the unit is missing, or if the result
     * would fall between two millimetres.
     */
    private function enMillimetres(string $brut, string $uniteFinale): ?int
    {
        $brut = str_replace(' ', '', $brut);

        // "1m60": metres, then centimetres on two digits.
        if (1 === preg_match('/^(\d+)m(\d{1,2})$/', $brut, $m)) {
            return (int) $m[1] * 1_000 + (int) str_pad($m[2], 2, '0') * 10;
        }

        // The main pattern already guarantees this shape; the guard is for static analysis.
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
