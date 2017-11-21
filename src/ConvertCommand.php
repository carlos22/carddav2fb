<?php

namespace Andig;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Andig\Vcard\Parser;
use Andig\FritzBox\Converter;
use \SimpleXMLElement;

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
		$phonebook = $this->config['phonebook'];

		// parse
		$cards = self::parse($xml, $conversions);

		if ($json = $input->getOption('json')) {
			file_put_contents($json, json_encode($cards, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
		}

		error_log(sprintf("Converted %d vcards", count($cards)));

		// convert
		$xml = self::export($phonebook['name'], $cards, $conversions);

		echo $xml->asXML();
	}

	public static function parse(SimpleXMLElement $xml, array $conversions)
	{
		$cards = [];
		$groups = [];

		// parse all vcards
		foreach ($xml->element as $element) {
			foreach ($element->vcard as $vcard) {
				$parser = new Parser($vcard);
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

		$groupConversions = $conversions['groupNameToCategory'] ?? null;

		if ($groupConversions) {
			// add category from group membership
			foreach ($cards as $card) {
				foreach ($groups as $key => $members) {
					$category = $groupConversions[$key] ?? null;

					if ($category && in_array($card->uid, $members)) {
						$card->category = $category;
						break;
					}
				}
			}
		}

		$excludeCategories = $conversions['excludeCategories'] ?? null;
		if ($excludeCategories) {
			$cards = array_filter($cards, function($card) use ($excludeCategories) {
				// filter excluded categories
				if (isset($card->category)) {
					return !in_array($card->category, $excludeCategories);
				}

				return true;
			});
		}

		return $cards;
	}

	public static function export(string $name='Telefonbuch', array $cards, array $conversions)
	{
		$xml = new SimpleXMLElement(<<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<phonebooks>
	<phonebook />
</phonebooks>
EOT
		);

		$root = $xml->xpath('//phonebook')[0];
		$root->addAttribute('name', $name);

		$converter = new Converter($conversions);

		foreach ($cards as $card) {
			$contact = $converter->convert($card);
			// $root->addChild('contact', $contact);
			self::xml_adopt($root, $contact);
		}

		return $xml;
	}

	// https://stackoverflow.com/questions/4778865/php-simplexml-addchild-with-another-simplexmlelement
	private static function xml_adopt(SimpleXMLElement $to, SimpleXMLElement $from) {
	    $toDom = dom_import_simplexml($to);
	    $fromDom = dom_import_simplexml($from);
	    $toDom->appendChild($toDom->ownerDocument->importNode($fromDom, true));
	}
}
