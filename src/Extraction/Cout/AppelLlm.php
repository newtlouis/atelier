<?php

declare(strict_types=1);

namespace App\Extraction\Cout;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * The trace of one call to the model: what it consumed, what it cost, and how
 * long it took.
 *
 * Without this table, "how much does an extraction cost?" and "is my new
 * prompt version any better?" are questions with no answer.
 *
 * Failures are recorded alongside successes: a failure rate nobody can see is
 * a failure rate nobody fixes.
 *
 * **Public readonly properties, no setters.** This entity is written once and
 * never modified; `readonly` enforces that at language level, which makes
 * eleven getters pointless. Doctrine hydrates without going through the
 * constructor, so it writes a property that is still uninitialised — which
 * `readonly` permits.
 *
 * The constructor is private and promoted: the fields come from an
 * `UsageAppel`, and the factory saves the caller eleven arguments.
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

        // BIGINT rather than INTEGER: a yearly total in nano-dollars passes two
        // billion quickly. PHP handles 64-bit integers without trouble.
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
