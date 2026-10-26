<?php

declare(strict_types=1);

namespace App\Devis\Entite;

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

    public function __construct(string $texteClient, ?string $emailClient = null)
    {
        if ('' === trim($texteClient)) {
            throw new InvalidArgumentException("Une demande sans texte n'est pas une demande.");
        }

        $this->id = Uuid::v7();
        $this->texteClient = $texteClient;
        $this->emailClient = $emailClient;
        $this->recueLe = new DateTimeImmutable();
    }
}
