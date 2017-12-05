<?php

namespace Andig;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConvertCommand extends Command
{
    use ConfigTrait;

    const JSON_OPTIONS = \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE;

    protected function configure()
    {
        $this->setName('convert')
            ->setDescription('Convert Vcard to FritzBox format')
            ->addOption('raw', 'r', InputOption::VALUE_REQUIRED, 'export raw conversion result to json file')
            ->addArgument('filename', InputArgument::REQUIRED, 'filename');

        $this->addConfig();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->loadConfig($input);

        $filename = $input->getArgument('filename');
        $cards = json_decode(file_get_contents($filename));

        // filter
        $filters = $this->config['filters'];
        $filtered = filter($cards, $filters);

        if ($json = $input->getOption('raw')) {
            file_put_contents($json, json_encode($filtered, self::JSON_OPTIONS));
        }

        error_log(sprintf("Converted %d cards", count($filtered)));

        // convert
        $phonebook = $this->config['phonebook'];
        $conversions = $this->config['conversions'];
        $xml = export($phonebook['name'], $filtered, $conversions);

        echo $xml->asXML();
    }
}
