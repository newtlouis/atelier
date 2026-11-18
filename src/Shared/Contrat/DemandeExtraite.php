<?php

declare(strict_types=1);

namespace App\Shared\Contrat;

use InvalidArgumentException;

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
        public NatureDemande $nature = NatureDemande::Projet,
        public ?SupportMur $support = null,
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
        // Une pièce du catalogue a sa propre hauteur (la Travertin XL fait
        // 38 cm) : la règle ne vaut que pour une table sur mesure.
        if (FamilleProduit::Table !== $this->famille || null !== $this->hauteurMm || NatureDemande::Projet !== $this->nature) {
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
            nature: $this->nature,
            support: $this->support,
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
        return NatureDemande::Projet === $this->nature
            && [] === $this->champsManquants
            && null !== $this->distanceKm
            && $this->donneesCompletesHorsDistance();
    }

    /**
     * Le support indiqué dans le formulaire, qui l'emporte sur celui du
     * message, sauf si le message parle de bois : un mur irréalisable ne
     * redevient pas réalisable parce qu'une case a été cochée.
     */
    public function avecSupport(?SupportMur $support): self
    {
        if (null === $support || SupportMur::Bois === $this->support) {
            return $this;
        }

        return new self(
            famille: $this->famille,
            longueurMm: $this->longueurMm,
            largeurMm: $this->largeurMm,
            hauteurMm: $this->hauteurMm,
            surfaceMetresCarres: $this->surfaceMetresCarres,
            distanceKm: $this->distanceKm,
            champsManquants: array_values(array_filter(
                $this->champsManquants,
                static fn(string $champ): bool => !str_starts_with(strtolower(trim($champ)), 'support'),
            )),
            villeLivraison: $this->villeLivraison,
            hauteurStandard: $this->hauteurStandard,
            nature: $this->nature,
            support: $support,
        );
    }

    /**
     * La distance saisie par Louis pour une demande qui n'attendait qu'elle.
     * Ce n'est pas une valeur par défaut : c'est une mesure, faite par un humain.
     */
    public function avecDistance(int $distanceKm): self
    {
        if ($distanceKm < 0) {
            throw new InvalidArgumentException("Distance négative : {$distanceKm} km.");
        }

        return new self(
            famille: $this->famille,
            longueurMm: $this->longueurMm,
            largeurMm: $this->largeurMm,
            hauteurMm: $this->hauteurMm,
            surfaceMetresCarres: $this->surfaceMetresCarres,
            distanceKm: $distanceKm,
            champsManquants: array_values(array_filter(
                $this->champsManquants,
                static fn(string $champ): bool => 'distance' !== strtolower(trim($champ)),
            )),
            villeLivraison: $this->villeLivraison,
            hauteurStandard: $this->hauteurStandard,
            nature: $this->nature,
            support: $this->support,
        );
    }

    /**
     * La suite à donner. Une demande où seuls les kilomètres manquent n'est pas
     * une question au client quand la ville est connue : Louis les calcule.
     */
    public function issue(): IssueDeLecture
    {
        return match (true) {
            NatureDemande::HorsSujet === $this->nature => IssueDeLecture::HorsSujet,
            NatureDemande::CommandeCatalogue === $this->nature => IssueDeLecture::CommandeCatalogue,
            FamilleProduit::Mur === $this->famille && SupportMur::Bois === $this->support => IssueDeLecture::Irrealisable,
            $this->estExploitable() => IssueDeLecture::Chiffrable,
            $this->neManqueQueLaDistance() => IssueDeLecture::EnAttenteDeDistance,
            default => IssueDeLecture::QuestionsAuClient,
        };
    }

    /**
     * Ce qu'il faut demander au client pour pouvoir chiffrer.
     *
     * Le client ne connaît pas la distance à l'atelier : on lui demande sa
     * ville, et Louis calcule les kilomètres. Si la ville est déjà connue, la
     * distance ne le regarde pas.
     */
    public function questionsAPoser(): string
    {
        if (IssueDeLecture::QuestionsAuClient !== $this->issue()) {
            return '';
        }

        $manquants = [];
        foreach ($this->champsManquants as $champ) {
            if ('distance' !== strtolower(trim($champ))) {
                $manquants[] = $champ;
            } elseif (null === $this->villeLivraison) {
                $manquants[] = 'ville de livraison';
            }
        }
        if ([] === $manquants) {
            $manquants = ['les informations nécessaires au chiffrage'];
        }

        return 'Il manque : ' . implode(', ', $manquants) . '.';
    }

    /** Vrai si la même demande, kilomètres en plus, serait exploitable. */
    private function neManqueQueLaDistance(): bool
    {
        if (NatureDemande::Projet !== $this->nature || null !== $this->distanceKm || null === $this->villeLivraison) {
            return false;
        }

        foreach ($this->champsManquants as $champ) {
            if ('distance' !== strtolower(trim($champ))) {
                return false;
            }
        }

        return $this->donneesCompletesHorsDistance();
    }

    /** Ce que la famille exige, la distance mise à part. */
    private function donneesCompletesHorsDistance(): bool
    {
        return match ($this->famille) {
            FamilleProduit::Table => null !== $this->longueurMm
                && null !== $this->largeurMm
                && null !== $this->hauteurMm,
            FamilleProduit::Mur => null !== $this->surfaceMetresCarres
                && true === $this->support?->estRealisable(),
            null => false,
        };
    }
}
