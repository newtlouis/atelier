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
 * Sans cette table, « combien coûte une extraction ? » et « est-ce que ma
 * nouvelle version de prompt est meilleure ? » sont des questions sans réponse.
 * C'est elle qui alimentera le tableau de bord et le rapport d'évaluation.
 *
 * Les échecs sont enregistrés au même titre que les succès : un taux d'échec
 * invisible est un taux d'échec qu'on ne corrige pas.
 */
#[ORM\Entity]
#[ORM\Table(name: 'appel_llm')]
#[ORM\Index(name: 'idx_appel_llm_date', columns: ['appele_le'])]
#[ORM\Index(name: 'idx_appel_llm_version', columns: ['version_prompt'])]
class AppelLlm
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private readonly Uuid $id;

    #[ORM\Column(length: 60)]
    private readonly string $modele;

    #[ORM\Column(length: 20)]
    private readonly string $versionPrompt;

    #[ORM\Column(type: Types::INTEGER)]
    private readonly int $tokensEntree;

    #[ORM\Column(type: Types::INTEGER)]
    private readonly int $tokensSortie;

    #[ORM\Column(type: Types::INTEGER)]
    private readonly int $tokensCacheEcriture;

    #[ORM\Column(type: Types::INTEGER)]
    private readonly int $tokensCacheLecture;

    /** En nano-dollars : voir GrilleTarifaire pour le choix de l'unité. */
    #[ORM\Column(type: Types::BIGINT)]
    private readonly int $coutNanoDollars;

    #[ORM\Column(type: Types::INTEGER)]
    private readonly int $latenceMs;

    #[ORM\Column(type: Types::BOOLEAN)]
    private readonly bool $succes;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private readonly ?string $erreur;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private readonly DateTimeImmutable $appeleLe;

    public function __construct(UsageAppel $usage, int $coutNanoDollars)
    {
        $this->id = Uuid::v7();
        $this->modele = $usage->modele;
        $this->versionPrompt = $usage->versionPrompt;
        $this->tokensEntree = $usage->tokensEntree;
        $this->tokensSortie = $usage->tokensSortie;
        $this->tokensCacheEcriture = $usage->tokensCacheEcriture;
        $this->tokensCacheLecture = $usage->tokensCacheLecture;
        // BIGINT plutôt que INTEGER : un cumul annuel de nano-dollars dépasse
        // vite les deux milliards. PHP gère l'entier 64 bits sans difficulté.
        $this->coutNanoDollars = $coutNanoDollars;
        $this->latenceMs = $usage->latenceMs;
        $this->succes = $usage->succes;
        $this->erreur = $usage->erreur;
        $this->appeleLe = new DateTimeImmutable();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function modele(): string
    {
        return $this->modele;
    }

    public function versionPrompt(): string
    {
        return $this->versionPrompt;
    }

    public function coutNanoDollars(): int
    {
        return $this->coutNanoDollars;
    }

    public function tokensEntree(): int
    {
        return $this->tokensEntree;
    }

    public function tokensSortie(): int
    {
        return $this->tokensSortie;
    }

    public function tokensCacheLecture(): int
    {
        return $this->tokensCacheLecture;
    }

    public function tokensCacheEcriture(): int
    {
        return $this->tokensCacheEcriture;
    }

    public function latenceMs(): int
    {
        return $this->latenceMs;
    }

    public function estUnSucces(): bool
    {
        return $this->succes;
    }

    public function erreur(): ?string
    {
        return $this->erreur;
    }

    public function appeleLe(): DateTimeImmutable
    {
        return $this->appeleLe;
    }
}
