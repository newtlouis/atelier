<?php

declare(strict_types=1);

namespace App\Tests\Shared\Contrat;

use App\Shared\Contrat\DemandeExtraite;
use App\Shared\Contrat\FamilleProduit;
use App\Shared\Contrat\IssueDeLecture;
use App\Shared\Contrat\NatureDemande;
use App\Shared\Contrat\SupportMur;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `estExploitable()` est la garde qui empêche un devis d'être calculé sur une
 * donnée inventée. Elle est donc éprouvée champ par champ : retirer n'importe
 * lequel des champs requis doit suffire à bloquer le chiffrage.
 */
final class DemandeExtraiteTest extends TestCase
{
    public function testUneTableComplèteEstExploitable(): void
    {
        self::assertTrue(self::table()->estExploitable());
    }

    public function testUnMurCompletEstExploitable(): void
    {
        self::assertTrue(self::mur()->estExploitable());
    }

    #[DataProvider('champsRequisDUneTable')]
    public function testUneTableAmputeeDUnChampNEstPasExploitable(string $champAbsent): void
    {
        self::assertFalse(self::table(sans: $champAbsent)->estExploitable());
    }

    /** @return iterable<string, array{string}> */
    public static function champsRequisDUneTable(): iterable
    {
        yield 'sans longueur' => ['longueur'];
        yield 'sans largeur' => ['largeur'];
        yield 'sans hauteur' => ['hauteur'];
        yield 'sans distance' => ['distance'];
    }

    #[DataProvider('champsRequisDUnMur')]
    public function testUnMurAmputeDUnChampNEstPasExploitable(string $champAbsent): void
    {
        self::assertFalse(self::mur(sans: $champAbsent)->estExploitable());
    }

    /** @return iterable<string, array{string}> */
    public static function champsRequisDUnMur(): iterable
    {
        yield 'sans surface' => ['surface'];
        yield 'sans distance' => ['distance'];
        yield 'sans support' => ['support'];
    }

    public function testSansFamilleRienNEstExploitable(): void
    {
        $demande = new DemandeExtraite(
            famille: null,
            longueurMm: 1_200,
            largeurMm: 600,
            hauteurMm: 350,
            surfaceMetresCarres: 6,
            distanceKm: 100,
        );

        self::assertFalse($demande->estExploitable());
    }

    public function testUnChampManquantDeclareSuffitABloquer(): void
    {
        // Toutes les données sont là, mais le modèle signale un doute :
        // on lui fait confiance dans ce sens-là.
        self::assertFalse(
            self::table(champsManquants: ['finition'])->estExploitable(),
        );
    }

    public function testUneDemandeExploitableNaPasDeQuestionAPoser(): void
    {
        self::assertSame('', self::table()->questionsAPoser());
    }

    public function testSansPrecisionLaQuestionResteGenerique(): void
    {
        // Le modèle n'a pas nommé ce qui manque : on ne peut pas être précis,
        // mais on ne doit pas non plus prétendre que tout va bien.
        self::assertSame(
            'Il manque : les informations nécessaires au chiffrage.',
            self::table(sans: 'hauteur')->questionsAPoser(),
        );
    }

    public function testUneTableSansHauteurPrendLaHauteurStandard(): void
    {
        $demande = self::table(sans: 'hauteur', champsManquants: ['hauteur'])->avecHauteurStandard();

        self::assertSame(350, $demande->hauteurMm);
        self::assertTrue($demande->hauteurStandard);
        self::assertSame([], $demande->champsManquants);
        self::assertTrue($demande->estExploitable());
    }

    public function testLaHauteurStandardNeRetireQueLaHauteurDesChampsManquants(): void
    {
        $demande = self::table(sans: 'hauteur', champsManquants: ['longueur', ' Hauteur_mm', 'distance'])
            ->avecHauteurStandard();

        self::assertSame(['longueur', 'distance'], $demande->champsManquants);
        self::assertFalse($demande->estExploitable());
    }

    public function testLaHauteurStandardGardeLesAutresDonnees(): void
    {
        $demande = new DemandeExtraite(
            famille: FamilleProduit::Table,
            longueurMm: 1_600,
            largeurMm: 800,
            hauteurMm: null,
            surfaceMetresCarres: null,
            distanceKm: null,
            champsManquants: ['hauteur', 'distance'],
            villeLivraison: 'Strasbourg',
        );

        self::assertEquals(
            new DemandeExtraite(
                famille: FamilleProduit::Table,
                longueurMm: 1_600,
                largeurMm: 800,
                hauteurMm: 350,
                surfaceMetresCarres: null,
                distanceKm: null,
                champsManquants: ['distance'],
                villeLivraison: 'Strasbourg',
                hauteurStandard: true,
            ),
            $demande->avecHauteurStandard(),
        );
    }

    public function testUneHauteurDonneeParLeClientNEstJamaisRemplacee(): void
    {
        $demande = new DemandeExtraite(
            famille: FamilleProduit::Table,
            longueurMm: 1_200,
            largeurMm: 600,
            hauteurMm: 420,
            surfaceMetresCarres: null,
            distanceKm: 100,
        );

        self::assertSame($demande, $demande->avecHauteurStandard());
    }

    /**
     * L'exception à l'invariant 3 ne s'étend à rien d'autre : un mur, ou une
     * demande dont on ignore la famille, ne reçoit aucune valeur par défaut.
     */
    public function testLaHauteurStandardNeConcerneQueLesTables(): void
    {
        $mur = self::mur(sans: 'surface', champsManquants: ['surface']);
        $inconnue = new DemandeExtraite(
            famille: null,
            longueurMm: null,
            largeurMm: null,
            hauteurMm: null,
            surfaceMetresCarres: null,
            distanceKm: null,
            champsManquants: ['famille', 'hauteur'],
        );

        self::assertSame($mur, $mur->avecHauteurStandard());
        self::assertSame($inconnue, $inconnue->avecHauteurStandard());
    }

    public function testParDefautLaHauteurNEstPasStandard(): void
    {
        self::assertFalse(self::table()->hauteurStandard);
    }

    public function testUneDemandeCompleteEstChiffrable(): void
    {
        self::assertSame(IssueDeLecture::Chiffrable, self::table()->issue());
    }

    /**
     * La vraie demande : dimensions lues, hauteur standard, ville connue.
     * Les kilomètres, c'est Louis qui les calcule ; le client n'a rien à ajouter.
     */
    public function testSansKilometresMaisAvecLaVilleLaDemandeAttendSaDistance(): void
    {
        $demande = self::table(sans: 'distance', champsManquants: [' Distance'], ville: 'Colmar');

        self::assertSame(IssueDeLecture::EnAttenteDeDistance, $demande->issue());
        self::assertSame('', $demande->questionsAPoser());
    }

    public function testSansKilometresNiVilleLaQuestionVaAuClient(): void
    {
        $demande = self::table(sans: 'distance', champsManquants: ['distance']);

        self::assertSame(IssueDeLecture::QuestionsAuClient, $demande->issue());
        self::assertSame('Il manque : distance.', $demande->questionsAPoser());
    }

    public function testSIlManqueAutreChoseQueLaDistanceLaQuestionVaAuClient(): void
    {
        $demande = self::table(sans: 'distance', champsManquants: ['distance', 'largeur'], ville: 'Colmar');

        self::assertSame(IssueDeLecture::QuestionsAuClient, $demande->issue());
    }

    public function testUneDonneeAbsenteAvecLaVilleResteUneQuestion(): void
    {
        // La largeur manque sans que le modèle l'ait dit : ce n'est pas qu'une distance.
        $demande = new DemandeExtraite(
            famille: FamilleProduit::Table,
            longueurMm: 1_200,
            largeurMm: null,
            hauteurMm: 350,
            surfaceMetresCarres: null,
            distanceKm: null,
            champsManquants: ['distance'],
            villeLivraison: 'Colmar',
        );

        self::assertSame(IssueDeLecture::QuestionsAuClient, $demande->issue());
    }

    public function testUneDistanceConnueNEstJamaisEnAttente(): void
    {
        // Une distance donnée mais un doute signalé : la question va au client.
        $demande = self::table(champsManquants: ['finition'], ville: 'Colmar');

        self::assertSame(IssueDeLecture::QuestionsAuClient, $demande->issue());
    }

    public function testUnHorsSujetNAppelleNiDevisNiQuestion(): void
    {
        $spam = self::table(nature: NatureDemande::HorsSujet);

        self::assertSame(IssueDeLecture::HorsSujet, $spam->issue());
        self::assertFalse($spam->estExploitable());
        self::assertSame('', $spam->questionsAPoser());
    }

    /** Les dimensions d'une pièce du catalogue sont là, mais son prix est déjà fixé. */
    public function testUneCommandeCatalogueNEstJamaisChiffree(): void
    {
        $commande = self::table(nature: NatureDemande::CommandeCatalogue);

        self::assertSame(IssueDeLecture::CommandeCatalogue, $commande->issue());
        self::assertFalse($commande->estExploitable());
        self::assertSame('', $commande->questionsAPoser());
    }

    public function testLaHauteurStandardGardeLaNature(): void
    {
        $commande = self::table(sans: 'hauteur', nature: NatureDemande::CommandeCatalogue)->avecHauteurStandard();

        self::assertSame(NatureDemande::CommandeCatalogue, $commande->nature);
    }

    public function testParDefautUneDemandeEstUnProjet(): void
    {
        self::assertSame(NatureDemande::Projet, self::table()->nature);
    }

    /** @param list<string> $champsManquants */
    private static function table(
        ?string $sans = null,
        array $champsManquants = [],
        ?string $ville = null,
        NatureDemande $nature = NatureDemande::Projet,
    ): DemandeExtraite {
        return new DemandeExtraite(
            famille: FamilleProduit::Table,
            longueurMm: 'longueur' === $sans ? null : 1_200,
            largeurMm: 'largeur' === $sans ? null : 600,
            hauteurMm: 'hauteur' === $sans ? null : 350,
            surfaceMetresCarres: null,
            distanceKm: 'distance' === $sans ? null : 100,
            champsManquants: $champsManquants,
            villeLivraison: $ville,
            nature: $nature,
        );
    }

    public function testUnMurSurDuBoisEstIrrealisable(): void
    {
        $mur = self::mur(support: SupportMur::Bois);

        self::assertSame(IssueDeLecture::Irrealisable, $mur->issue());
        self::assertFalse($mur->estExploitable());
        self::assertSame('', $mur->questionsAPoser());
    }

    public function testUnMurSurDuBoisResteIrrealisableMemeIncomplet(): void
    {
        self::assertSame(
            IssueDeLecture::Irrealisable,
            self::mur(sans: 'surface', champsManquants: ['surface'], support: SupportMur::Bois)->issue(),
        );
    }

    public function testUnMurTapisseEstChiffrable(): void
    {
        self::assertSame(IssueDeLecture::Chiffrable, self::mur(support: SupportMur::Tapisserie)->issue());
    }

    public function testUnMurAuSupportInconnuEstUneQuestionAuClient(): void
    {
        self::assertSame(IssueDeLecture::QuestionsAuClient, self::mur(sans: 'support')->issue());
    }

    public function testUnMurSansKilometresAttendSaDistance(): void
    {
        $mur = new DemandeExtraite(
            famille: FamilleProduit::Mur,
            longueurMm: null,
            largeurMm: null,
            hauteurMm: null,
            surfaceMetresCarres: 6,
            distanceKm: null,
            champsManquants: ['distance'],
            villeLivraison: 'Colmar',
            support: SupportMur::Placo,
        );

        self::assertSame(IssueDeLecture::EnAttenteDeDistance, $mur->issue());
    }

    /** Le support n'a pas de sens pour une table : il ne la rend pas irréalisable. */
    public function testLeBoisNeConcerneQueLesMurs(): void
    {
        $table = new DemandeExtraite(
            famille: FamilleProduit::Table,
            longueurMm: 1_200,
            largeurMm: 600,
            hauteurMm: 350,
            surfaceMetresCarres: null,
            distanceKm: 100,
            support: SupportMur::Bois,
        );

        self::assertSame(IssueDeLecture::Chiffrable, $table->issue());
    }

    public function testLaHauteurStandardGardeLeSupport(): void
    {
        $demande = new DemandeExtraite(
            famille: FamilleProduit::Table,
            longueurMm: 1_200,
            largeurMm: 600,
            hauteurMm: null,
            surfaceMetresCarres: null,
            distanceKm: 100,
            support: SupportMur::Placo,
        );

        self::assertSame(SupportMur::Placo, $demande->avecHauteurStandard()->support);
    }

    public function testLeSupportDuFormulaireCompleteLaDemande(): void
    {
        $mur = self::mur(sans: 'support', champsManquants: ['support du mur', 'Support'])
            ->avecSupport(SupportMur::Tapisserie);

        self::assertSame(SupportMur::Tapisserie, $mur->support);
        self::assertSame([], $mur->champsManquants);
        self::assertSame(IssueDeLecture::Chiffrable, $mur->issue());
    }

    public function testLeSupportDuFormulaireNeRetireQueLeSupportDesChampsManquants(): void
    {
        $mur = self::mur(sans: 'support', champsManquants: [' Support', 'surface'])->avecSupport(SupportMur::Placo);

        self::assertSame(['surface'], $mur->champsManquants);
    }

    public function testLeSupportDuFormulaireGardeLeResteDeLaDemande(): void
    {
        $avant = new DemandeExtraite(
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
        );

        self::assertEquals(
            new DemandeExtraite(
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
                support: SupportMur::Enduit,
            ),
            $avant->avecSupport(SupportMur::Enduit),
        );
    }

    public function testDuBoisDansLeMessageLEmporteSurLeFormulaire(): void
    {
        $mur = self::mur(support: SupportMur::Bois);

        self::assertSame($mur, $mur->avecSupport(SupportMur::Placo));
    }

    public function testSansSupportDansLeFormulaireRienNeChange(): void
    {
        $mur = self::mur(support: SupportMur::Enduit);

        self::assertSame($mur, $mur->avecSupport(null));
    }

    /** @param list<string> $champsManquants */
    private static function mur(
        ?string $sans = null,
        array $champsManquants = [],
        SupportMur $support = SupportMur::Maconne,
    ): DemandeExtraite {
        return new DemandeExtraite(
            famille: FamilleProduit::Mur,
            longueurMm: null,
            largeurMm: null,
            hauteurMm: null,
            surfaceMetresCarres: 'surface' === $sans ? null : 6,
            distanceKm: 'distance' === $sans ? null : 200,
            champsManquants: $champsManquants,
            support: 'support' === $sans ? null : $support,
        );
    }
}
