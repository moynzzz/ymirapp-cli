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

use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Ymir\Cli\Console\OutputStyle;

class DeployProjectCommand extends AbstractProjectDeploymentCommand
{
    /**
     * The name of the command.
     *
     * @var string
     */
    public const NAME = 'project:deploy';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName(self::NAME)
            ->setDescription('Deploy project to an environment')
            ->setAliases(['deploy'])
            ->addArgument('environment', InputArgument::OPTIONAL, 'The environment name', 'staging');
    }

    /**
     * {@inheritdoc}
     */
    protected function createDeployment(InputInterface $input, OutputStyle $output): int
    {
        $environment = $this->getStringArgument($input, 'environment');
        $projectId = $this->projectConfiguration->getProjectId();

        $this->invoke($output, ValidateProjectCommand::NAME, ['environments' => $environment]);
        $this->invoke($output, BuildProjectCommand::NAME, ['environment' => $environment]);

        $deploymentId = (int) $this->apiClient->createDeployment($projectId, $environment, $this->projectConfiguration)->get('id');

        if (empty($deploymentId)) {
            throw new RuntimeException('There was an error creating the deployment');
        }

        return $deploymentId;
    }

    /**
     * {@inheritdoc}
     */
    protected function getSuccessMessage(string $environment): string
    {
        return sprintf('Project deployed successfully to "<comment>%s</comment>" environment', $environment);
    }
}
