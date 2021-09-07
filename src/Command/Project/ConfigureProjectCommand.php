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

namespace Ymir\Cli\Command\Project;

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Tightenco\Collect\Support\Collection;
use Ymir\Cli\ApiClient;
use Ymir\Cli\CliConfiguration;
use Ymir\Cli\Command\AbstractProjectCommand;
use Ymir\Cli\Console\ConsoleOutput;
use Ymir\Cli\ProjectConfiguration\ProjectConfiguration;
use Ymir\Cli\ProjectConfiguration\WordPressConfigurationChangeInterface;
use Ymir\Cli\WpCli;

class ConfigureProjectCommand extends AbstractProjectCommand
{
    /**
     * The alias of the command.
     *
     * @var string
     */
    public const ALIAS = 'configure';

    /**
     * The name of the command.
     *
     * @var string
     */
    public const NAME = 'project:configure';

    /**
     * The build steps to perform.
     *
     * @var Collection
     */
    private $configurationChanges;

    /**
     * Constructor.
     */
    public function __construct(ApiClient $apiClient, CliConfiguration $cliConfiguration, ProjectConfiguration $projectConfiguration, iterable $configurationChanges = [])
    {
        parent::__construct($apiClient, $cliConfiguration, $projectConfiguration);

        $this->configurationChanges = new Collection();

        foreach ($configurationChanges as $configurationChange) {
            $this->addConfigurationChange($configurationChange);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName(self::NAME)
            ->setDescription('Configure the project by scanning your plugins and themes')
            ->setAliases([self::ALIAS])
            ->addArgument('environment', InputArgument::OPTIONAL, 'The name of the environment to configure', 'staging');
    }

    /**
     * {@inheritdoc}
     */
    protected function perform(InputInterface $input, ConsoleOutput $output)
    {
        if (!WpCli::isInstalledGlobally()) {
            throw new RuntimeException('WP-CLI needs to be available globally to scan your project');
        } elseif (!WpCli::isWordPressInstalled()) {
            throw new RuntimeException('WordPress needs to be installed and connected to a database to scan your project');
        }

        $environment = $this->getStringArgument($input, 'environment', false);

        if (!empty($environment) && !$this->projectConfiguration->hasEnvironment($environment)) {
            throw new InvalidArgumentException(sprintf('Environment "%s" not found in ymir.yml file', $environment));
        }

        $output->info('Scanning your project');

        $plugins = WpCli::listPlugins()->groupBy('status');

        $activePlugins = $plugins->only(['active', 'must-use'])->flatten(1);
        $inactivePlugins = $plugins->only(['inactive'])->flatten(1);

        $this->applyConfigurationChanges('The following plugin(s) are <comment>active</comment> and have available configuration changes:', $activePlugins, $output, $environment);
        $this->applyConfigurationChanges('The following plugin(s) are <comment>inactive</comment> and have available configuration changes:', $inactivePlugins, $output, $environment, false);

        $output->info('Project configured successfully');
    }

    /**
     * Add a configuration change to the command.
     */
    private function addConfigurationChange(WordPressConfigurationChangeInterface $configurationChange)
    {
        $this->configurationChanges[] = $configurationChange;
    }

    /**
     * Apply the given configuration changes to the given environment.
     */
    private function applyConfigurationChanges(string $message, Collection $plugins, ConsoleOutput $output, string $environment = '', bool $apply = true)
    {
        $filteredConfigurationChanges = $this->configurationChanges->filter(function (WordPressConfigurationChangeInterface $configurationChange) use ($plugins) {
            return $plugins->contains('name', $configurationChange->getName());
        });

        if ($filteredConfigurationChanges->isEmpty()) {
            return;
        }

        $output->info($message);

        $output->list($filteredConfigurationChanges->map(function (WordPressConfigurationChangeInterface $configurationChange) {
            return $configurationChange->getName();
        }));

        if (!$output->confirm('Do you want to apply them?', $apply)) {
            return;
        }

        foreach ($filteredConfigurationChanges as $configurationChange) {
            empty($environment)
                ? $this->projectConfiguration->applyChangesToEnvironments($configurationChange)
                : $this->projectConfiguration->applyChangesToEnvironment($environment, $configurationChange);
        }
    }
}
