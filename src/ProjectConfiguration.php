<?php

declare(strict_types=1);

/*
 * This file is part of Ymir command-line tool.
 *
 * (c) Carl Alexander <support@ymirapp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ymir\Cli;

use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
use Tightenco\Collect\Contracts\Support\Arrayable;
use Tightenco\Collect\Support\Collection;

class ProjectConfiguration implements Arrayable
{
    /**
     * The parsed configuration.
     *
     * @var array
     */
    private $configuration;

    /**
     * The path to the configuration file.
     *
     * @var string
     */
    private $configurationFilePath;

    /**
     * The file system.
     *
     * @var Filesystem
     */
    private $filesystem;

    /**
     * Constructor.
     */
    public function __construct(string $configurationFilePath, Filesystem $filesystem)
    {
        $this->configurationFilePath = $configurationFilePath;
        $this->filesystem = $filesystem;
        $this->configuration = $this->load($configurationFilePath);
    }

    /**
     * Save the options back to the configuration file when we're destroying the object.
     */
    public function __destruct()
    {
        if (empty($this->configuration)) {
            return;
        }

        $this->save();
    }

    /**
     * Add a new environment node to the project configuration.
     */
    public function addEnvironment(string $name, ?array $options = null)
    {
        if ('bedrock' === $this->configuration['type']) {
            $options = array_merge(['build' => ['COMPOSER_MIRROR_PATH_REPOS=1 composer install']], (array) $options);
        }

        $this->configuration['environments'][$name] = $options;
    }

    /**
     * Add the given options to all project environments.
     */
    public function addOptionsToEnvironments(array $options)
    {
        foreach ($this->configuration['environments'] as $name => $environment) {
            $this->configuration['environments'][$name] = array_merge((array) $environment, $options);
        }
    }

    /**
     * Creates a new configuration from the given project.
     *
     * Overwrites the existing project configuration.
     */
    public function createNew(Collection $project, array $environments, string $type = '')
    {
        $this->configuration = $project->only(['id', 'name'])->all();
        $this->configuration['type'] = $type ?: 'wordpress';
        $this->configuration['environments'] = $environments;

        $this->save();
    }

    /**
     * Delete the project configuration.
     */
    public function delete()
    {
        $this->configuration = [];
        $this->filesystem->remove($this->configurationFilePath);
    }

    /**
     * Delete the given project environment.
     */
    public function deleteEnvironment(string $environment)
    {
        if ($this->hasEnvironment($environment)) {
            unset($this->configuration['environments'][$environment]);
        }
    }

    /**
     * Checks if the project configuration file exists.
     */
    public function exists(): bool
    {
        return $this->filesystem->exists($this->configurationFilePath);
    }

    /**
     * Get the configuration information for the given environment.
     */
    public function getEnvironment(string $environment): ?array
    {
        if (!$this->hasEnvironment($environment)) {
            throw new \InvalidArgumentException(sprintf('Environment "%s" not found in ymir.yml file', $environment));
        }

        return $this->configuration['environments'][$environment];
    }

    /**
     * Get the environments in the project configuration.
     */
    public function getEnvironments(): array
    {
        return array_keys($this->configuration['environments']);
    }

    /**
     * Get the project ID.
     */
    public function getProjectId(): int
    {
        if (empty($this->configuration['id'])) {
            throw new RuntimeException('No "id" found in ymir.yml file');
        }

        return (int) $this->configuration['id'];
    }

    /**
     * Get the project name.
     */
    public function getProjectName(): string
    {
        if (empty($this->configuration['name'])) {
            throw new RuntimeException('No "name" found in ymir.yml file');
        }

        return (string) $this->configuration['name'];
    }

    /**
     * Get the project type.
     */
    public function getProjectType(): string
    {
        if (empty($this->configuration['type'])) {
            throw new RuntimeException('No "type" found in ymir.yml file');
        }

        return (string) $this->configuration['type'];
    }

    /**
     * Check if the project configuration file has configuration information for the given environment.
     */
    public function hasEnvironment(string $environment): bool
    {
        return array_key_exists($environment, $this->configuration['environments']);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        return $this->configuration;
    }

    /**
     * Validates the loaded configuration file.
     */
    public function validate()
    {
        if (!$this->exists()) {
            throw new RuntimeException('No ymir.yml file found in current directory');
        } elseif (empty($this->configuration['id'])) {
            throw new RuntimeException('The ymir.yml file must have an "id"');
        } elseif (empty($this->configuration['environments'])) {
            throw new RuntimeException('The ymir.yml file must have at least one environment');
        }
    }

    /**
     * Load the options from the configuration file.
     */
    private function load(string $configurationFilePath): array
    {
        $configuration = [];

        if ($this->filesystem->exists($configurationFilePath)) {
            $configuration = Yaml::parse((string) file_get_contents($configurationFilePath));
        }

        if (!empty($configuration) && !is_array($configuration)) {
            throw new RuntimeException('Error parsing ymir.yml file');
        }

        return $configuration;
    }

    /**
     * Save the configuration options to the configuration file.
     */
    private function save()
    {
        $this->filesystem->dumpFile($this->configurationFilePath, Yaml::dump($this->configuration, 20, 2, Yaml::DUMP_NULL_AS_TILDE));
    }
}
