<?php

return [
    // required import configurations of other extensions,
    // in case a module imports from another package
    'dependencies' => [
        'backend',
        'core',
    ],
    'tags' => [
        'backend.module',
    ],
    'imports' => [
        // recursive definition, all *.js files in this folder are import-mapped
        // trailing slash is required per importmap-specification
        '@typo3/backend/' => 'EXT:backend/Resources/Public/JavaScript/',
        '@WebVision/WvFileCleanup/' => 'EXT:wv_file_cleanup/Resources/Public/JavaScript/',
    ]
];
