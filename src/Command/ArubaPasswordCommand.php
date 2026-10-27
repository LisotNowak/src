<?php
/**
 * php bin/console aruba:password            mot de passe commun à tous les switchs (secrets/default.txt)
 * php bin/console aruba:password <id>       mot de passe propre à un switch (secrets/<id>.txt)
 *
 * Le mot de passe est chiffré en AES-256 avec la clé config/aruba/secrets/aruba.AES.key (créée au
 * premier appel), au même format que ConvertFrom-SecureString -Key de PowerShell.
 */
declare(strict_types=1);

namespace App\Command;

use App\Service\Aruba\Config;
use App\Service\Aruba\SecurePassword;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'aruba:password', description: 'Chiffre le mot de passe d\'un switch (Gestion switch)')]
class ArubaPasswordCommand extends Command
{
    public function __construct(private Config $config)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('switch', InputArgument::OPTIONAL, 'Identifiant du switch (config.json), tous si absent');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = (string) $input->getArgument('switch');
        if ($id !== '') {
            $this->config->switch($id);   // erreur si l'identifiant est inconnu
        }
        $pwd = (string) $io->askHidden($id !== '' ? "Mot de passe du switch {$id}" : 'Mot de passe commun aux switchs');
        if ($pwd === '') {
            $io->error('Mot de passe vide');
            return Command::FAILURE;
        }
        if ($io->askHidden('Confirmation') !== $pwd) {
            $io->error('Les deux saisies diffèrent');
            return Command::FAILURE;
        }
        $file = $this->config->path($this->config->secretFile($id ?: 'default'));
        SecurePassword::write($pwd, $file, $this->config->keyFile());
        $io->success(["Mot de passe chiffré dans {$file}", 'Clé : ' . $this->config->keyFile()]);
        return Command::SUCCESS;
    }
}
