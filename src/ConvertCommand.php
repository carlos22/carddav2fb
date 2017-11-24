<?php

namespace Andig;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConvertCommand extends Command {

	use ConfigTrait;

	protected function configure() {
		$this->setName('convert')
			->setDescription('Convert Vcard to FritzBox format')
			->addOption('json', 'j', InputOption::VALUE_REQUIRED, 'export parse result to json file')
			->addArgument('filename', InputArgument::REQUIRED, 'filename');

		$this->addConfig();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->loadConfig($input);
		
		$filename = $input->getArgument('filename');
		$xml = simplexml_load_file($filename);

		$conversions = $this->config['conversions'];
		$excludes = $this->config['excludes'];
		$phonebook = $this->config['phonebook'];

		// parse
		$cards = parse($xml, $conversions);
		$filtered = filter($cards, $excludes);

		if ($json = $input->getOption('json')) {
			file_put_contents($json, json_encode($filtered, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
		}

		error_log(sprintf("Converted %d vcards", count($filtered)));

		// convert
		$xml = export($phonebook['name'], $filtered, $conversions);

		echo $xml->asXML();
	}
}
