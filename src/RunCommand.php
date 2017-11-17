<?php

namespace Andig;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends Command {

	private $config;

	public function __construct($config) {
		$this->config = $config;
		parent::__construct();
	}

	protected function configure() {
		$this->setName('run')
			->setDescription('Download, convert and upload - all in one');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$server = $this->config['server'];
		$xml = CardDavLoaderCommand::load($server['url'], $server['user'], $server['password']);

		// parse and convert
		$cards = VcardToFritzCommand::parse($xml);
		$xml = VcardToFritzCommand::export($this->config['phonebook'] ?? null, $cards, $this->config['conversions']);

		$fritzbox = $this->config['fritzbox'];
		UploadToFritzCommand::upload($xml, $fritzbox['url'], $fritzbox['user'], $fritzbox['password']);
	}
}
