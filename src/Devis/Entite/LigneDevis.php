<?php

declare(strict_types=1);

namespace App\Devis\Entite;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

/**
 * Une ligne de devis, figée à l'écriture.
 *
 * Le libellé et le montant sont copiés au moment du chiffrage, pas recalculés
 * à l'affichage : un devis envoyé en octobre doit se relire à l'identique en
 * mars, même si le barème a changé deux fois entre-temps.
 */
#[ORM\Entity]
#[ORM\Table(name: 'ligne_devis')]
class LigneDevis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $libelle;

    /** Stocké en centimes : la base ne voit jamais un nombre à virgule. */
    #[ORM\Column(type: Types::INTEGER)]
    private int $montantCentimes;

    #[ORM\ManyToOne(targetEntity: Devis::class, inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false)]
    private Devis $devis;

    public function __construct(Devis $devis, string $libelle, int $montantCentimes)
    {
        if ($montantCentimes < 0) {
            throw new InvalidArgumentException("Montant de ligne négatif : {$montantCentimes}.");
        }

        $this->devis = $devis;
        $this->libelle = $libelle;
        $this->montantCentimes = $montantCentimes;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function devis(): Devis
    {
        return $this->devis;
    }

    public function libelle(): string
    {
        return $this->libelle;
    }

    public function montantCentimes(): int
    {
        return $this->montantCentimes;
    }
}
