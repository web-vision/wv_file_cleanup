<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'FAL File cleanup',
    'description' => 'Enables cleanup of unused fal records.',
    'category' => 'misc',
    'version' => '2.0.1',
    'state' => 'stable',
    'author' => 'web-vision Team',
    'author_email' => 'frans@beech.it, ricky@web-vision.de, riad@web-vision.de',
    'author_company' => 'beech.it, web-vision GmbH',
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.1-12.4.99',
        ],
    ],
];
