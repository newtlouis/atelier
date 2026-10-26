<?php

declare(strict_types=1);

namespace App\Extraction;

/**
 * Ne retient rien. Utile en test et tant que la persistance n'existe pas :
 * l'extracteur fonctionne sans, le traçage est une préoccupation séparée.
 */
final class JournalNul implements JournalDesAppels
{
    /** @var list<UsageAppel> */
    private array $usages = [];

    public function enregistre(UsageAppel $usage): void
    {
        $this->usages[] = $usage;
    }

    /** @return list<UsageAppel> Ce qui a été enregistré, pour les tests. */
    public function usages(): array
    {
        return $this->usages;
    }
}
