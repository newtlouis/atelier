<?php

declare(strict_types=1);

namespace App\Shared\Contrat;

/**
 * Ce qu'on a pu tirer d'une demande en texte libre.
 *
 * Vit dans Shared parce que deux domaines en dépendent sans se connaître :
 * Extraction la produit, Devis la consomme. C'est le contrat entre les deux,
 * et c'est la seule chose qu'ils partagent.
 *
 * Le point central de cet objet est `champsManquants`. Un modèle de langage
 * répond toujours quelque chose ; le laisser deviner des dimensions
 * produirait des devis faux et plausibles — le pire des deux mondes. Ici,
 * l'absence d'information est une donnée de première classe, pas une erreur.
 */
final readonly class DemandeExtraite
{
    /** @param list<string> $champsManquants */
    public function __construct(
        public ?FamilleProduit $famille,
        public ?int $longueurMm,
        public ?int $largeurMm,
        public ?int $hauteurMm,
        public ?int $surfaceMetresCarres,
        public ?int $distanceKm,
        public array $champsManquants = [],
        public ?string $villeLivraison = null,
    ) {}

    /**
     * Vrai si le chiffrage peut être tenté sans rien inventer.
     *
     * On ne se contente pas de `champsManquants === []` : le modèle pourrait
     * déclarer n'avoir rien oublié tout en laissant un champ vide. La
     * vérification porte sur les données elles-mêmes.
     */
    public function estExploitable(): bool
    {
        if ([] !== $this->champsManquants) {
            return false;
        }

        return match ($this->famille) {
            FamilleProduit::Table => null !== $this->longueurMm
                && null !== $this->largeurMm
                && null !== $this->hauteurMm
                && null !== $this->distanceKm,
            FamilleProduit::Mur => null !== $this->surfaceMetresCarres
                && null !== $this->distanceKm,
            null => false,
        };
    }

    /** Ce qu'il faut demander au client pour pouvoir chiffrer. */
    public function questionsAPoser(): string
    {
        if ($this->estExploitable()) {
            return '';
        }

        $manquants = [] !== $this->champsManquants
            ? $this->champsManquants
            : ['les informations nécessaires au chiffrage'];

        return 'Il manque : ' . implode(', ', $manquants) . '.';
    }
}
