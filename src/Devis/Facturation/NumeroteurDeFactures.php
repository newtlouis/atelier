<?php

declare(strict_types=1);

namespace App\Devis\Facturation;

use App\Devis\Entite\CompteurFactures;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;

/**
 * Attribue le prochain numéro de facture, sans trou et sans doublon.
 *
 * La mécanique tient en une ligne : on verrouille la ligne du compteur en base
 * (SELECT … FOR UPDATE), on l'incrémente, et on relâche à la fin de la
 * transaction. Deux requêtes simultanées ne se croisent pas — la seconde attend
 * la première.
 *
 * ⚠ À appeler obligatoirement dans une transaction déjà ouverte : hors
 * transaction, le verrou serait relâché immédiatement et deux factures
 * pourraient recevoir le même numéro. La méthode le vérifie plutôt que de
 * l'espérer.
 */
final readonly class NumeroteurDeFactures
{
    public function __construct(private EntityManagerInterface $em) {}

    public function prochainNumero(int $annee): string
    {
        if (!$this->em->getConnection()->isTransactionActive()) {
            throw new LogicException(
                'La numérotation doit se faire dans une transaction, sinon deux '
                . 'factures peuvent recevoir le même numéro.',
            );
        }

        $compteur = $this->em->find(CompteurFactures::class, $annee, LockMode::PESSIMISTIC_WRITE);

        if (!$compteur instanceof CompteurFactures) {
            $compteur = new CompteurFactures($annee);
            $this->em->persist($compteur);
            // Insertion immédiate : la ligne doit exister pour être verrouillée
            // par les transactions concurrentes.
            $this->em->flush();
        }

        $numero = $compteur->numeroSuivant();
        $this->em->flush();

        return \sprintf('FA-%d-%04d', $annee, $numero);
    }
}
