<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'FAL File cleanup',
    'description' => 'Enables cleanup of unused fal records.',
    'category' => 'misc',
    'version' => '1.2.0',
    'state' => 'stable',
    'author' => 'Frans Saris',
    'author_email' => 'frans@beech.it, ricky@web-vision.de, riad@web-vision.de',
    'author_company' => 'beech.it, web-vision GmbH',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.1-11.5.99',
        ],
    ],
];
