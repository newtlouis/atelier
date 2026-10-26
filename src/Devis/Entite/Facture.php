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
 *    du CGI) — voir CompteurDeFactures pour la mécanique ;
 * 2. une fois émise, elle ne change plus. Pas de mutateur ici, et un écouteur
 *    Doctrine refuse tout UPDATE : une correction se fait par avoir, jamais en
 *    réécrivant l'original.
 *
 * Les montants sont recopiés depuis le devis plutôt que lus par la relation :
 * si le devis était modifié ou supprimé, la facture doit rester lisible seule.
 */
#[ORM\Entity]
#[ORM\Table(name: 'facture')]
class Facture
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private readonly Uuid $id;

    #[ORM\Column(length: 20, unique: true)]
    private readonly string $numero;

    #[ORM\ManyToOne(targetEntity: Devis::class)]
    #[ORM\JoinColumn(nullable: false)]
    private readonly Devis $devis;

    #[ORM\Column(type: Types::INTEGER)]
    private readonly int $totalHorsTaxesCentimes;

    #[ORM\Column(type: Types::INTEGER)]
    private readonly int $tvaCentimes;

    #[ORM\Column(length: 120, nullable: true)]
    private readonly ?string $mentionFranchiseTva;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private readonly DateTimeImmutable $emiseLe;

    public function __construct(Devis $devis, string $numero, DateTimeImmutable $emiseLe)
    {
        $this->id = Uuid::v7();
        $this->numero = $numero;
        $this->devis = $devis;
        $this->totalHorsTaxesCentimes = $devis->totalHorsTaxes()->centimes;
        $this->tvaCentimes = $devis->tva()->centimes;
        $this->mentionFranchiseTva = $devis->mentionFranchiseTva();
        $this->emiseLe = $emiseLe;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function numero(): string
    {
        return $this->numero;
    }

    public function devis(): Devis
    {
        return $this->devis;
    }

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

    public function mentionFranchiseTva(): ?string
    {
        return $this->mentionFranchiseTva;
    }

    public function emiseLe(): DateTimeImmutable
    {
        return $this->emiseLe;
    }
}
