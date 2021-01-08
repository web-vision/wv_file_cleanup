<?php
defined('TYPO3_MODE') || die();

$boot = function () {
    $signalSlotDispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
        \TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class
    );
    $signalSlotDispatcher->connect(
        \TYPO3\CMS\Core\Resource\ResourceStorage::class,
        \TYPO3\CMS\Core\Resource\ResourceStorageInterface::SIGNAL_PostFileMove,
        \WebVision\WvFileCleanup\Slot\ResourceStorageSlots::class,
        'postFileMove'
    );
};
$boot();
unset($boot);
