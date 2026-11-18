<?php

declare(strict_types=1);

namespace App\Devis\Entite;

use App\Shared\Contrat\DemandeExtraite;
use App\Shared\Contrat\FamilleProduit;
use App\Shared\Contrat\IssueDeLecture;
use App\Shared\Contrat\NatureDemande;
use App\Shared\Contrat\SupportMur;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Ce que la chaîne a compris d'une demande, et la suite à donner.
 *
 * Sans elle, une demande incomplète ne laissait qu'une ligne de journal : rien
 * à lister pour Louis, rien à compter pour savoir combien d'envois finissent en
 * devis. Enregistrée une fois, elle sert aussi l'économie : un message redélivré
 * ou une distance saisie plus tard repartent de cette lecture, sans rappeler le
 * modèle.
 *
 * Une seule lecture par demande, garantie par la base. Immuable.
 */
#[ORM\Entity]
#[ORM\Table(name: 'lecture_de_demande')]
#[ORM\Index(name: 'idx_lecture_issue', columns: ['issue'])]
class LectureDeDemande
{
    /** @param list<string> $champsManquants */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid', unique: true)]
        public readonly Uuid $id,
        #[ORM\OneToOne(targetEntity: Demande::class)]
        #[ORM\JoinColumn(nullable: false, unique: true)]
        public readonly Demande $demande,
        #[ORM\Column(length: 30, enumType: IssueDeLecture::class)]
        public readonly IssueDeLecture $issue,
        #[ORM\Column(length: 30, enumType: NatureDemande::class)]
        public readonly NatureDemande $nature,
        #[ORM\Column(length: 10, nullable: true, enumType: FamilleProduit::class)]
        public readonly ?FamilleProduit $famille,
        #[ORM\Column(nullable: true)]
        public readonly ?int $longueurMm,
        #[ORM\Column(nullable: true)]
        public readonly ?int $largeurMm,
        #[ORM\Column(nullable: true)]
        public readonly ?int $hauteurMm,
        #[ORM\Column]
        public readonly bool $hauteurStandard,
        #[ORM\Column(nullable: true)]
        public readonly ?int $surfaceMetresCarres,
        #[ORM\Column(nullable: true)]
        public readonly ?int $distanceKm,
        #[ORM\Column(length: 20, nullable: true, enumType: SupportMur::class)]
        public readonly ?SupportMur $support,
        #[ORM\Column(length: 120, nullable: true)]
        public readonly ?string $villeLivraison,
        #[ORM\Column(type: Types::JSON)]
        public readonly array $champsManquants,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        public readonly DateTimeImmutable $lueLe,
    ) {}

    public static function depuis(Demande $demande, DemandeExtraite $lue): self
    {
        return new self(
            id: Uuid::v7(),
            demande: $demande,
            issue: $lue->issue(),
            nature: $lue->nature,
            famille: $lue->famille,
            longueurMm: $lue->longueurMm,
            largeurMm: $lue->largeurMm,
            hauteurMm: $lue->hauteurMm,
            hauteurStandard: $lue->hauteurStandard,
            surfaceMetresCarres: $lue->surfaceMetresCarres,
            distanceKm: $lue->distanceKm,
            support: $lue->support,
            villeLivraison: $lue->villeLivraison,
            champsManquants: $lue->champsManquants,
            lueLe: new DateTimeImmutable(),
        );
    }

    /** La lecture telle que la chaîne l'a produite, pour la chiffrer. */
    public function demandeExtraite(): DemandeExtraite
    {
        return new DemandeExtraite(
            famille: $this->famille,
            longueurMm: $this->longueurMm,
            largeurMm: $this->largeurMm,
            hauteurMm: $this->hauteurMm,
            surfaceMetresCarres: $this->surfaceMetresCarres,
            distanceKm: $this->distanceKm,
            champsManquants: $this->champsManquants,
            villeLivraison: $this->villeLivraison,
            hauteurStandard: $this->hauteurStandard,
            nature: $this->nature,
            support: $this->support,
        );
    }
}
