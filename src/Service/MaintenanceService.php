<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;

class MaintenanceService
{
    private readonly string $configPath;
    private readonly Filesystem $filesystem;
    private const DEFAULT_MESSAGE = 'Cette partie du site est actuellement en maintenance. <br> Elle sera de retour très prochainement. Merci de votre patience.';

    public function __construct(string $projectDir)
    {
        $this->configPath = $projectDir . '/var/maintenance.json';
        $this->filesystem = new Filesystem();
    }

    /**
     * Récupère la configuration de maintenance.
     */
    private function getConfig(): array
    {
        if (!$this->filesystem->exists($this->configPath)) {
            return [];
        }
        $content = file_get_contents($this->configPath);
        return json_decode($content, true)['active_categories'] ?? [];
    }

    /**
     * Vérifie si une catégorie spécifique est en maintenance.
     */
    public function isCategoryActive(string $category): bool
    {
        return array_key_exists($category, $this->getConfig());
    }

    /**
     * Récupère la configuration pour une catégorie (message, etc.).
     * @return array|null
     */
    public function getCategoryConfig(string $category): ?array
    {
        $config = $this->getConfig();
        return $config[$category] ?? null;
    }

    /**
     * Récupère le message pour une catégorie, ou un message par défaut.
     */
    public function getMessageForCategory(string $category): string
    {
        $config = $this->getConfig();
        return $config[$category]['message'] ?? self::DEFAULT_MESSAGE;
    }

    /**
     * Active le mode maintenance pour une catégorie avec un message personnalisé.
     */
    public function activate(string $category, ?string $message): void
    {
        $config = $this->getConfig();
        $config[$category] = [
            'message' => !empty($message) ? $message : self::DEFAULT_MESSAGE
        ];
        $this->saveConfig($config);
    }

    /**
     * Désactive le mode maintenance pour une catégorie.
     */
    public function deactivate(string $category): void
    {
        $config = $this->getConfig();
        if (array_key_exists($category, $config)) {
            unset($config[$category]);
            $this->saveConfig($config);
        }
    }

    /**
     * Sauvegarde la configuration dans le fichier JSON.
     */
    private function saveConfig(array $config): void
    {
        $data = ['active_categories' => $config];
        $this->filesystem->dumpFile($this->configPath, json_encode($data, JSON_PRETTY_PRINT));
    }
}