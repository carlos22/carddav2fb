<?php

namespace Andig;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class RunCommand extends Command
{
    use ConfigTrait;

    protected function configure()
    {
        $this->setName('run')
            ->setDescription('Download, convert and upload - all in one')
            ->addOption('image', 'i', InputOption::VALUE_NONE, 'download images');

        $this->addConfig();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->loadConfig($input);

        $vcards = array();
        $xcards = array();
        $substitutes = ($input->getOption('image')) ? ['PHOTO'] : [];
        
        foreach($this->config['server'] as $server) {
            $progress = new ProgressBar($output);
            error_log("Downloading vCard(s) from account ".$server['user']);
            
            $backend = backendProvider($server);
            $progress->start();
            $xcards = download ($backend, $substitutes, function () use ($progress) {
                $progress->advance();
            });
            $progress->finish();
            $vcards = array_merge($vcards, $xcards);
            $quantity = count($vcards);
            error_log(sprintf("\nDownloaded %d vCard(s)", $quantity));
        }

        // dissolve
        error_log("Dissolving groups (e.g. iCloud)");
        $cards = dissolveGroups($vcards);
        $remain = count($cards);
        error_log(sprintf("Dissolved %d group(s)", $quantity - $remain));
                
        // filter
        error_log(sprintf("Filtering %d vCard(s)", $remain));
        $filters = $this->config['filters'];
        $filtered = filter($cards, $filters);
        error_log(sprintf("Filtered out %d vCard(s)", $remain - count($filtered)));
        
        // image upload
        if ($input->getOption('image')) {
            error_log("Detaching and uploading image(s)");
            $pictures = uploadImages($filtered, $this->config['fritzbox']);
            error_log(sprintf("Uploaded %d image file(s)", $pictures)); 
        }
        else {
            unset($this->config['phonebook']['imagepath']);             // otherwise convert will set wrong links
        }
                
        // fritzbox format
        $xml = export($filtered, $this->config);
        error_log(sprintf("Converted %d vCard(s)", count($filtered)));

        // upload
        error_log("Uploading");

        $xmlStr = $xml->asXML();

        upload($xmlStr, $this->config);
        error_log("Successful uploaded new Fritz!Box phonebook");
    }
}