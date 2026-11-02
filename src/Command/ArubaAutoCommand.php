<?php
/**
 * Collecte automatique (intervalle réglé dans Gestion switch > Configuration > Valeurs par défaut et options).
 *
 * php bin/console aruba:auto --boucle   tourne en continu, vérifie toutes les 30 s (worker du serveur Symfony,
 *                                       déclaré dans .symfony.local.yaml)
 * php bin/console aruba:auto            un seul passage : à lancer chaque minute par une tâche planifiée
 *                                       Windows (ou cron) quand le site n'est pas servi par le serveur Symfony
 */
declare(strict_types=1);

namespace App\Command;

use App\Service\Aruba\Collector;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'aruba:auto', description: 'Collecte automatique des switchs (Gestion switch)')]
class ArubaAutoCommand extends Command
{
    private const PAUSE = 30;

    public function __construct(private Collector $collector, private Connection $db, private string $dataDir)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('boucle', null, InputOption::VALUE_NONE, 'Tourne en continu (vérification toutes les 30 s)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $log = fn(string $m) => $output->writeln(Collector::heure() . ' ' . $m, OutputInterface::OUTPUT_RAW);
        if (!$input->getOption('boucle')) {
            $log($this->collector->autoTick(false));
            return Command::SUCCESS;
        }

        // une seule boucle à la fois : une deuxième instance attend que la première s'arrête
        $lock = fopen($this->dataDir . DIRECTORY_SEPARATOR . 'auto.lock', 'c');
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            $log('Planificateur déjà lancé : en attente');
            flock($lock, LOCK_EX);
        }
        $log('Planificateur démarré');
        $previous = null;
        for (;;) {
            try {
                $done = $this->collector->autoTick(true);
                // une ligne par changement, pas une toutes les 30 s
                if ($done !== $previous) {
                    $log($done);
                    $previous = $done;
                }
            } catch (\Throwable $e) {
                $log('Erreur : ' . $e->getMessage());
                $previous = null;
                // connexion coupée par le serveur PostgreSQL : rouverte au passage suivant
                $this->db->close();
            }
            sleep(self::PAUSE);
        }
    }
}
