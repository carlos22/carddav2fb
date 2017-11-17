<?php

namespace Andig;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use JeroenDesloovere\VCard\VCardParser;
use Andig\FritzBox\Converter;
use \SimpleXMLElement;

class VcardToFritzCommand extends Command {

	private $config;

	public function __construct($config) {
		$this->config = $config;
		parent::__construct();
	}

	protected function configure() {
		$this->setName('convert')
			->setDescription('Convert Vcard to FritzBox format')
			->addArgument('filename', InputArgument::REQUIRED, 'filename');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$filename = $input->getArgument('filename');
		$xml = simplexml_load_file($filename);

		// parse
		$cards = self::parse($xml);
		// file_put_contents('output.json', print_r($cards, 1));

		// convert
		$xml = self::export($this->config['phonebook'] ?? null, $cards, $this->config['conversions']);

		echo $xml->asXML();
	}

	public static function parse($xml)
	{
		$cards = [];
		$groups = [];

		// parse all vcards
		foreach ($xml->element as $element) {
			foreach ($element->vcard as $vcard) {
				$parser = new VCardParser($vcard);
				$card = $parser->getCardAtIndex(0);

				// separate iCloud groups
				if (isset($card->xabsmember)) {
					$groups[$card->fullname] = $card->xabsmember;
					continue;
				}
				
				$cards[] = $card;
				// print_r($card);
			}
		}

		// add category from group membership
		foreach ($cards as $card) {
			foreach ($groups as $key => $members) {
				if (in_array($card->uid, $members)) {
					$card->category = $key;
					// print_r($card);
					break;
				}
			}
		}

		return $cards;
	}

	// https://stackoverflow.com/questions/4778865/php-simplexml-addchild-with-another-simplexmlelement
	private static function xml_adopt(SimpleXMLElement $to, SimpleXMLElement $from) {
	    $toDom = dom_import_simplexml($to);
	    $fromDom = dom_import_simplexml($from);
	    $toDom->appendChild($toDom->ownerDocument->importNode($fromDom, true));
	}

	public static function export(string $phonebook=null, array $cards, $conversions)
	{
		$xml = new SimpleXMLElement(<<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<phonebooks>
	<phonebook />
</phonebooks>
EOT
		);

		$root = $xml->xpath('//phonebook')[0];
		if ($phonebook) {
			$root->addAttribute('name', $phonebook);
		}

		$converter = new Converter($conversions);

		foreach ($cards as $card) {
			$contact = $converter->convert($card);
			// $root->addChild('contact', $contact);
			self::xml_adopt($root, $contact);
		}

		return $xml;
	}
}
