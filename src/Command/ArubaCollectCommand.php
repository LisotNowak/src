<?php
/**
 * php bin/console aruba:collect                  collecte complète (à planifier : tâche Windows / cron)
 * php bin/console aruba:collect --switch <id>    un seul switch, résultat JSON brut (usage interne)
 */
declare(strict_types=1);

namespace App\Command;

use App\Service\Aruba\Collector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'aruba:collect', description: 'Collecte des switchs Aruba CX (Gestion switch)')]
class ArubaCollectCommand extends Command
{
    public function __construct(private Collector $collector)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('switch', null, InputOption::VALUE_REQUIRED, 'Interroge un seul switch (usage interne)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($id = $input->getOption('switch')) {
            $output->write(json_encode($this->collector->collectSwitch($id),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
                false, OutputInterface::OUTPUT_RAW);
            return Command::SUCCESS;
        }
        $t0 = microtime(true);
        if (!$this->collector->run(fn(string $m) => $output->writeln($m, OutputInterface::OUTPUT_RAW))) {
            return 2;
        }
        $output->writeln(sprintf('Collecte terminée en %.1f s', microtime(true) - $t0));
        return Command::SUCCESS;
    }
}
