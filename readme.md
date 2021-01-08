# About

This extension should provide an easy way to remove unused files in the TYPO3 FAL.


# How to use

After installing ext:wv_file_cleanup a new BE module appears under "File".

Also the symfony commands ``wv_file_cleanup:cleanup`` and ``wv_file_cleanup:emptyrecycler`` will be available.

Example of using the command controllers::

    ./typo3/sysext/core/bin/typo3 wv_file_cleanup:cleanup 1:/ --verbose
    ./typo3/sysext/core/bin/typo3 wv_file_cleanup:emptyrecycler 1:/ --verbose

It is recommended to use the commands in a CLI context, but they can also be setup in the _scheduler_
as scheduler tasks. 

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

    ./typo3/sysext/core/bin/typo3 wv_file_cleanup:cleanup 1:/ --verbose --dry-run --file-deny-pattern='/^(?!.*\b.pdf\b)/'