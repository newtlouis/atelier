<?php

declare(strict_types=1);

namespace App\Extraction\Infrastructure;

use App\Extraction\Extracteur;
use App\Shared\Contrat\DemandeExtraite;
use LogicException;
use Throwable;

/**
 * An extractor that talks to nobody: it replays prepared responses.
 *
 * Used by the tests, but also in development and for demos with no API key. The
 * whole chain — intake, pricing, quote — can be worked on without spending a
 * cent; the real call only comes in at the very end.
 *
 * It lives in src/ rather than tests/ for that reason: it is a component usable
 * in development, not merely a test fixture.
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

    /** @return list<string> What was actually submitted, in order. */
    public function textesRecus(): array
    {
        return $this->textesRecus;
    }
}
