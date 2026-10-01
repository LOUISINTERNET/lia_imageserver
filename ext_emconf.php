<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'LIA Imageserver',
    'description' => 'Provides functions to resize images and embed responsive images.',
    'category' => 'misc',
    'author' => 'LOUIS TYPO3 Developers',
    'author_company' => 'LOUIS INTERNET',
    'author_email' => 'devs@louis.info',
    'state' => 'stable',
    'version' => '2.3.10',
    'constraints' => [
        // Single contiguous range — cannot express the v14.0-v14.2 gap that
        // composer.json excludes via "^13.4 || ^14.3". Composer is authoritative.
        'depends' => [
            'typo3' => '13.4.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
