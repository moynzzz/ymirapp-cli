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

namespace Ymir\Cli\Tool;

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Filesystem\Filesystem;
use Ymir\Cli\Process\Process;

class Ssh extends CommandLineTool
{
    /**
     * Creates an SSH tunnel to a bastion host and returns the running process.
     */
    public static function tunnelBastionHost(array $bastionHost, int $localPort, string $remoteHost, int $remotePort): Process
    {
        if (!isset($bastionHost['endpoint'], $bastionHost['private_key'])) {
            throw new InvalidArgumentException('Invalid bastion host given');
        }

        $filesystem = new Filesystem();
        $sshDirectory = rtrim((string) getenv('HOME'), '/').'/.ssh';

        if (!is_dir($sshDirectory)) {
            $filesystem->mkdir($sshDirectory, 0700);
        }

        $identityFilePath = $sshDirectory.'/ymir-tunnel';

        $filesystem->dumpFile($identityFilePath, $bastionHost['private_key']);
        $filesystem->chmod($identityFilePath, 0600);

        $command = sprintf('ec2-user@%s -i %s -o LogLevel=error -L %s:%s:%s -N', $bastionHost['endpoint'], $identityFilePath, $localPort, $remoteHost, $remotePort);

        $process = self::getProcess($command, null, null);
        $process->start();

        return $process;
    }

    /**
     * {@inheritdoc}
     */
    protected static function getCommand(): string
    {
        return 'ssh';
    }

    /**
     * {@inheritdoc}
     */
    protected static function getName(): string
    {
        return 'SSH';
    }
}