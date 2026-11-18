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
        // No default: a forgotten nature must never turn into a project,
        // that is, into a request that gets quoted.
        public NatureDemande $nature,
        public ?int $longueurMm,
        public ?int $largeurMm,
        public ?int $hauteurMm,
        public ?int $surfaceMetresCarres,
        public ?int $distanceKm,
        public array $champsManquants = [],
        public ?string $villeLivraison = null,
        public bool $hauteurStandard = false,
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

        return $this->variante(
            hauteurMm: self::HAUTEUR_STANDARD_TABLE_BASSE_MM,
            hauteurStandard: true,
            support: $this->support,
            distanceKm: $this->distanceKm,
            // The model names the field "hauteur" or "hauteur_mm".
            champsManquants: $this->sansManquant('hauteur'),
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

        return $this->variante(
            hauteurMm: $this->hauteurMm,
            hauteurStandard: $this->hauteurStandard,
            support: $support,
            distanceKm: $this->distanceKm,
            champsManquants: $this->sansManquant('support'),
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

        return $this->variante(
            hauteurMm: $this->hauteurMm,
            hauteurStandard: $this->hauteurStandard,
            support: $this->support,
            distanceKm: $distanceKm,
            champsManquants: $this->sansManquant('distance'),
        );
    }

    /**
     * Forgets a distance read from the client's own words. The fee for the
     * trip is money: it rests on kilometres Louis measured, never on "I'm
     * 5 km away" typed in a public form and copied by the model.
     */
    public function sansDistance(): self
    {
        if (null === $this->distanceKm) {
            return $this;
        }

        return $this->variante(
            hauteurMm: $this->hauteurMm,
            hauteurStandard: $this->hauteurStandard,
            support: $this->support,
            distanceKm: null,
            champsManquants: [...$this->sansManquant('distance'), 'distance'],
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
            if (!self::nomme($champ, 'distance')) {
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

        if ([] !== $this->sansManquant('distance')) {
            return false;
        }

        return $this->donneesCompletesHorsDistance();
    }

    /**
     * The only place that copies the contract. The three avec…() methods vary
     * these five fields; everything else is carried over, nature included, so
     * a copy can never turn spam into a project.
     *
     * @param list<string> $champsManquants
     */
    private function variante(
        ?int $hauteurMm,
        bool $hauteurStandard,
        ?SupportMur $support,
        ?int $distanceKm,
        array $champsManquants,
    ): self {
        return new self(
            famille: $this->famille,
            nature: $this->nature,
            longueurMm: $this->longueurMm,
            largeurMm: $this->largeurMm,
            hauteurMm: $hauteurMm,
            surfaceMetresCarres: $this->surfaceMetresCarres,
            distanceKm: $distanceKm,
            champsManquants: $champsManquants,
            villeLivraison: $this->villeLivraison,
            hauteurStandard: $hauteurStandard,
            support: $support,
        );
    }

    /**
     * The missing fields, minus the one named. The model's wording varies
     * ("distance", "distance_km", " Hauteur"): one rule for all three fields.
     *
     * @return list<string>
     */
    private function sansManquant(string $nom): array
    {
        return array_values(array_filter(
            $this->champsManquants,
            static fn(string $champ): bool => !self::nomme($champ, $nom),
        ));
    }

    private static function nomme(string $champ, string $nom): bool
    {
        return str_starts_with(strtolower(trim($champ)), $nom);
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
