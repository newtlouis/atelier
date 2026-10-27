<?php

declare(strict_types=1);

namespace App\Tests\Extraction\Cout;

use App\Extraction\Cout\GrilleTarifaire;
use App\Extraction\Cout\ModeleInconnu;
use App\Extraction\Cout\UsageAppel;
use PHPUnit\Framework\TestCase;

final class GrilleTarifaireTest extends TestCase
{
    public function testUnAppelTypiqueCouteEnvironUnCentimeEtDemiDeDollar(): void
    {
        // Le chiffre qui compte commercialement : sur un devis à 1 800 €,
        // l'extraction coûte moins de deux centimes.
        $cout = new GrilleTarifaire()->coutEnNanoDollars($this->usage(
            tokensEntree: 1_400,
            tokensSortie: 300,
        ));

        // 1 400 × 5 000 + 300 × 25 000 = 14 500 000 nano-dollars.
        self::assertSame(14_500_000, $cout);
        self::assertSame('0,0145 $', GrilleTarifaire::enDollars($cout));
    }

    public function testLeCacheDiviseLeCoutDeLaConsigne(): void
    {
        $sansCache = new GrilleTarifaire()->coutEnNanoDollars($this->usage(tokensEntree: 1_000));
        $avecCache = new GrilleTarifaire()->coutEnNanoDollars($this->usage(tokensCacheLecture: 1_000));

        // 500 contre 5 000 par jeton : un dixième du prix.
        self::assertSame(5_000_000, $sansCache);
        self::assertSame(500_000, $avecCache);
        self::assertSame($sansCache, $avecCache * 10);
    }

    public function testEcrireDansLeCacheCoutePlusCherQuUnJetonNormal(): void
    {
        // Le premier appel paie l'écriture : 1,25 fois le tarif d'entrée.
        // C'est ce qui rend le cache perdant sur un appel unique, et gagnant dès le second.
        $ecriture = new GrilleTarifaire()->coutEnNanoDollars($this->usage(tokensCacheEcriture: 1_000));

        self::assertSame(6_250_000, $ecriture);
    }

    public function testHaikuCouteCinqFoisMoinsCherQuOpus(): void
    {
        $grille = new GrilleTarifaire();

        $opus = $grille->coutEnNanoDollars($this->usage(tokensEntree: 1_400, tokensSortie: 300));
        $haiku = $grille->coutEnNanoDollars($this->usage(
            modele: 'claude-haiku-4-5',
            tokensEntree: 1_400,
            tokensSortie: 300,
        ));

        self::assertSame($opus, $haiku * 5);
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
            UsageAppel::echec('claude-opus-5', 'v1', 'délai dépassé', latenceMs: 30_000),
        );

        self::assertSame(0, $cout);
    }

    private function usage(
        string $modele = 'claude-opus-5',
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
