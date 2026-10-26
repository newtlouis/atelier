<?php

declare(strict_types=1);

namespace App\Shared\Contrat;

/**
 * Les deux familles que l'atelier produit, et qui ne se chiffrent pas pareil :
 * une table se livre, un mur se pose.
 */
enum FamilleProduit: string
{
    case Table = 'table';
    case Mur = 'mur';
}
