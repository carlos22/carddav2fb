<?php

$config = [
    // phonebook
    'phonebook' => [
        'id'        => 0,                                              // only "0" can store quickdial and vanity numbers
        'name'      => 'Telefonbuch',
        'imagepath' => 'file:///var/InternerSpeicher/[YOURUSBSTICK]/FRITZ/fonpix/', // mandatory if you use the -i option
    ],

    // or server
    'server' => [
        [
            'url' => 'https://...',
            'user' => '',
            'password' => '',
            'http' => [           // http client options are directly passed to Guzzle http client
                // 'verify' => false, // uncomment to disable certificate check
                // 'auth' => 'digest', // uncomment for digest auth
            ]
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
        'fonpix'   => '/[YOURUSBSTICK]/FRITZ/fonpix',   // the storage on your usb stick for uploading images
        'http' => [           // http client options are directly passed to Guzzle http client
            // 'verify' => false, // uncomment to disable certificate check
        ]
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
        /**
         * 'realName' conversions are processed consecutively. Order decides!
         */
        'realName' => [
            '{lastname}, {prefix} {nickname}',
            '{lastname}, {prefix} {firstname}',
            '{lastname}, {nickname}',
            '{lastname}, {firstname}',
            '{organization}',
            '{fullname}'
        ],
        /**
         * 'phoneTypes':
         * The order of the target values (first occurrence) determines the sorting of the telephone numbers
         */
        'phoneTypes' => [
            'WORK' => 'work',
            'HOME' => 'home',
            'CELL' => 'mobile'
        ],
        'emailTypes' => [
            'WORK' => 'work',
            'HOME' => 'home'
        ],
        /**
         * 'phoneReplaceCharacters' conversions are processed consecutively. Order decides!
         */
        'phoneReplaceCharacters' => [
            '+49' => '',  // router is usually operated in 'DE; '0049' could also be part of a phone number
            '('   => '',
            ')'   => '',
            '/'   => '',
            '-'   => ' '
        ]
    ]
];
