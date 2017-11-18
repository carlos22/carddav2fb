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
		// download
		$server = $this->config['server'];
		$xmlStr = DownloadCommand::load($server['url'], $server['user'], $server['password']);

		// parse and convert
		$xml = simplexml_load_string($xmlStr);
		$cards = ConvertCommand::parse($xml);

		$phonebook = $this->config['phonebook'];
		$xml = ConvertCommand::export($phonebook['name'], $cards, $this->config['conversions']);

		// upload
		$xmlStr = $xml->asXML();

		$fritzbox = $this->config['fritzbox'];
		UploadCommand::upload($xmlStr, $fritzbox['url'], $fritzbox['user'], $fritzbox['password'], $phonebook['id']);
	}
}
