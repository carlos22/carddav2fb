<?php

namespace Andig;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Andig\FritzBox\Api;

class UploadCommand extends Command {

	use ConfigTrait;

	protected function configure() {
		$this->setName('upload')
			->setDescription('Upload to FritzBox')
			->addArgument('filename', InputArgument::REQUIRED, 'filename');

		$this->addConfig();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->loadConfig($input);

		$filename = $input->getArgument('filename');
		$xml = file_get_contents($filename);

		$fritzbox = $this->config['fritzbox'];
		$phonebook = $this->config['phonebook'];

		self::upload($xml, $fritzbox['url'], $fritzbox['user'], $fritzbox['password'], $phonebook['id'] ?? 0);

		error_log("Uploaded fritz phonebook");
	}

	public static function upload(string $xml, string $url, string $user, string $password, int $phonebook=0)
	{
		$fritz = new Api($url, $user, $password, 1);

		$formfields = array(
			'PhonebookId' => $phonebook
		);

		$filefields = array(
			'PhonebookImportFile' => array(
				'type' => 'text/xml',
				'filename' => 'updatepb.xml',
				'content' => $xml,
			)
		);

		$result = $fritz->doPostFile($formfields, $filefields); // send the command

		if (strpos($result, 'Das Telefonbuch der FRITZ!Box wurde wiederhergestellt') === false) {
			throw new \Exception('Upload failed');
		}
	}
}
