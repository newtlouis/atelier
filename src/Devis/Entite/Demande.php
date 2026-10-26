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
 * pièce dont on soit certain qu'elle n'a pas été déformée. Au lot 2, c'est à
 * partir de ce champ que l'extraction travaillera — et pouvoir rejouer une
 * extraction sur la demande d'origine est ce qui rendra les évaluations
 * possibles.
 */
#[ORM\Entity]
#[ORM\Table(name: 'demande')]
class Demande
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::TEXT)]
    private string $texteClient;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $emailClient;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $recueLe;

    public function __construct(string $texteClient, ?string $emailClient = null)
    {
        if ('' === trim($texteClient)) {
            throw new InvalidArgumentException('Une demande sans texte n\'est pas une demande.');
        }

        $this->id = Uuid::v7();
        $this->texteClient = $texteClient;
        $this->emailClient = $emailClient;
        $this->recueLe = new DateTimeImmutable();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function texteClient(): string
    {
        return $this->texteClient;
    }

    public function emailClient(): ?string
    {
        return $this->emailClient;
    }

    public function recueLe(): DateTimeImmutable
    {
        return $this->recueLe;
    }
}
