<?php

declare(strict_types=1);

namespace App\Application;

use Symfony\Component\Uid\Uuid;

/**
 * Demande de traiter une demande client : l'extraire, puis la chiffrer.
 *
 * Ne porte que l'identifiant, jamais le texte. Un message transporté dans une
 * file doit rester petit et ne pas dupliquer une donnée qui vit déjà en base —
 * sans quoi un réessai travaillerait sur une version périmée.
 */
final readonly class ExtraireEtChiffrer
{
    public function __construct(public Uuid $demandeId) {}
}
