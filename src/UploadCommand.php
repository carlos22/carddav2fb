<?php

namespace Andig;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UploadCommand extends Command
{
    use ConfigTrait;

    protected function configure()
    {
        $this->setName('upload')
            ->setDescription('Upload to FritzBox')
            ->addArgument('filename', InputArgument::REQUIRED, 'filename');

        $this->addConfig();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->loadConfig($input);

        $filename = $input->getArgument('filename');
        $xml = file_get_contents($filename);

        $fritzbox = $this->config['fritzbox'];
        $phonebook = $this->config['phonebook'];

        upload($xml, $fritzbox['url'], $fritzbox['user'], $fritzbox['password'], $phonebook['id'] ?? 0);

        error_log("Uploaded fritz phonebook");
    }
}
