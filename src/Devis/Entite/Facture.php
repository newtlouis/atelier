<?php

declare(strict_types=1);

namespace App\Devis\Entite;

use App\Shared\Montant;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Une facture émise.
 *
 * Deux propriétés non négociables, imposées par la loi plus que par
 * l'informatique :
 *
 * 1. le numéro suit une séquence chronologique **sans trou** (art. 242 nonies A
 *    du CGI) — voir CompteurFactures pour la mécanique ;
 * 2. une fois émise, elle ne change plus. Pas de mutateur, des propriétés en
 *    lecture seule que PHP refuse de réécrire même par réflexion, et un
 *    écouteur Doctrine qui rejette tout UPDATE. Une correction se fait par
 *    avoir, jamais en réécrivant l'original.
 *
 * Les montants sont recopiés depuis le devis plutôt que lus par la relation :
 * si le devis était modifié, la facture doit rester lisible seule.
 */
#[ORM\Entity]
#[ORM\Table(name: 'facture')]
class Facture
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid', unique: true)]
        public readonly Uuid $id,
        #[ORM\Column(length: 20, unique: true)]
        public readonly string $numero,
        #[ORM\ManyToOne(targetEntity: Devis::class)]
        #[ORM\JoinColumn(nullable: false)]
        public readonly Devis $devis,
        #[ORM\Column(type: Types::INTEGER)]
        private readonly int $totalHorsTaxesCentimes,
        #[ORM\Column(type: Types::INTEGER)]
        private readonly int $tvaCentimes,
        #[ORM\Column(length: 120, nullable: true)]
        public readonly ?string $mentionFranchiseTva,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        public readonly DateTimeImmutable $emiseLe,
    ) {}

    public static function emettre(Devis $devis, string $numero, DateTimeImmutable $emiseLe): self
    {
        return new self(
            id: Uuid::v7(),
            numero: $numero,
            devis: $devis,
            totalHorsTaxesCentimes: $devis->totalHorsTaxes()->centimes,
            tvaCentimes: $devis->tva()->centimes,
            mentionFranchiseTva: $devis->mentionFranchiseTva(),
            emiseLe: $emiseLe,
        );
    }

    /**
     * Les montants restent derrière un accesseur, contrairement au reste : la
     * base stocke des centimes bruts, le domaine manipule des Montant. Exposer
     * l'entier inviterait à faire de l'arithmétique dessus.
     */
    public function totalHorsTaxes(): Montant
    {
        return Montant::enCentimes($this->totalHorsTaxesCentimes);
    }

    public function tva(): Montant
    {
        return Montant::enCentimes($this->tvaCentimes);
    }

    public function totalToutesTaxes(): Montant
    {
        return $this->totalHorsTaxes()->plus($this->tva());
    }
}
