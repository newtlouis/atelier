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
    /**
     * L'atelier ne fait que des tables basses, toutes à 35 cm sauf demande
     * contraire (règle de Louis, 17/11/2026). La hauteur n'entre pas dans le prix.
     */
    public const int HAUTEUR_STANDARD_TABLE_BASSE_MM = 350;

    /**
     * @param list<string> $champsManquants
     * @param bool         $hauteurStandard vrai si la hauteur vient de la règle, pas du client
     */
    public function __construct(
        public ?FamilleProduit $famille,
        public ?int $longueurMm,
        public ?int $largeurMm,
        public ?int $hauteurMm,
        public ?int $surfaceMetresCarres,
        public ?int $distanceKm,
        public array $champsManquants = [],
        public ?string $villeLivraison = null,
        public bool $hauteurStandard = false,
    ) {}

    /**
     * La seule exception écrite à l'invariant 3 (voir CLAUDE.md) : une table
     * sans hauteur prend la hauteur standard des tables basses.
     *
     * Elle est étroite à dessein. Elle ne vaut que pour la hauteur d'une table,
     * qui ne change pas le prix ; elle est appliquée par le code, jamais par le
     * modèle, qui continue de signaler la hauteur comme manquante ; et elle
     * reste visible (`hauteurStandard`) pour que le devis l'annonce au client.
     * Une hauteur donnée par le client n'est jamais remplacée.
     */
    public function avecHauteurStandard(): self
    {
        if (FamilleProduit::Table !== $this->famille || null !== $this->hauteurMm) {
            return $this;
        }

        return new self(
            famille: $this->famille,
            longueurMm: $this->longueurMm,
            largeurMm: $this->largeurMm,
            hauteurMm: self::HAUTEUR_STANDARD_TABLE_BASSE_MM,
            surfaceMetresCarres: $this->surfaceMetresCarres,
            distanceKm: $this->distanceKm,
            champsManquants: array_values(array_filter(
                $this->champsManquants,
                // Le modèle nomme le champ « hauteur » ou « hauteur_mm ».
                static fn(string $champ): bool => !str_starts_with(strtolower(trim($champ)), 'hauteur'),
            )),
            villeLivraison: $this->villeLivraison,
            hauteurStandard: true,
        );
    }

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
