<?php

declare(strict_types=1);

namespace App\Application;

use App\Devis\Chiffrage\CommandeSousLeMinimum;
use App\Devis\Chiffrage\SaisieDeDistance;
use App\Devis\Entite\LectureDeDemande;
use App\Devis\Entite\LectureRepository;
use App\Shared\Contrat\FamilleProduit;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Pour chaque demande qui n'attend que ses kilomètres : la ville, un lien
 * d'itinéraire depuis l'atelier, et Louis tape la distance. Le calcul
 * automatique (géocodage) est reporté : quelques demandes par mois ne le
 * justifient pas, et une distance relue par un humain ne se trompe pas de
 * Saint-Martin.
 */
#[AsCommand(
    name: 'app:distance',
    description: 'Saisit la distance des demandes qui n\'attendent qu\'elle, et établit leur devis en brouillon.',
)]
final class SaisirLesDistancesCommand extends Command
{
    public function __construct(
        private readonly LectureRepository $lectures,
        private readonly SaisieDeDistance $saisie,
        private readonly string $villeAtelier,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $enAttente = $this->lectures->enAttenteDeDistance();

        if ([] === $enAttente) {
            $io->success('Aucune demande n\'attend de distance.');

            return Command::SUCCESS;
        }

        foreach ($enAttente as $lecture) {
            $ville = (string) $lecture->villeLivraison;
            $io->section(\sprintf('Demande du %s — %s', $lecture->demande->recueLe->format('d/m/Y'), $this->resume($lecture)));
            $io->text([
                'Livraison : ' . $ville,
                'Itinéraire : ' . $this->itineraire($ville),
            ]);

            $km = $io->ask('Distance aller en km (vide pour passer)', null, static function (mixed $reponse): ?int {
                if (!\is_string($reponse) || '' === trim($reponse)) {
                    return null;
                }
                if (1 !== preg_match('/^\d{1,5}$/', trim($reponse))) {
                    throw new RuntimeException('Un nombre entier de kilomètres, sans unité.');
                }

                return (int) trim($reponse);
            });

            if (!\is_int($km)) {
                $io->text('Passée.');

                continue;
            }

            try {
                $devis = $this->saisie->chiffre($lecture, $km);
                $io->success(\sprintf('Devis en brouillon : %s HT.', $devis->totalHorsTaxes()));
            } catch (CommandeSousLeMinimum $e) {
                $io->warning($e->getMessage());
            }
        }

        return Command::SUCCESS;
    }

    private function resume(LectureDeDemande $lecture): string
    {
        return match ($lecture->famille) {
            FamilleProduit::Table => \sprintf(
                'table %d × %d cm',
                intdiv((int) $lecture->longueurMm, 10),
                intdiv((int) $lecture->largeurMm, 10),
            ),
            FamilleProduit::Mur => \sprintf('mur de %d m²', (int) $lecture->surfaceMetresCarres),
            null => 'demande',
        };
    }

    private function itineraire(string $ville): string
    {
        return 'https://www.google.com/maps/dir/?' . http_build_query([
            'api' => 1,
            'origin' => $this->villeAtelier,
            'destination' => $ville,
        ]);
    }
}
