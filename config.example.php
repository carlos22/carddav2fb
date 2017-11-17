<?php

$config = [
	// local cards
	'file' => '',

	// or server
	'server' => [
		'url' => 'https://...',
		'user' => '',
		'password' => '',
	],

	// or fritzbox
	'fritzbox' => [
		'url' => 'http://fritz.box',
		'user' => '',
		'password' => '',
	],

	'conversions' => [
		'realName' => [
			'{lastname}, {firstname}',
			'{fullname}',
			'{organization}'
		],
		'phoneTypes' => [
			'WORK' => 'work', 
			'HOME' => 'home', 
			'CELL' => 'mobile'
		],
		'emailTypes' => [
			'WORK' => 'work', 
			'HOME' => 'home'
		]
	]
];
