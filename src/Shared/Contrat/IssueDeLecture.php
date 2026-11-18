<?php

declare(strict_types=1);

namespace App\Shared\Contrat;

/**
 * Ce qu'il advient d'une demande une fois lue : la suite à donner, et à qui.
 */
enum IssueDeLecture: string
{
    /** Tout est là : un devis en brouillon peut être calculé. */
    case Chiffrable = 'chiffrable';
    /** Seuls les kilomètres manquent, et la ville est connue : Louis les saisit. */
    case EnAttenteDeDistance = 'en_attente_de_distance';
    /** Il manque ce que seul le client sait. */
    case QuestionsAuClient = 'questions_au_client';
    /** Prix connu : Louis répond directement. */
    case CommandeCatalogue = 'commande_catalogue';
    /** Rien à faire. */
    case HorsSujet = 'hors_sujet';
    /** Impossible à réaliser tel que demandé, par exemple un mur sur du bois. */
    case Irrealisable = 'irrealisable';
}
