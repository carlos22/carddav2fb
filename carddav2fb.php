<?php

namespace Andig;

use Symfony\Component\Console\Application;

require_once('vendor/autoload.php');
require_once(__DIR__ . '/config.php');

$app = new Application('CardDAV to FritzBox converter');

$app->addCommands(array(
	new CardDAVLoaderCommand($config),
	new VcardToFritzCommand($config),
	new UploadToFritzCommand($config),
	new RunCommand($config)
));

$app->run();