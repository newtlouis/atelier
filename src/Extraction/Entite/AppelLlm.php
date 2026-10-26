<?php

declare(strict_types=1);

namespace App\Extraction\Entite;

use App\Extraction\UsageAppel;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * La trace d'un appel au modèle : ce qu'il a consommé, coûté, et combien de
 * temps il a pris.
 *
 * Sans cette table, « combien coûte une extraction ? » et « ma nouvelle
 * version de prompt est-elle meilleure ? » sont des questions sans réponse.
 *
 * Les échecs sont enregistrés au même titre que les succès : un taux d'échec
 * invisible est un taux d'échec qu'on ne corrige pas.
 *
 * **Propriétés publiques en lecture seule, pas de mutateurs.** Cette entité
 * est écrite une fois et jamais modifiée ; `readonly` l'impose au niveau du
 * langage, ce qui rend onze accesseurs inutiles. Doctrine hydrate sans passer
 * par le constructeur, donc il écrit une propriété encore non initialisée —
 * ce que `readonly` autorise.
 *
 * Le constructeur est privé et promu : les champs viennent d'un `UsageAppel`,
 * et la fabrique évite onze arguments à l'appel.
 */
#[ORM\Entity]
#[ORM\Table(name: 'appel_llm')]
#[ORM\Index(name: 'idx_appel_llm_date', columns: ['appele_le'])]
#[ORM\Index(name: 'idx_appel_llm_version', columns: ['version_prompt'])]
class AppelLlm
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid', unique: true)]
        public readonly Uuid $id,
        #[ORM\Column(length: 60)]
        public readonly string $modele,
        #[ORM\Column(length: 20)]
        public readonly string $versionPrompt,
        #[ORM\Column(type: Types::INTEGER)]
        public readonly int $tokensEntree,
        #[ORM\Column(type: Types::INTEGER)]
        public readonly int $tokensSortie,
        #[ORM\Column(type: Types::INTEGER)]
        public readonly int $tokensCacheEcriture,
        #[ORM\Column(type: Types::INTEGER)]
        public readonly int $tokensCacheLecture,

        // BIGINT plutôt qu'INTEGER : un cumul annuel de nano-dollars dépasse
        // vite les deux milliards. PHP gère l'entier 64 bits sans difficulté.
        #[ORM\Column(type: Types::BIGINT)]
        public readonly int $coutNanoDollars,
        #[ORM\Column(type: Types::INTEGER)]
        public readonly int $latenceMs,
        #[ORM\Column(type: Types::BOOLEAN)]
        public readonly bool $succes,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        public readonly ?string $erreur,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        public readonly DateTimeImmutable $appeleLe,
    ) {}

    public static function depuis(UsageAppel $usage, int $coutNanoDollars): self
    {
        return new self(
            id: Uuid::v7(),
            modele: $usage->modele,
            versionPrompt: $usage->versionPrompt,
            tokensEntree: $usage->tokensEntree,
            tokensSortie: $usage->tokensSortie,
            tokensCacheEcriture: $usage->tokensCacheEcriture,
            tokensCacheLecture: $usage->tokensCacheLecture,
            coutNanoDollars: $coutNanoDollars,
            latenceMs: $usage->latenceMs,
            succes: $usage->succes,
            erreur: $usage->erreur,
            appeleLe: new DateTimeImmutable(),
        );
    }
}
