<?php

namespace Andig;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;

trait ConfigTrait
{
    private $config;

    protected function addConfig()
    {
        $default = realpath(__DIR__ . '/../config.php');

        $this->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'config file', $default);
    }

    protected function loadConfig(InputInterface $input)
    {
        $configFile = $input->getOption('config');

        if (!file_exists($configFile)) {
            throw new \Exception('Config file ' . $configFile . ' does not exist');
        }

        require_once($configFile);
        $this->config = $config;
    }
}
