<?php
/**
 * php bin/console aruba:import
 *
 * Reprise, une seule fois, des anciens fichiers de Gestion switch dans le schéma gestion_switch :
 *   config/aruba/config.json   liste "switches"  → table equipement (la liste est ensuite retirée du fichier,
 *                                                   copie de l'original dans config.json.avant-bdd)
 *   var/aruba/history.json     appareils vus      → tables appareil et appareil_mouvement
 *   var/aruba/snapshot.json    dernière collecte  → table collecte (+ identification des switchs)
 * Les fichiers repris sont renommés en *.importe. Relancer la commande ne crée pas de doublon.
 */
declare(strict_types=1);

namespace App\Command;

use App\Service\Aruba\Collector;
use App\Service\Aruba\Config;
use App\Service\Aruba\Inventory;
use App\Service\Aruba\Store;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'aruba:import', description: 'Reprend en base les anciens fichiers JSON de Gestion switch')]
class ArubaImportCommand extends Command
{
    public function __construct(
        private Config $config,
        private Store $store,
        #[Autowire('%kernel.project_dir%/var/aruba')]
        private string $dataDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // 1. switchs et clusters Wi-Fi
        $added = 0;
        $fileSwitches = $this->config->fileSwitches();
        foreach ($fileSwitches as $sw) {
            if ($this->config->rawSwitch($sw['id']) === null) {
                $this->config->saveSwitch($sw);
                $added++;
            }
        }
        if ($fileSwitches) {
            $this->config->removeFileSwitches();
            $io->writeln(sprintf('Équipements : %d ajoutés, %d déjà en base (liste retirée de config.json)',
                $added, count($fileSwitches) - $added));
        } else {
            $io->writeln('Équipements : aucun dans config.json');
        }

        // 2. appareils vus (historique)
        $oui = $this->config->path('oui.csv');
        if (is_file($oui)) {
            Inventory::loadOui($oui);
        }
        $historyFile = $this->dataDir . DIRECTORY_SEPARATOR . 'history.json';
        if (!is_file($historyFile)) {
            $io->writeln('Historique : pas de history.json');
        } elseif ($this->store->countAppareils() > 0) {
            $io->warning('Historique : la table appareil n\'est pas vide, history.json n\'est pas repris');
        } else {
            $history = Collector::loadJson($historyFile, null);
            if (!is_array($history)) {
                $io->error('history.json illisible');
                return Command::FAILURE;
            }
            $n = $this->store->importHistory($history);
            rename($historyFile, $historyFile . '.importe');
            $io->writeln(sprintf('Historique : %d appareils repris', $n));
        }

        // 3. dernière collecte
        $snapshotFile = $this->dataDir . DIRECTORY_SEPARATOR . 'snapshot.json';
        $snap = is_file($snapshotFile) ? Collector::loadJson($snapshotFile, null) : null;
        if (!is_array($snap) || empty($snap['collected_at'])) {
            $io->writeln('Collecte : pas de snapshot.json exploitable');
        } elseif ($this->store->hasCollecte($snap['collected_at'])) {
            $io->writeln('Collecte : déjà en base');
            rename($snapshotFile, $snapshotFile . '.importe');
        } else {
            $jobs = [];
            foreach ([...$snap['switches'] ?? [], ...$snap['wifi'] ?? []] as $s) {
                $jobs[$s['id']] = ['statut' => empty($s['error']) ? 'ok' : 'erreur', 'duree' => null, 'erreur' => $s['error'] ?? null];
            }
            $id = $this->store->record($snap, $jobs, [], [], new \DateTimeImmutable($snap['collected_at']), 'import',
                (int) $this->config->get('retention_days', 0));
            rename($snapshotFile, $snapshotFile . '.importe');
            $io->writeln(sprintf('Collecte : celle du %s reprise (n° %d)', $snap['collected_at'], $id));
        }

        $io->success('Reprise terminée. Les données de Gestion switch sont maintenant lues dans le schéma gestion_switch.');
        return Command::SUCCESS;
    }
}
