<?php

namespace Andig;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends Command {

	use ConfigTrait;

	protected function configure() {
		$this->setName('run')
			->setDescription('Download, convert and upload - all in one');

		$this->addConfig();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->loadConfig($input);

		// download
		$server = $this->config['server'];
		$xmlStr = DownloadCommand::load($server['url'], $server['user'], $server['password']);

		$count = DownloadCommand::countCards($xmlStr);
		error_log(sprintf("\nDownloaded %d vcards", $count));

		// parse and convert
		$phonebook = $this->config['phonebook'];
		$conversions = $this->config['conversions'];

		$xml = simplexml_load_string($xmlStr);
		$cards = ConvertCommand::parse($xml, $conversions);
		error_log(sprintf("Converted %d vcards", count($cards)));

		$xml = ConvertCommand::export($phonebook['name'], $cards, $conversions);
		// error_log(sprintf("Exported fritz phonebook", count($cards)));

		// upload
		$xmlStr = $xml->asXML();

		$fritzbox = $this->config['fritzbox'];
		UploadCommand::upload($xmlStr, $fritzbox['url'], $fritzbox['user'], $fritzbox['password'], $phonebook['id']);

		error_log("Uploaded fritz phonebook");
	}
}
