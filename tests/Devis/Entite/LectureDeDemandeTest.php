<?php

declare(strict_types=1);

namespace App\Tests\Devis\Entite;

use App\Devis\Entite\Demande;
use App\Devis\Entite\LectureDeDemande;
use App\Shared\Contrat\DemandeExtraite;
use App\Shared\Contrat\FamilleProduit;
use App\Shared\Contrat\IssueDeLecture;
use App\Shared\Contrat\NatureDemande;
use App\Shared\Contrat\SupportMur;
use PHPUnit\Framework\TestCase;

final class LectureDeDemandeTest extends TestCase
{
    public function testLaLectureRendExactementCeQuiAEteLu(): void
    {
        $lue = new DemandeExtraite(
            famille: FamilleProduit::Mur,
            longueurMm: 1,
            largeurMm: 2,
            hauteurMm: 3,
            surfaceMetresCarres: 6,
            distanceKm: null,
            champsManquants: ['distance'],
            villeLivraison: 'Colmar',
            hauteurStandard: true,
            nature: NatureDemande::Projet,
            support: SupportMur::Tapisserie,
        );

        self::assertEquals($lue, LectureDeDemande::de(new Demande('Un mur.'), $lue)->demandeExtraite());
    }

    public function testLIssueEstFigeeAuMomentDeLaLecture(): void
    {
        $demande = new Demande('Un mur.');
        $lue = new DemandeExtraite(
            famille: FamilleProduit::Mur,
            nature: NatureDemande::Projet,
            longueurMm: null,
            largeurMm: null,
            hauteurMm: null,
            surfaceMetresCarres: 6,
            distanceKm: 50,
            support: SupportMur::Bois,
        );

        $lecture = LectureDeDemande::de($demande, $lue);

        self::assertSame(IssueDeLecture::Irrealisable, $lecture->issue);
        self::assertSame($demande, $lecture->demande);
    }
}
