<?php

declare(strict_types=1);

namespace App\Application;

use App\Devis\Entite\Demande;
use App\Devis\Infrastructure\EnvoisNetlify;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Fait entrer les envois du formulaire dans l'atelier : une demande par envoi
 * nouveau, puis sa lecture et son chiffrage en file.
 *
 * Relançable à volonté. Un envoi déjà importé est reconnu à son identifiant
 * Netlify, et la base refuse de toute façon un doublon (index unique) : le
 * pire cas d'une course entre deux imports est une erreur, jamais deux devis.
 */
final readonly class ImportDesEnvois
{
    public function __construct(
        private EnvoisNetlify $source,
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
    ) {}

    public function importe(): BilanImport
    {
        $nouveaux = $dejaConnus = $vides = 0;

        foreach ($this->source->envois() as $envoi) {
            if (null !== $this->em->getRepository(Demande::class)->findOneBy(['idNetlify' => $envoi->idNetlify])) {
                ++$dejaConnus;

                continue;
            }
            // Un envoi sans aucun champ rempli ne dit rien : il n'y a rien à lire.
            if ('' === $envoi->texteClient()) {
                ++$vides;

                continue;
            }

            $demande = Demande::depuisFormulaire($envoi);
            $this->em->persist($demande);
            $this->em->flush();
            // Après l'écriture : le message ne doit pas partir pour une demande
            // que la base n'a pas.
            $this->bus->dispatch(new ExtraireEtChiffrer($demande->id));
            ++$nouveaux;
        }

        return new BilanImport($nouveaux, $dejaConnus, $vides);
    }
}
