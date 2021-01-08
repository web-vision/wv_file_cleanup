<?php

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

$EM_CONF[$_EXTKEY] = [
    'title' => 'FAL File cleanup',
    'description' => 'Enables cleanup of unused fal records.',
    'category' => 'misc',
    'version' => '2.0.0',
    'state' => 'stable',
    'author' => 'Frans Saris',
    'author_email' => 'frans@beech.it,ricky@web-vision.de',
    'author_company' => 'beech.it, web-vision GmbH',
    'constraints' => [
        'depends' => [
            'typo3' => '9.5.1-10.4.99',
        ],
    ],
];
