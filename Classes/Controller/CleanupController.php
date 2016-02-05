<?php
namespace WebVision\WvFileCleanup\Controller;

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
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\Exception;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use WebVision\WvFileCleanup\Domain\Repository\FileRepository;

/**
 * Class CleanupController
 *
 * @author Frans Saris <t3ext@beech.it>
 */
class CleanupController extends ActionController
{
    /**
     * @var Folder
     */
    protected $folder;

    /**
     * @var array
     */
    public $MOD_MENU = array();

    /**
     * @var array
     */
    public $MOD_SETTINGS = [];

    /**
     * @var BackendTemplateView
     */
    protected $view;

    /**
     * BackendTemplateView Container
     *
     * @var BackendTemplateView
     */
    protected $defaultViewObjectName = BackendTemplateView::class;

    /**
     * @var FileRepository
     */
    protected $fileRepository;

    /**
     * @param FileRepository $fileRepository
     */
    public function injectFileRepository(FileRepository $fileRepository)
    {
        $this->fileRepository = $fileRepository;
    }

    /**
     * Initialize the view
     *
     * @param ViewInterface $view The view
     *
     * @return void
     */
    public function initializeView(ViewInterface $view)
    {
        /** @var BackendTemplateView $view **/
        parent::initializeView($view);
        if ($view instanceof BackendTemplateView) {
            $pageRenderer = $this->view->getModuleTemplate()->getPageRenderer();
            $pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/ClickMenu');
            $pageRenderer->loadRequireJsModule('TYPO3/CMS/WvFileCleanup/Cleanup');
            $pageRenderer->addJsInlineCode(
                'FileCleanup',
                'function jumpToUrl(URL) {
                    window.location.href = URL;
                    return false;
                }'
            );

            $this->registerDocHeaderButtons();

            $pageRecord = [
                'combined_identifier' => $this->folder->getCombinedIdentifier(),
            ];
            $this->view->getModuleTemplate()->getDocHeaderComponent()->setMetaInformation($pageRecord);
        }
    }

    /**
     * Initialize variables, file object
     * Incoming GET vars include id, pointer, table, imagemode
     *
     * @return void
     */
    public function initializeObject()
    {
        $this->getLanguageService()->includeLLFile('EXT:lang/locallang_mod_file_list.xlf');
        $this->getLanguageService()->includeLLFile('EXT:lang/locallang_misc.xlf');

        // GPvars:
        $combinedIdentifier = GeneralUtility::_GP('id');

        try {
            if ($combinedIdentifier) {
                /** @var $resourceFactory ResourceFactory **/
                $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
                $storage = $resourceFactory->getStorageObjectFromCombinedIdentifier($combinedIdentifier);
                $identifier = substr($combinedIdentifier, strpos($combinedIdentifier, ':') + 1);
                if (!$storage->hasFolder($identifier)) {
                    $identifier = $storage->getFolderIdentifierFromFileIdentifier($identifier);
                }

                $this->folder = $resourceFactory->getFolderObjectFromCombinedIdentifier(
                    $storage->getUid() . ':' . $identifier
                );
                // Disallow access to fallback storage 0
                if ($storage->getUid() === 0) {
                    throw new Exception\InsufficientFolderAccessPermissionsException(
                        'You are not allowed to access files outside your storages',
                        1453971238
                    );
                }
                // Disallow the rendering of the processing folder (e.g. could be called manually)
                if ($this->folder && $storage->isProcessingFolder($this->folder)) {
                    $this->folder = $storage->getRootLevelFolder();
                }
            } else {
                // Take the first object of the first storage
                $fileStorages = $this->getBackendUser()->getFileStorages();
                $fileStorage = reset($fileStorages);
                if ($fileStorage) {
                    $this->folder = $fileStorage->getRootLevelFolder();
                } else {
                    throw new \RuntimeException('Could not find any folder to be displayed.', 1453971239);
                }
            }

            if ($this->folder &&
                !$this->folder->getStorage()->isWithinFileMountBoundaries($this->folder)
            ) {
                throw new \RuntimeException('Folder not accessible.', 1453971240);
            }
        } catch (Exception\InsufficientFolderAccessPermissionsException $permissionException) {
            $this->folder = null;
            $this->addFlashMessage(
                sprintf(
                    $this->getLanguageService()->getLL('missingFolderPermissionsMessage', true),
                    htmlspecialchars($combinedIdentifier)
                ),
                $this->getLanguageService()->getLL('missingFolderPermissionsTitle', true),
                FlashMessage::NOTICE
            );
        } catch (Exception $fileException) {
            // Set folder object to null and throw a message later on
            $this->folder = null;
            // Take the first object of the first storage
            $fileStorages = $this->getBackendUser()->getFileStorages();
            $fileStorage = reset($fileStorages);
            if ($fileStorage instanceof ResourceStorage) {
                $this->folder = $fileStorage->getRootLevelFolder();
                if (!$fileStorage->isWithinFileMountBoundaries($this->folder)) {
                    $this->folder = null;
                }
            }
            $this->addFlashMessage(
                sprintf(
                    $this->getLanguageService()->getLL('folderNotFoundMessage', true),
                    htmlspecialchars($combinedIdentifier)
                ),
                $this->getLanguageService()->getLL('folderNotFoundTitle', true),
                FlashMessage::NOTICE
            );
        } catch (\RuntimeException $e) {
            $this->folder = null;
            $this->addFlashMessage(
                $e->getMessage() . ' (' . $e->getCode() . ')',
                $this->getLanguageService()->getLL('folderNotFoundTitle', true),
                FlashMessage::NOTICE
            );
        }

        if ($this->folder &&
            !$this->folder->getStorage()->checkFolderActionPermission('read', $this->folder)
        ) {
            $this->folder = null;
        }
        // Configure the "menu" - which is used internally to save the values of sorting, displayThumbs etc.
        $this->menuConfig();
    }

    /**
     * Setting the menu/session variables
     *
     * @return void
     */
    public function menuConfig()
    {
        // MENU-ITEMS:
        // If array, then it's a selector box menu
        // If empty string it's just a variable, that will be saved.
        // Values NOT in this array will not be saved in the settings-array for the module.
        $this->MOD_MENU = array(
            'displayThumbs' => '',
        );

        // CLEANSE SETTINGS
        $this->MOD_SETTINGS = BackendUtility::getModuleData(
            $this->MOD_MENU,
            GeneralUtility::_GP('SET'),
            'file_WvFileCleanupCleanup'
        );
    }

    /**
     * Initialize indexAction
     *
     * @return void
     */
    protected function initializeIndexAction()
    {
        $backendUser = $this->getBackendUser();
        // Set predefined value for DisplayThumbnails:
        if ($backendUser->getTSConfigVal('options.file_list.enableDisplayThumbnails') === 'activated') {
            $this->MOD_SETTINGS['displayThumbs'] = true;
        } elseif ($backendUser->getTSConfigVal('options.file_list.enableDisplayThumbnails') === 'deactivated') {
            $this->MOD_SETTINGS['displayThumbs'] = false;
        }
        // If user never opened the list module, set the value for displayThumbs
        if (!isset($this->MOD_SETTINGS['displayThumbs'])) {
            $this->MOD_SETTINGS['displayThumbs'] = $backendUser->uc['thumbnailsByDefault'];
        }
    }

    /**
     * Register doc header buttons
     *
     * @return void
     */
    protected function registerDocHeaderButtons()
    {
        /** @var ButtonBar $buttonBar **/
        $buttonBar = $this->view->getModuleTemplate()->getDocHeaderComponent()->getButtonBar();

        /** @var IconFactory $iconFactory **/
        $iconFactory = $this->view->getModuleTemplate()->getIconFactory();

        $lang = $this->getLanguageService();

        // Refresh page
        $refreshLink = GeneralUtility::linkThisScript(
            array(
                'target' => rawurlencode($this->folder->getCombinedIdentifier())
            )
        );
        $refreshButton = $buttonBar->makeLinkButton()
            ->setHref($refreshLink)
            ->setTitle($lang->sL('LLL:EXT:lang/locallang_core.xlf:labels.reload'))
            ->setIcon($iconFactory->getIcon('actions-refresh', Icon::SIZE_SMALL));
        $buttonBar->addButton($refreshButton, ButtonBar::BUTTON_POSITION_RIGHT);

        // Level up
        try {
            $currentStorage = $this->folder->getStorage();
            $parentFolder = $this->folder->getParentFolder();
            if ($parentFolder->getIdentifier() !== $this->folder->getIdentifier()
                && $currentStorage->isWithinFileMountBoundaries($parentFolder)
            ) {
                $levelUpClick = 'top.document.getElementsByName("navigation")[0].';
                $levelUpClick .= 'contentWindow.Tree.highlightActiveItem("file","folder';
                $levelUpClick .= GeneralUtility::md5int($parentFolder->getCombinedIdentifier());
                $levelUpClick .= '_"+top.fsMod.currentBank)';
                $levelUpButton = $buttonBar->makeLinkButton()
                    ->setHref(
                        BackendUtility::getModuleUrl(
                            'file_WvFileCleanupCleanup',
                            ['id' => $parentFolder->getCombinedIdentifier()]
                        )
                    )
                    ->setOnClick($levelUpClick)
                    ->setTitle($lang->sL('LLL:EXT:lang/locallang_core.xlf:labels.upOneLevel'))
                    ->setIcon($iconFactory->getIcon('actions-view-go-up', Icon::SIZE_SMALL));
                $buttonBar->addButton($levelUpButton, ButtonBar::BUTTON_POSITION_LEFT, 1);
            }
        } catch (\Exception $e) {
            // Silent ignore exceptions
        }

        // Shortcut
        if ($this->getBackendUser()->mayMakeShortcut()) {
            $shortCutButton = $buttonBar->makeShortcutButton()->setModuleName('file_WvFileCleanupCleanup');
            $buttonBar->addButton($shortCutButton, ButtonBar::BUTTON_POSITION_RIGHT);
        }
    }

    /**
     * Index action
     *
     * @return void
     */
    public function indexAction()
    {
        $this->view->assign('files', $this->fileRepository->findUnusedFile($this->folder));
        $this->view->assign('folder', $this->folder);

        $this->view->assign('checkboxes', [
            'displayThumbs' => [
                'enabled' => $GLOBALS['TYPO3_CONF_VARS']['GFX']['thumbnails']
                    && $this->getBackendUser()->getTSConfigVal('options.file_list.enableDisplayThumbnails') === 'selectable',
                'label' => $this->getLanguageService()->getLL('displayThumbs', true),
                'html' => BackendUtility::getFuncCheck(
                    $this->folder ? $this->folder->getCombinedIdentifier() : '',
                    'SET[displayThumbs]',
                    $this->MOD_SETTINGS['displayThumbs'],
                    '',
                    '',
                    'id="checkDisplayThumbs"'
                ),
                'checked' => $this->MOD_SETTINGS['displayThumbs'],
            ],
        ]);
    }

    /**
     * Cleanup files
     *
     * @param array $files
     *
     * @return void
     */
    public function cleanupAction(array $files)
    {
        /** @var $resourceFactory ResourceFactory **/
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $movedFilesCount = 0;

        foreach ($files as $fileUid) {
            try {
                $file = $resourceFactory->getFileObject($fileUid);
                $folder = $file->getParentFolder();
                try {
                    if (!$folder->hasFolder('_recycler_')) {
                        $recycler = $folder->getStorage()->createFolder('_recycler_', $folder);
                    } else {
                        $recycler = $folder->getSubfolder('_recycler_');
                    }
                    $file->moveTo($recycler);
                    $movedFilesCount++;
                } catch (Exception\InsufficientFolderWritePermissionsException $e) {
                    $this->addFlashMessage(
                        'You are not allowed to create a _recycler_ folder in ' . $folder->getReadablePath(),
                        '',
                        FlashMessage::ERROR,
                        true
                    );
                }
            } catch (Exception\FileDoesNotExistException $e) {
                // If given doesn't exist we silently ignore the file
            }
        }

        if ($movedFilesCount) {
            $this->addFlashMessage(
                str_replace('%d', $movedFilesCount, 'Moved %d files to recycler'),
                '',
                FlashMessage::OK,
                true
            );
        } else {
            $this->addFlashMessage(
                'No files moved',
                '',
                FlashMessage::WARNING,
                true
            );
        }

        $this->redirect('index');
    }

    /**
     * Creates a Message object and adds it to the FlashMessageQueue.
     *
     * @param string $messageBody The message
     * @param string $messageTitle Optional message title
     * @param int $severity Optional severity, must be one of \TYPO3\CMS\Core\Messaging\FlashMessage constants
     * @param bool $storeInSession Optional, defines whether the message should be stored in the session
     *
     * @return void
     * @throws \InvalidArgumentException When the message body is no string
     */
    public function addFlashMessage(
        $messageBody,
        $messageTitle = '',
        $severity = \TYPO3\CMS\Core\Messaging\AbstractMessage::OK,
        $storeInSession = true
    ) {
        if (!is_string($messageBody)) {
            throw new \InvalidArgumentException(
                'The message body must be of type string, "' . gettype($messageBody) . '" given.',
                1243258395
            );
        }
        /** @var \TYPO3\CMS\Core\Messaging\FlashMessage $flashMessage **/
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $messageBody,
            $messageTitle,
            $severity,
            $storeInSession
        );
        $this->controllerContext->getFlashMessageQueue('core.template.flashMessages')->enqueue($flashMessage);
    }

    /**
     * Returns an instance of LanguageService
     *
     * @return \TYPO3\CMS\Lang\LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }

    /**
     * Returns the current BE user.
     *
     * @return \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
     */
    protected function getBackendUser()
    {
        return $GLOBALS['BE_USER'];
    }
}
