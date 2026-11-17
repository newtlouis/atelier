<?php

declare(strict_types=1);

namespace App\Tests\Extraction\Cout;

use App\Extraction\Cout\GrilleTarifaire;
use App\Extraction\Cout\ModeleInconnu;
use App\Extraction\Cout\UsageAppel;
use PHPUnit\Framework\TestCase;

final class GrilleTarifaireTest extends TestCase
{
    public function testUnAppelTypiqueCouteEnvironUnCentimeDeDollar(): void
    {
        // Le chiffre qui compte commercialement : sur un devis à 1 800 €,
        // l'extraction coûte à peine plus d'un centime.
        $cout = new GrilleTarifaire()->coutEnNanoDollars($this->usage(
            tokensEntree: 1_400,
            tokensSortie: 300,
        ));

        // 1 400 × 4 000 + 300 × 20 000 = 11 600 000 nano-dollars.
        self::assertSame(11_600_000, $cout);
        self::assertSame('0,0116 $', GrilleTarifaire::enDollars($cout));
    }

    public function testLeCacheDiviseLeCoutDeLaConsigne(): void
    {
        $sansCache = new GrilleTarifaire()->coutEnNanoDollars($this->usage(tokensEntree: 1_000));
        $avecCache = new GrilleTarifaire()->coutEnNanoDollars($this->usage(tokensCacheLecture: 1_000));

        // 200 contre 4 000 par jeton : un vingtième du prix sur Opus 5.5.
        self::assertSame(4_000_000, $sansCache);
        self::assertSame(200_000, $avecCache);
        self::assertSame($sansCache, $avecCache * 20);
    }

    public function testEcrireDansLeCacheCoutePlusCherQuUnJetonNormal(): void
    {
        // Le premier appel paie l'écriture : 1,25 fois le tarif d'entrée.
        // C'est ce qui rend le cache perdant sur un appel unique, et gagnant dès le second.
        $ecriture = new GrilleTarifaire()->coutEnNanoDollars($this->usage(tokensCacheEcriture: 1_000));

        self::assertSame(5_000_000, $ecriture);
    }

    public function testHaikuCouteQuaranteFoisMoinsCherQuOpus(): void
    {
        $grille = new GrilleTarifaire();

        $opus = $grille->coutEnNanoDollars($this->usage(tokensEntree: 1_400, tokensSortie: 300));
        $haiku = $grille->coutEnNanoDollars($this->usage(
            modele: 'claude-haiku-5-5',
            tokensEntree: 1_400,
            tokensSortie: 300,
        ));

        // C'est l'écart que l'évaluation doit justifier : Haiku ne se choisit
        // pas au prix, mais à la qualité mesurée sur le jeu de cas.
        self::assertSame($opus, $haiku * 40);
    }

    public function testUnAppelDejaJournaliseSurUnAncienModeleRestePrixable(): void
    {
        // Les appels en base portent l'identifiant du modèle de l'époque.
        // Changer de modèle par défaut ne doit pas rendre leur coût incalculable.
        $cout = new GrilleTarifaire()->coutEnNanoDollars($this->usage(
            modele: 'claude-opus-5',
            tokensEntree: 1_400,
            tokensSortie: 300,
        ));

        self::assertSame(14_500_000, $cout);
    }

    public function testUnModeleSansTarifEstRefuseEtNonFactureAZero(): void
    {
        // Un coût silencieusement faux est pire qu'une absence de coût :
        // on le croit.
        $this->expectException(ModeleInconnu::class);
        $this->expectExceptionMessage('Aucun tarif connu pour le modèle « claude-inexistant-9 »');

        new GrilleTarifaire()->coutEnNanoDollars($this->usage(modele: 'claude-inexistant-9'));
    }

    public function testUnAppelEnEchecNeCouteRien(): void
    {
        $cout = new GrilleTarifaire()->coutEnNanoDollars(
            UsageAppel::echec('claude-opus-5-5', 'v1', 'délai dépassé', latenceMs: 30_000),
        );

        self::assertSame(0, $cout);
    }

    private function usage(
        string $modele = 'claude-opus-5-5',
        int $tokensEntree = 0,
        int $tokensSortie = 0,
        int $tokensCacheEcriture = 0,
        int $tokensCacheLecture = 0,
    ): UsageAppel {
        return new UsageAppel(
            modele: $modele,
            versionPrompt: 'v1',
            tokensEntree: $tokensEntree,
            tokensSortie: $tokensSortie,
            tokensCacheEcriture: $tokensCacheEcriture,
            tokensCacheLecture: $tokensCacheLecture,
            latenceMs: 800,
            succes: true,
        );
    }
}
