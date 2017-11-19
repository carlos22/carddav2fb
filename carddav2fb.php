#!/usr/bin/env php
<?php

namespace Andig;

use Symfony\Component\Console\Application;

require_once('vendor/autoload.php');

$app = new Application('CardDAV to FritzBox converter');

$app->addCommands(array(
	new RunCommand(),
	new DownloadCommand(),
	new ConvertCommand(),
	new UploadCommand()
));

$app->run();
