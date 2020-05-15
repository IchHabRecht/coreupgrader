<?php
declare(strict_types = 1);
namespace IchHabRecht\Upgrader\Command;

use Helhum\Typo3Console\Mvc\Cli\CommandDispatcher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\OutputStyle;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpgradeCommand extends Command
{
    /**
     * @var CommandDispatcher
     */
    private $commandDispatcher;

    public function __construct(string $name = null, CommandDispatcher $commandDispatcher = null)
    {
        parent::__construct($name);

        if ($commandDispatcher === null) {
            $commandDispatcher = CommandDispatcher::createFromCommandRun();
        }
        $this->commandDispatcher = $commandDispatcher;
    }

    protected function configure()
    {
        $this->setDescription('Run necessary upgrade wizards');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $this->runUpdatePrepare($io);
        $this->runUpgradeWizards($io, $input->isInteractive());

        return 0;
    }

    private function runUpdatePrepare(OutputStyle $io)
    {
        $io->title('Preparing upgrade');
        $output = $this->commandDispatcher->executeCommand('upgrade:prepare');
        $io->success($this->formatOutput($output));
        $io->newLine(2);
    }

    private function runUpgradeWizards(OutputStyle $io, bool $isInteractive = true)
    {
        $io->title('Running TYPO3 upgrade wizards');
        $upgradeWizards = require __DIR__ . '/../../Configuration/Upgrades.php';
        foreach ($upgradeWizards as $version => $versionUpgrades) {
            $output = [];
            $io->section('Running upgrade to TYPO3 ' . $version);
            if (!$isInteractive) {
                $identifier = array_map(function ($upgradeArray) {
                    return key($upgradeArray);
                }, array_values($versionUpgrades));
                $output[$version] = $this->commandDispatcher->executeCommand(
                    'upgrade:run',
                    array_merge(
                        $identifier,
                        [
                            '--no-interaction',
                            '--deny',
                            'all',
                        ]
                    )
                );
            } else {
                $io->progressStart(count($versionUpgrades));
                foreach ($versionUpgrades as $key => $upgradeArray) {
                    foreach ($upgradeArray as $identifier => $class) {
                        $output[$class] = $this->commandDispatcher->executeCommand(
                            'upgrade:run',
                            [
                                $identifier,
                                '--no-interaction',
                                '--deny',
                                'all',
                            ]
                        );
                    }
                    $io->progressAdvance(1);
                }
                $io->progressFinish();
            }
            if ($io->isVerbose()) {
                foreach ($output as $class => $wizardOutput) {
                    $io->note(
                        [
                            $class . ':',
                            $this->formatOutput($wizardOutput),
                        ]
                    );
                }
            }
        }
    }

    private function formatOutput(string $output): string
    {
        return implode(PHP_EOL, preg_split("/\r\n|\n|\r/", strip_tags($output)));
    }
}