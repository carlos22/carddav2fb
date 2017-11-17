<?php

namespace Andig;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Andig\CardDav\Backend;

class CardDavLoaderCommand extends Command {

	private $config;

	public function __construct($config) {
		$this->config = $config;
		parent::__construct();
	}

	protected function configure() {
		$this->setName('carddav')
			->setDescription('Load from CardDAV server');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
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
