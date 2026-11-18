<?php

declare(strict_types=1);

namespace App\Application;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:importer-envois',
    description: 'Importe les nouveaux envois du formulaire du site et les met en file de lecture.',
)]
final class ImporterLesEnvoisCommand extends Command
{
    public function __construct(private readonly ImportDesEnvois $import)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $bilan = $this->import->importe();

        new SymfonyStyle($input, $output)->success(\sprintf(
            '%d nouvel(s) envoi(s) importé(s) et mis en file, %d déjà connu(s), %d vide(s) ignoré(s).',
            $bilan->nouveaux,
            $bilan->dejaConnus,
            $bilan->vides,
        ));

        return Command::SUCCESS;
    }
}
