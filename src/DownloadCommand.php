<?php

namespace Andig;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class DownloadCommand extends Command
{
    use ConfigTrait;

    protected function configure()
    {
        $this->setName('download')
            ->setDescription('Load from CardDAV server')
            ->addOption('json', 'j', InputOption::VALUE_REQUIRED, 'export result to json file');

        $this->addConfig();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->loadConfig($input);

        $progress = new ProgressBar($output);
        $progress->start();

        $server = $this->config['server'];
        $cards = download(backendProvider($server), function () use ($progress) {
            $progress->advance();
        });

        $progress->finish();

        error_log(sprintf("\nDownloaded %d vcards", count($cards)));

        $jsonStr = json_encode($cards, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE);

        if ($json = $input->getOption('json')) {
            file_put_contents($json, $jsonStr);
        }

        echo $jsonStr;
    }
}
