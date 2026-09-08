<?php

declare(strict_types=1);

namespace App\Command;

use App\Content\ContentImporter;
use App\Content\ValidationError;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'docs:import',
    description: 'Importiert die Markdown-Inhalte aus content/ in die Datenbank (idempotent).',
)]
final class DocsImportCommand extends Command
{
    public function __construct(
        private readonly ContentImporter $importer,
        #[Autowire('%kernel.project_dir%/content')]
        private readonly string $defaultContentDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nur prüfen und rendern, nichts in die Datenbank schreiben')
            ->addOption('content-dir', null, InputOption::VALUE_REQUIRED, 'Verzeichnis mit entries/ und adr/', $this->defaultContentDir)
            ->setHelp(<<<'HELP'
                Liest <info>content/entries/*.md</info> und <info>content/adr/*.md</info>, prüft Frontmatter und Pflicht-Abschnitte
                (Schema in ADR-004) und schreibt Einträge, ADRs und Tags per Upsert in PostgreSQL.
                Dateien, die verschwunden sind, werden aus der Datenbank entfernt.

                  <info>bin/console docs:import --dry-run</info>   nur validieren (Exit-Code 1 bei Fehlern)
                  <info>bin/console docs:import</info>             importieren
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = true === $input->getOption('dry-run');
        $contentDir = $input->getOption('content-dir');

        if (!\is_string($contentDir) || '' === $contentDir) {
            $io->error('Die Option --content-dir braucht einen Pfad.');

            return Command::FAILURE;
        }

        $io->text(\sprintf('Prüfe Inhalte in <comment>%s</comment> …', $contentDir));

        try {
            $result = $this->importer->import($contentDir, $dryRun);
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if (!$result->isSuccessful()) {
            $io->table(
                ['Datei', 'Feld', 'Fehler'],
                array_map(
                    static fn(ValidationError $error): array => [$error->file, $error->field, $error->message],
                    $result->errors,
                ),
            );
            $io->error(\sprintf('%d Fehler gefunden – nichts importiert.', \count($result->errors)));

            return Command::FAILURE;
        }

        $summary = \sprintf('%d Einträge, %d ADRs', $result->entries, $result->adrs);

        if ($result->dryRun) {
            $io->success(\sprintf('Alle Dateien sind gültig (%s). Trockenlauf – nichts geschrieben.', $summary));

            return Command::SUCCESS;
        }

        $io->success(\sprintf('Import abgeschlossen: %s, %d gelöscht.', $summary, $result->deleted));

        return Command::SUCCESS;
    }
}
