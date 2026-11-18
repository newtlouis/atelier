<?php

declare(strict_types=1);

namespace App\Shared\Contrat;

/**
 * Ce qu'est un envoi avant de savoir ce qu'il contient.
 *
 * Sur les quatre envois réels du formulaire, un seul était un projet à
 * chiffrer : les autres étaient une commande du catalogue et un spam. Les
 * traiter comme des projets incomplets ferait poser des questions à un
 * spammeur, ou chiffrer au m² une pièce dont le prix est déjà fixé.
 */
enum NatureDemande: string
{
    /** Une pièce sur mesure ou un mur : se chiffre au barème. */
    case Projet = 'projet';
    /** Une pièce du catalogue : son prix est connu, elle ne se chiffre jamais. */
    case CommandeCatalogue = 'commande_catalogue';
    /** Démarchage, spam, message sans rapport : ni devis ni question. */
    case HorsSujet = 'hors_sujet';
}
