<?php

declare(strict_types=1);

namespace App\Devis\Entite;

use App\Devis\Chiffrage\Chiffrage;
use App\Shared\Montant;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Un devis.
 *
 * Les totaux sont stockés, pas recalculés : voir LigneDevis. Et le passage
 * d'un état à l'autre n'est pas un simple `setEtat()` — chaque transition a sa
 * méthode, qui refuse ce qui n'a pas de sens. Un objet qui laisse écrire
 * n'importe quel état n'est pas une entité, c'est une structure de données.
 */
#[ORM\Entity]
#[ORM\Table(name: 'devis')]
class Devis
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Demande::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Demande $demande;

    #[ORM\Column(length: 20, enumType: EtatDevis::class)]
    private EtatDevis $etat;

    /** @var Collection<int, LigneDevis> */
    #[ORM\OneToMany(targetEntity: LigneDevis::class, mappedBy: 'devis', cascade: ['persist'])]
    private Collection $lignes;

    #[ORM\Column(type: Types::INTEGER)]
    private int $totalHorsTaxesCentimes;

    #[ORM\Column(type: Types::INTEGER)]
    private int $tvaCentimes;

    #[ORM\Column(type: Types::INTEGER)]
    private int $tauxTvaCentiemes;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $mentionFranchiseTva;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $creeLe;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $envoyeLe = null;

    /**
     * Verrouillage optimiste : si deux personnes ouvrent le même devis et
     * l'enregistrent, la seconde reçoit une erreur au lieu d'écraser
     * silencieusement le travail de la première.
     */
    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    public function __construct(Demande $demande, Chiffrage $chiffrage)
    {
        $this->id = Uuid::v7();
        $this->demande = $demande;
        $this->etat = EtatDevis::Brouillon;
        $this->creeLe = new DateTimeImmutable();
        $this->lignes = new ArrayCollection();

        foreach ($chiffrage->lignes as $ligne) {
            $this->lignes->add(new LigneDevis($this, $ligne->libelle, $ligne->montant->centimes));
        }

        $this->totalHorsTaxesCentimes = $chiffrage->totalHorsTaxes->centimes;
        $this->tvaCentimes = $chiffrage->tva->centimes;
        $this->tauxTvaCentiemes = $chiffrage->tauxTvaCentiemes;
        $this->mentionFranchiseTva = $chiffrage->mentionFranchiseTva;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function demande(): Demande
    {
        return $this->demande;
    }

    public function etat(): EtatDevis
    {
        return $this->etat;
    }

    /** @return Collection<int, LigneDevis> */
    public function lignes(): Collection
    {
        return $this->lignes;
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

    /** En centièmes de point : 2000 pour 20 %, 0 en franchise. */
    public function tauxTvaCentiemes(): int
    {
        return $this->tauxTvaCentiemes;
    }

    public function creeLe(): DateTimeImmutable
    {
        return $this->creeLe;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function envoyeLe(): ?DateTimeImmutable
    {
        return $this->envoyeLe;
    }

    public function envoyer(): void
    {
        $this->exigeEtat(EtatDevis::Brouillon, 'envoyer');

        $this->etat = EtatDevis::Envoye;
        $this->envoyeLe = new DateTimeImmutable();
    }

    public function accepter(): void
    {
        $this->exigeEtat(EtatDevis::Envoye, 'accepter');

        $this->etat = EtatDevis::Accepte;
    }

    public function refuser(): void
    {
        $this->exigeEtat(EtatDevis::Envoye, 'refuser');

        $this->etat = EtatDevis::Refuse;
    }

    /**
     * Lève si le devis n'est pas facturable, sans rien modifier.
     *
     * Existe pour que l'appelant puisse vérifier *avant* d'ouvrir une
     * transaction : une exception levée dans une transaction Doctrine ferme
     * l'EntityManager, ce qui est une conséquence disproportionnée pour un
     * refus métier parfaitement prévisible.
     */
    public function verifieFacturable(): void
    {
        $this->exigeEtat(EtatDevis::Accepte, 'facturer');
    }

    /** Appelé par la facturation, une fois la facture émise. */
    public function marquerFacture(): void
    {
        $this->verifieFacturable();

        $this->etat = EtatDevis::Facture;
    }

    private function exigeEtat(EtatDevis $attendu, string $action): void
    {
        if ($this->etat !== $attendu) {
            throw new TransitionInterdite($this->etat, $attendu, $action);
        }
    }
}
