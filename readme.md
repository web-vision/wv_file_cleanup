# About

This extension should provide an easy way to remove unused files inside of TYPO3
using FAL.

# How to use

After installing ext:wv_file_cleanup a new BE module appears under "File". 

Also the command controller ``file:cleanup`` will be available.

Example of using the command controller::

    ./typo3/cli_dispatch.phpsh extbase file:cleanup 1:/ --verbose
    
The command controller can be setup in the _scheduler_ as scheduler task.
