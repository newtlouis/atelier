<?php

declare(strict_types=1);

namespace App\Devis\Chiffrage;

use App\Shared\Argent\Montant;

/**
 * Le résultat d'un chiffrage : le détail, puis les totaux.
 *
 * Les totaux sont calculés à la construction à partir des lignes, de sorte
 * qu'un total ne puisse jamais être en désaccord avec son détail.
 */
final readonly class Chiffrage
{
    public Montant $totalHorsTaxes;
    public Montant $tva;
    public Montant $totalToutesTaxes;

    /** @param non-empty-list<LigneChiffree> $lignes */
    public function __construct(
        public array $lignes,
        public int $tauxTvaCentiemes,
        public ?string $mentionFranchiseTva = null,
    ) {
        $total = Montant::zero();
        foreach ($lignes as $ligne) {
            $total = $total->plus($ligne->montant);
        }

        $this->totalHorsTaxes = $total;
        $this->tva = $total->pourcentage($tauxTvaCentiemes);
        $this->totalToutesTaxes = $total->plus($this->tva);
    }
}
