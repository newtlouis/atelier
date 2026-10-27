<?php

declare(strict_types=1);

namespace App\Extraction\Cout;

/**
 * Enregistre ce que chaque appel au modèle a consommé.
 *
 * Une interface, parce que l'extracteur n'a pas à savoir si l'on écrit en base,
 * dans un fichier ou nulle part — et parce que les tests ne doivent pas avoir
 * besoin d'une base pour vérifier un appel.
 */
interface JournalDesAppels
{
    public function enregistre(UsageAppel $usage): void;
}
