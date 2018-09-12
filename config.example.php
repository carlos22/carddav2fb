<?php

$config = [
	// phonebook
	'phonebook' => [
		'id' => 0,
		'name' => 'Telefonbuch'
	],

	// or server
    'server' => [
        [
            'url' => 'https://...',
            'user' => '',
            'password' => '',
            // 'authentication' => 'digest' // uncomment for digest auth
        ],
/* add as many as you need
        [
            'url' => 'https://...',
            'user' => '',
            'password' => '',
        ],
*/
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
			'{lastname}, {prefix} {nickname}',
			'{lastname}, {prefix} {firstname}',
			'{lastname}, {nickname}',
			'{lastname}, {firstname}',
			'{organization}',
			'{fullname}'
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
			'+49' => '',  //Router steht default in DE; '0049' könnte auch Teil einer Rufnummer sein
            '('   => '',
			')'   => '',
			'/'   => '',
			'-'   => ' '
		]
	]
];
