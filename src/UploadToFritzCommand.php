<?php

namespace Andig;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Andig\FritzBox\Api;

class UploadToFritzCommand extends Command {

	private $config;

	public function __construct($config) {
		$this->config = $config;
		parent::__construct();
	}

	protected function configure() {
		$this->setName('upload')
			->setDescription('Upload to FritzBox')
			->addArgument('filename', InputArgument::REQUIRED, 'filename');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$filename = $input->getArgument('filename');
		$xml = file_get_contents($filename);

		$fritzbox = $this->config['fritzbox'];
		self::upload($xml, $fritzbox['url'], $fritzbox['user'], $fritzbox['password']);
	}

	public static function upload($xml, $url, $user, $password)
	{
		$fritz = new Api($url, $user, $password, 1);

		$formfields = array(
			'PhonebookId' => 0
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
