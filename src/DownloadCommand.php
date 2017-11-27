<?php

namespace Andig;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class DownloadCommand extends Command {

	use ConfigTrait;

	protected function configure() {
		$this->setName('download')
			->setDescription('Load from CardDAV server');

		$this->addConfig();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->loadConfig($input);

		$progress = new ProgressBar($output);
		$progress->start();

		$server = $this->config['server'];
		$xmlStr = download($server['url'], $server['user'], $server['password'], function() use ($progress) {
			$progress->advance();
		});

		$progress->finish();

		$count = countCards($xmlStr);
		error_log(sprintf("\nDownloaded %d vcards", $count));

		echo $xmlStr;
	}
}
