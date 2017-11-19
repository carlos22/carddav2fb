<?php

namespace Andig;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Andig\CardDav\Backend;

class DownloadCommand extends Command {

	use ConfigTrait;

	protected function configure() {
		$this->setName('carddav')
			->setDescription('Load from CardDAV server');

		$this->addConfig();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->loadConfig($input);

		$server = $this->config['server'];
		$xml = self::load($server['url'], $server['user'], $server['password']);

		echo $xml;
	}

	public static function load($url, $user, $password) {
		$backend = new Backend($url);
		$backend->setAuth($user, $password);
		return $backend->get();
	}
}
