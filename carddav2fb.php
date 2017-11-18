#!/usr/bin/env php
<?php

namespace Andig;

use Symfony\Component\Console\Application;

require_once('vendor/autoload.php');
require_once(__DIR__ . '/config.php');

$app = new Application('CardDAV to FritzBox converter');

$app->addCommands(array(
	new RunCommand($config),
	new DownloadCommand($config),
	new ConvertCommand($config),
	new UploadCommand($config)
));

$app->run();
