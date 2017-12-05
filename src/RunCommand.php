<?php

namespace Andig;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class RunCommand extends Command
{
    use ConfigTrait;

    protected function configure()
    {
        $this->setName('run')
            ->setDescription('Download, convert and upload - all in one')
            ->addOption('image', 'i', InputOption::VALUE_NONE, 'download images');

        $this->addConfig();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->loadConfig($input);
        $progress = new ProgressBar($output);

        // download
        error_log("Downloading");

        $server = $this->config['server'];
        $backend = backendProvider($server);

        $progress->start();
        $vcards = download($backend, function () use ($progress) {
            $progress->advance();
        });
        $progress->finish();

        error_log(sprintf("\nDownloaded %d vcard(s)", count($vcards)));

        // parse and convert
        error_log("Parsing vcards");
        $cards = parse($vcards);

        // images
        if ($input->getOption('image')) {
            error_log("Downloading images");

            $progress->start();
            $cards = downloadImages($backend, $cards, function() use ($progress) {
                $progress->advance();
            });
            $progress->finish();

            error_log(sprintf("\nDownloaded %d image(s)", countImages($cards)));
        }

        // conversion
        $filters = $this->config['filters'];
        $filtered = filter($cards, $filters);

        error_log(sprintf("Converted %d vcard(s)", count($filtered)));

        // fritzbox format
        $phonebook = $this->config['phonebook'];
        $conversions = $this->config['conversions'];
        $xml = export($phonebook['name'], $filtered, $conversions);

        // upload
        error_log("Uploading");

        $xmlStr = $xml->asXML();

        $fritzbox = $this->config['fritzbox'];
        upload($xmlStr, $fritzbox['url'], $fritzbox['user'], $fritzbox['password'], $phonebook['id']);

        error_log("Uploaded fritz phonebook");
    }
}
