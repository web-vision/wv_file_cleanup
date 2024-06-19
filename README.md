# About

German:

Die TYPO3 Extension “FAL File Cleanup” bietet eine einfache Möglichkeit, unbenutzte Dateien im TYPO3 System zu identifizieren und automatisch aufzuräumen.
Ein TYPO3 Scheduler Task führt dabei regelmäßig Prüfungen im System durch, und gleich die verlinkten Dateien (Dateireferenzen) mit den vorhandenen Dateien im Fileadmin von TYPO3 ab. Auf Wunsch können diese regelmäßigen Prüfungen auch auf bestimmte Datei-Endungen beschränkt werden.
Sind Dateien identifiziert, welche keine Verlinkung in Inhalten des TYPO3 System besitzen, können diese wahlweise direkt gelöscht, zuerst in einen Papierkorb verschoben und nach definierbaren Intervallen (z.B. nach 30 Tagen) verspätet gelöscht werden.

---
English:

The TYPO3 extension "FAL File Cleanup" offers an easy way to identify and automatically clean up unused files in the TYPO3 system.
A TYPO3 scheduler task carries out regular checks in the system and compares the linked files (file references) with the existing files in the TYPO3 file admin. If desired, these regular checks can also be limited to certain file endings.
If files are identified which do not have a link in the TYPO3 system content, they can be deleted directly, moved to a recycle bin first and deleted after definable intervals (e.g. after 30 days).


# How to use

After installing ext:wv_file_cleanup a new BE module appears under "File".

Also, the symfony commands ``wv_file_cleanup:cleanup`` and
``wv_file_cleanup:emptyrecycler`` will be available.

Example of using the command controllers::

    ./typo3/sysext/core/bin/typo3 wv_file_cleanup:cleanup 1:/ -r --verbose
    ./typo3/sysext/core/bin/typo3 wv_file_cleanup:emptyrecycler 1:/ -a 1month --verbose

It is recommended to use the commands in a CLI context, but they can also be
setup in the _scheduler_ as scheduler tasks.

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
