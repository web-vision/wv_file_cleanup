# About

This extension should provide an easy way to remove unused files inside of TYPO3
using FAL.


# How to use

After installing ext:wv_file_cleanup a new BE module appears under "File".

Also the command controller ``file:cleanup`` will be available.

Example of using the command controller::

    ./typo3/cli_dispatch.phpsh extbase file:cleanup 1:/ --verbose

The command controller can be setup in the _scheduler_ as scheduler task.


## Options

You can configure an fileNameDenyPattern that holds a regular expression that is used to check
the filename against. If the pattern matches the file is excluded from the cleanup and also not
visible in het BE module.

Default value is ``/index.html/i`` so all ``index.html`` files are excluded and can be adjusted
in the extension configuration (see extension manager).

The value can also be overwritten in the command controller (and scheduler task).


## Examples

To only match `*.pdf` files you can set the fileNameDenyPattern to ``/^(?!.*\b.pdf\b)/``

Can be tested by executing this from CLI: 

    ./typo3/cli_dispatch.phpsh extbase file:cleanup 1:/ --verbose --dry-run --file-deny-pattern='/^(?!.*\b.pdf\b)/'