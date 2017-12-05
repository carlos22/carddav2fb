<?php

$config = [
	// phonebook
	'phonebook' => [
		'id' => 0,
		'name' => 'Telefonbuch'
	],

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

    'filters' => [
        'include' => [
            // if empty include all by default
        ],

        'exclude' => [
            'category' => [
                'a', 'b'
            ],
            'group' => [
                'c', 'd'
            ],
        ],
    ],

	'conversions' => [
		'vip' => [
			'category' => [
				'vip1'
			],
			'group' => [
				'PERS'
			],
		],
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
		],
		'phoneReplaceCharacters' => [
			'(' => '',
			')' => '',
			'/' => '',
			'-' => ' '
		]
	]
];
