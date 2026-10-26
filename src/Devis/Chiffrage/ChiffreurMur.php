<?php

declare(strict_types=1);

namespace App\Devis\Chiffrage;

/**
 * Chiffre un mur posé sur place.
 *
 * Contrairement à une table, un mur ne se livre pas : il se réalise chez le
 * client. Le déplacement n'est donc jamais offert — ce n'est pas un frais de
 * port, c'est une partie du travail.
 */
final readonly class ChiffreurMur
{
    public function __construct(
        private Bareme $bareme,
        private BaremeNuitees $nuitees = new BaremeNuitees(),
    ) {}

    public function chiffre(DemandeMur $demande): Chiffrage
    {
        $pose = $this->bareme->prixMetreCarreMur->multiplieParEntier($demande->surfaceMetresCarres);

        $nombreDeNuitees = $this->nuitees->pour($demande->surfaceMetresCarres);

        $lignes = [
            new LigneChiffree(
                \sprintf('Mur effet roche — %d m² posés', $demande->surfaceMetresCarres),
                $pose,
            ),
            new LigneChiffree(
                'Location utilitaire — 1 journée',
                $this->bareme->coutJourneeCamionnette,
            ),
            new LigneChiffree(
                \sprintf('Hébergement sur place — %d nuitées', $nombreDeNuitees),
                $this->bareme->coutNuitee->multiplieParEntier($nombreDeNuitees),
            ),
            new LigneChiffree(
                \sprintf('Déplacement — %d km aller-retour', $demande->distanceKm * 2),
                $this->bareme->tarifKilometrique->multiplieParEntier($demande->distanceKm * 2),
            ),
        ];

        $chiffrage = new Chiffrage(
            $lignes,
            $this->bareme->tauxTvaCentiemes,
            $this->bareme->estEnFranchiseDeTva() ? $this->bareme->mentionFranchiseTva : null,
        );

        // Le minimum porte sur le total : un mur de 2 m² à 450 €/m² ne tient pas
        // debout commercialement, même une fois le déplacement ajouté.
        if ($chiffrage->totalHorsTaxes->estInferieurA($this->bareme->minimumDeCommande)) {
            throw new CommandeSousLeMinimum(
                $chiffrage->totalHorsTaxes,
                $this->bareme->minimumDeCommande,
            );
        }

        return $chiffrage;
    }
}
