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

namespace Ymir\Cli\Command\Dns;

use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Ymir\Cli\Console\OutputInterface;

class CreateDnsZoneCommand extends AbstractDnsCommand
{
    /**
     * The name of the command.
     *
     * @var string
     */
    public const NAME = 'dns:zone:create';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName(self::NAME)
            ->setDescription('Create a new DNS zone')
            ->addArgument('name', InputArgument::OPTIONAL, 'The name of the domain managed by the created DNS zone')
            ->addOption('provider', null, InputOption::VALUE_REQUIRED, 'The cloud provider region where the DNS zone will created');
    }

    /**
     * {@inheritdoc}
     */
    protected function perform(InputInterface $input, OutputInterface $output)
    {
        $name = $this->getStringArgument($input, 'name');

        if (empty($name)) {
            $name = $output->ask('What is the name of the domain that the DNS zone will manage');
        }

        $providerId = $this->determineCloudProvider('Enter the ID of the cloud provider where the DNS zone will be created', $input, $output);

        if (!$output->confirm('A DNS zone will cost $0.50/month if it isn\'t deleted in the next 12 hours. Would you like to proceed?', true)) {
            return;
        }

        $zone = $this->apiClient->createDnsZone($providerId, $name);

        $nameServers = $this->wait(function () use ($zone) {
            return $this->apiClient->getDnsZone($zone['id'])->get('name_servers', []);
        });

        if (!empty($nameServers)) {
            $output->horizontalTable(
                ['Domain Name', new TableSeparator(), 'Name Servers'],
                [[$zone['domain_name'], new TableSeparator(), implode(PHP_EOL, $nameServers)]]
            );
        }

        $output->info('DNS zone created');

        if ($output->confirm('Do you want to import the root DNS records for this domain', false)) {
            $this->apiClient->importDnsRecords($zone['id']);
        }

        if ($output->confirm('Do you want to import DNS records for subdomains of this domain', false)) {
            $this->invoke($output, ImportDnsRecordsCommand::NAME, [
                'zone' => $zone['domain_name'],
            ]);
        }
    }
}
