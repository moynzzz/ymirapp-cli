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

namespace Ymir\Cli\Command\Database;

use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Ymir\Cli\Console\OutputInterface;

class DeleteDatabaseCommand extends AbstractDatabaseCommand
{
    /**
     * The name of the command.
     *
     * @var string
     */
    public const NAME = 'database:delete';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName(self::NAME)
            ->setDescription('Delete a database on a public database server')
            ->addArgument('name', InputArgument::OPTIONAL, 'The name of the database to delete')
            ->addOption('server', null, InputOption::VALUE_REQUIRED, 'The ID or name of the database server where the database will be deleted');
    }

    /**
     * {@inheritdoc}
     */
    protected function perform(InputInterface $input, OutputInterface $output)
    {
        $databaseServer = $this->determineDatabaseServer('On which database server would you like to delete a database?', $input, $output);

        if (!$databaseServer['publicly_accessible']) {
            throw new RuntimeException('Database on private database servers need to be manually deleted.');
        }

        $name = $this->getStringArgument($input, 'name');

        if (empty($name)) {
            $name = (string) $output->choice('Which database would you like to delete', $this->apiClient->getDatabases($databaseServer['id'])->filter(function (string $name) {
                return !in_array($name, ['information_schema', 'innodb', 'mysql', 'performance_schema', 'sys']);
            })->values());
        }

        if (!$output->confirm(sprintf('Are you sure you want to delete the "<comment>%s</comment>" database?', $name), false)) {
            return;
        }

        $this->apiClient->deleteDatabase($databaseServer['id'], $name);

        $output->info('Database deleted');
    }
}
