<?php

declare(strict_types=1);

namespace App\Extraction\Cout;

use DomainException;

final class ModeleInconnu extends DomainException
{
    public function __construct(public readonly string $modele)
    {
        parent::__construct(\sprintf(
            'Aucun tarif connu pour le modèle « %s ». Modèles tarifés : %s.',
            $modele,
            implode(', ', GrilleTarifaire::modelesConnus()),
        ));
    }
}
