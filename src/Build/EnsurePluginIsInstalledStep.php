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

namespace Ymir\Cli\Build;

use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Finder\Finder;
use Ymir\Cli\ProjectConfiguration;

class EnsurePluginIsInstalledStep implements BuildStepInterface
{
    /**
     * The build directory where the project files are copied to.
     *
     * @var string
     */
    private $buildDirectory;

    /**
     * Constructor.
     */
    public function __construct(string $buildDirectory)
    {
        $this->buildDirectory = rtrim($buildDirectory, '/');
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Ensuring Ymir plugin is installed';
    }

    /**
     * {@inheritdoc}
     */
    public function perform(string $environment, ProjectConfiguration $projectConfiguration)
    {
        $pluginsPath = 'bedrock' !== $projectConfiguration->getProjectType() ? '/wp-content/plugins' : '/web/app/plugins';

        $finder = Finder::create()
                        ->files()
                        ->in($this->buildDirectory.$pluginsPath)
                        ->depth('== 1')
                        ->name('ymir.php')
                        ->contains('Plugin Name: Ymir');

        if (0 === $finder->count()) {
            throw new RuntimeException('Ymir plugin not found');
        }
    }
}
