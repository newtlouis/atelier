<?php

declare(strict_types=1);

namespace App\Devis\Entite;

use App\Shared\Contrat\EnvoiFormulaire;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * Ce qu'un client a écrit, tel qu'il l'a écrit.
 *
 * Le texte d'origine est conservé intact et pour toujours : c'est la seule
 * pièce dont on soit certain qu'elle n'a pas été déformée. Pouvoir rejouer une
 * extraction sur la demande d'origine est ce qui rend les évaluations possibles.
 *
 * Une demande venue du formulaire du site garde en plus l'envoi complet :
 * l'identifiant Netlify, que la base rend unique pour que l'import ne crée
 * jamais deux demandes du même envoi, et les champs tels que le client les a
 * remplis, pour rejouer l'aiguillage sur la donnée d'origine.
 *
 * Immuable : propriétés publiques en lecture seule, aucun accesseur.
 */
#[ORM\Entity]
#[ORM\Table(name: 'demande')]
class Demande
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    public readonly Uuid $id;

    #[ORM\Column(type: Types::TEXT)]
    public readonly string $texteClient;

    #[ORM\Column(length: 180, nullable: true)]
    public readonly ?string $emailClient;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public readonly DateTimeImmutable $recueLe;

    #[ORM\Column(length: 64, unique: true, nullable: true)]
    public readonly ?string $idNetlify;

    #[ORM\Column(length: 40, nullable: true)]
    public readonly ?string $formulaire;

    /** @var array<string, string> */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    public readonly array $champsFormulaire;

    /** Les champs d'un envoi que la demande garde, dans l'ordre du contrat. */
    private const array CHAMPS = ['projet', 'surface', 'support', 'ville', 'piece', 'taille', 'besoin'];

    /** @param array<string, string> $champsFormulaire */
    public function __construct(
        string $texteClient,
        ?string $emailClient = null,
        ?string $idNetlify = null,
        ?string $formulaire = null,
        array $champsFormulaire = [],
        ?DateTimeImmutable $recueLe = null,
    ) {
        if ('' === trim($texteClient)) {
            throw new InvalidArgumentException("Une demande sans texte n'est pas une demande.");
        }

        $this->id = Uuid::v7();
        $this->texteClient = $texteClient;
        $this->emailClient = $emailClient;
        $this->recueLe = $recueLe ?? new DateTimeImmutable();
        $this->idNetlify = $idNetlify;
        $this->formulaire = $formulaire;
        $this->champsFormulaire = $champsFormulaire;
    }

    /** La date de la demande est celle de l'envoi, pas celle de l'import. */
    public static function depuisFormulaire(EnvoiFormulaire $envoi): self
    {
        $champs = [];
        foreach (self::CHAMPS as $nom) {
            if (null !== $envoi->{$nom}) {
                $champs[$nom] = $envoi->{$nom};
            }
        }
        $champs['message'] = $envoi->message;

        return new self(
            texteClient: $envoi->texteClient(),
            emailClient: $envoi->emailClient,
            idNetlify: $envoi->idNetlify,
            formulaire: $envoi->formulaire,
            champsFormulaire: $champs,
            recueLe: $envoi->recuLe,
        );
    }

    /** L'envoi d'origine, ou null pour une demande saisie à la main. */
    public function envoiFormulaire(): ?EnvoiFormulaire
    {
        if (null === $this->idNetlify || null === $this->formulaire) {
            return null;
        }

        $champs = $this->champsFormulaire;

        return new EnvoiFormulaire(
            idNetlify: $this->idNetlify,
            formulaire: $this->formulaire,
            recuLe: $this->recueLe,
            emailClient: $this->emailClient,
            message: $champs['message'] ?? '',
            projet: $champs['projet'] ?? null,
            surface: $champs['surface'] ?? null,
            support: $champs['support'] ?? null,
            ville: $champs['ville'] ?? null,
            piece: $champs['piece'] ?? null,
            taille: $champs['taille'] ?? null,
            besoin: $champs['besoin'] ?? null,
        );
    }
}
