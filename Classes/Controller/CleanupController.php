<?php

namespace WebVision\WvFileCleanup\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Module\ModuleData;
use TYPO3\CMS\Backend\Routing\UriBuilder as BackendUriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\Buttons\LinkButton;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\Exception;
use TYPO3\CMS\Core\Resource\Exception\ExistingTargetFolderException;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use \TYPO3Fluid\Fluid\View\ViewInterface;
use WebVision\WvFileCleanup\Domain\Repository\FileRepository;

/**
 * Class CleanupController
 */
class CleanupController extends ActionController
{
    protected ?Folder $folder;

    public array $moduleSettings = [];

    protected ModuleTemplate $moduleTemplate;

    protected PageRenderer $pageRenderer;

    protected ModuleTemplateFactory $moduleTemplateFactory;

    protected FileRepository $fileRepository;

    protected BackendUriBuilder $backendUriBuilder;

    /**
     * @var StorageRepository
     */
    protected $storageRepository;

    protected IconFactory $iconFactory;

    protected ?ModuleData $moduleData = null;

    protected string $moduleIdentifier = '';

    public function __construct(
        FileRepository $fileRepository,
        PageRenderer $pageRenderer,
        ModuleTemplateFactory $moduleTemplateFactory,
        IconFactory $iconFactory,
        StorageRepository $storageRepository,
        BackendUriBuilder $backendUriBuilder
    ) {
        $this->fileRepository = $fileRepository;
        $this->pageRenderer = $pageRenderer;
        $this->moduleTemplateFactory = $moduleTemplateFactory;
        $this->iconFactory = $iconFactory;
        $this->storageRepository = $storageRepository;
        $this->backendUriBuilder = $backendUriBuilder;
    }

    public function initializeAction(): void
    {
        $this->moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $this->moduleData = $this->request->getAttribute('moduleData');
        $this->moduleIdentifier = $this->moduleData->getModuleIdentifier();
    }

    public function initializeView(ViewInterface $view): void
    {

//        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/ContextMenu');
//        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/WvFileCleanup/Cleanup');
        // Load JavaScript via PageRenderer
        $this->pageRenderer->loadJavaScriptModule('@typo3/backend/context-menu.js');
        $this->pageRenderer->loadJavaScriptModule('@WebVision/WvFileCleanup/Cleanup.js');
//        $this->pageRenderer->addJsInlineCode(
//            'FileCleanup',
//            'function jumpToUrl(URL) {
//                        window.location.href = URL;
//                        return false;
//                    }'
//        );
        $this->registerDocHeaderButtons();

        $pageRecord = [
            'combined_identifier' => $this->folder->getCombinedIdentifier(),
        ];

//        $arguments = $this->request->getArguments();

        $this->moduleTemplate->getDocHeaderComponent()->setMetaInformation($pageRecord);
    }

    /**
     * @throws \TYPO3\CMS\Core\Exception
     */
    public function initializeObject(): void
    {
        $langResourcePath = 'EXT:core/Resources/Private/Language/';
        $fileListResourcePath = 'EXT:filelist/Resources/Private/Language/';
        $this->getLanguageService()->includeLLFile($langResourcePath . 'locallang_core.xlf');
        $this->getLanguageService()->includeLLFile($langResourcePath . 'locallang_misc.xlf');
        $this->getLanguageService()->includeLLFile($fileListResourcePath . 'locallang_mod_file_list.xlf');
        $this->getLanguageService()->includeLLFile('EXT:wv_file_cleanup/Resources/Private/Language/locallang_mod_cleanup.xlf');

        // GPvars
        //$combinedIdentifier = GeneralUtility::_GP('id');
        $this->request = $this->getExtbaseRequest();
        $combinedIdentifier = ($this->request->getParsedBody()['id'] ?? $this->request->getQueryParams()['id'] ?? null);

        try {
            if ($combinedIdentifier) {
                /** @var $resourceFactory ResourceFactory **/
                $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
                //$storage = $resourceFactory->getStorageObjectFromCombinedIdentifier($combinedIdentifier);
                [$storageId, $objectIdentifier] = array_pad(GeneralUtility::trimExplode(':', $combinedIdentifier), 2, null);
                if (!MathUtility::canBeInterpretedAsInteger($storageId) && $objectIdentifier === null) {
                    $storage =  $this->storageRepository->getDefaultStorage();
                } else {
                    $storage = $this->storageRepository->findByUid((int)$storageId);
                }

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
                if ($storage->isProcessingFolder($this->folder)) {
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

            if (!$this->folder->getStorage()->isWithinFileMountBoundaries($this->folder)) {
                throw new \RuntimeException('Folder not accessible.', 1453971240);
            }
        } catch (Exception\InsufficientFolderAccessPermissionsException $permissionException) {
            $this->folder = null;
            $this->addFlashMessage(
                sprintf(
                    $this->getLanguageService()->sL('missingFolderPermissionsMessage'),
                    htmlspecialchars($combinedIdentifier)
                ),
                $this->getLanguageService()->sL('missingFolderPermissionsTitle'),
                \TYPO3\CMS\Core\Type\ContextualFeedbackSeverity::NOTICE
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
                    $this->getLanguageService()->sL('folderNotFoundMessage'),
                    htmlspecialchars($combinedIdentifier)
                ),
                $this->getLanguageService()->sL('folderNotFoundTitle'),
                \TYPO3\CMS\Core\Type\ContextualFeedbackSeverity::NOTICE
            );
        } catch (\RuntimeException $e) {
            $this->folder = null;
            $this->addFlashMessage(
                $e->getMessage() . ' (' . $e->getCode() . ')',
                $this->getLanguageService()->sL('folderNotFoundTitle'),
                \TYPO3\CMS\Core\Type\ContextualFeedbackSeverity::NOTICE
            );
        }

        if (
            $this->folder &&
            !$this->folder->getStorage()->checkFolderActionPermission('read', $this->folder)
        ) {
            $this->folder = null;
        }
        // Configure the "options" - which is used internally to save the values of sorting, displayThumbs etc.
        $this->optionsConfig();
    }

    /**
     * Setting the options/session variables
     */
    protected function optionsConfig(): void
    {

        $this->moduleSettings = BackendUtility::getModuleData(
            [
                'displayThumbs' => '',
                'recursive' => '',
            ],
            //GeneralUtility::_GP('SET'),
            $this->request->getQueryParams()['SET'] ?? null,
            'file_WvFileCleanupCleanup'
        );

    }

    /**
     * Initialize indexAction
     */
    protected function initializeIndexAction(): void
    {
        $backendUser = $this->getBackendUser();
        $backendUserTsconfig = $this->getBackendUserTsconfig();

        // Set predefined value for DisplayThumbnails:
        if ($backendUserTsconfig['options.']['file_list.']['enableDisplayThumbnails'] === 'activated') {
            $this->moduleSettings['displayThumbs'] = true;
        } elseif ($backendUserTsconfig['options.']['file_list.']['enableDisplayThumbnails'] === 'deactivated') {
            $this->moduleSettings['displayThumbs'] = false;
        }
        // If user never opened the list module, set the value for displayThumbs
        if (!isset($this->moduleSettings['displayThumbs'])) {
            $this->moduleSettings['displayThumbs'] = $backendUser->uc['thumbnailsByDefault'] ?? false;
        }
    }

    /**
     * Register doc header buttons
     */
    protected function registerDocHeaderButtons(): void
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();
        // Refresh page
//        $refreshLink = GeneralUtility::linkThisScript(
//            [
//                'target' => rawurlencode($this->folder->getCombinedIdentifier()),
//            ]
//        );

        $refreshLink = $this->backendUriBuilder->buildUriFromRoute(
            $this->moduleIdentifier,
            [
                'target' => rawurlencode($this->folder->getCombinedIdentifier()),
            ]
        );

        $buttonFactory = GeneralUtility::makeInstance(
            LinkButton::class
        );
        $buttonTitle = $this->getLanguageService()->sL('labels.reload');
        $refreshButton = $buttonBar->makeLinkButton($buttonFactory)
            ->setHref($refreshLink)
            ->setTitle($buttonTitle)
            ->setIcon($this->iconFactory->getIcon('actions-refresh', Icon::SIZE_SMALL));

        $buttonBar->addButton($refreshButton, ButtonBar::BUTTON_POSITION_RIGHT);

        // Level up
        try {
            $currentStorage = $this->folder->getStorage();
            $parentFolder = $this->folder->getParentFolder();
            if (
                $parentFolder->getIdentifier() !== $this->folder->getIdentifier()
                && $currentStorage->isWithinFileMountBoundaries($parentFolder)
            ) {
                $levelUpTitle = $this->getLanguageService()->sL('labels.upOneLevel');
                if (!$levelUpTitle) {
                    $levelUpTitle = 'Up one level';
                }
                $levelUpButton = $buttonBar->makeLinkButton($buttonFactory)
                    ->setHref(
                        (string)$this->backendUriBuilder->buildUriFromRoute(
                            $this->moduleIdentifier,
                            ['id' => $parentFolder->getCombinedIdentifier()]
                        )
                    )
                    ->setTitle($levelUpTitle)
                    ->setIcon($this->iconFactory->getIcon('actions-view-go-up', Icon::SIZE_SMALL));
                if ((new Typo3Version())->getMajorVersion() < 12) {
                    $levelUpClick = 'top.document.getElementsByName("navigation")[0].';
                    $levelUpClick .= 'contentWindow.Tree.highlightActiveItem("file","folder';
                    $levelUpClick .= GeneralUtility::md5int($parentFolder->getCombinedIdentifier());
                    $levelUpClick .= '_"+top.fsMod.currentBank)';
                    $levelUpButton->setOnClick($levelUpClick);
                }
                $buttonBar->addButton($levelUpButton, ButtonBar::BUTTON_POSITION_LEFT, 1);
            }
        } catch (\Exception $e) {
            // Silent ignore exceptions
        }

        // Shortcut
        if ($this->getBackendUser()->mayMakeShortcut()) {
            $shortCutButton = $buttonBar->makeShortcutButton()
                ->setRouteIdentifier($this->moduleIdentifier)
                ->setDisplayName('File cleanup');
            $buttonBar->addButton($shortCutButton, ButtonBar::BUTTON_POSITION_RIGHT);
        }
    }

    /**
     * @throws ResourceDoesNotExistException
     */
    public function indexAction(): ResponseInterface
    {
        $this->moduleTemplate->assign('files', $this->fileRepository->findUnusedFile($this->folder, $this->moduleSettings['recursive'] ?? false));
        $this->moduleTemplate->assign('folder', $this->folder);
        $backendUserTsconfig = $this->getBackendUserTsconfig();

        $attributesDisplayThumbs = GeneralUtility::implodeAttributes([
            'type' => 'checkbox',
            'class' => 'checkbox',
            'name' => 'SET[displayThumbs]',
            'value' => '1',
            'data-global-event' => 'change',
            'data-action-navigate' => '$data=~s/$value/',
            'data-navigate-value' => sprintf(
                '%s&%s=${value}',
                (string)$this->backendUriBuilder->buildUriFromRoute(
                    $this->moduleIdentifier,
                    ['id' =>$this->folder ? $this->folder->getCombinedIdentifier() : '']
                ),
                'SET[displayThumbs]'
            ),
            'data-empty-value' => '0',
        ], true);
        $attributesRecursive = GeneralUtility::implodeAttributes([
            'type' => 'checkbox',
            'class' => 'checkbox',
            'name' => 'SET[recursive]',
            'value' => '1',
            'data-global-event' => 'change',
            'data-action-navigate' => '$data=~s/$value/',
            'data-navigate-value' => sprintf(
                '%s&%s=${value}',
                (string)$this->backendUriBuilder->buildUriFromRoute(
                    $this->moduleIdentifier,
                    ['id' =>$this->folder ? $this->folder->getCombinedIdentifier() : '']
                ),
                'SET[recursive]'
            ),
            'data-empty-value' => '0',
        ], true);

        $this->moduleTemplate->assign('checkboxes', [
            'displayThumbs' => [
                'enabled' => $GLOBALS['TYPO3_CONF_VARS']['GFX']['thumbnails']
                    && $backendUserTsconfig['options.']['file_list.']['enableDisplayThumbnails'] === 'selectable',
                'label' => $this->getLanguageService()->sL('LLL:EXT:wv_file_cleanup/Resources/Private/Language/locallang_mod_cleanup.xlf:displayThumbs'),
                'html' => '<input ' . $attributesDisplayThumbs . (isset($this->moduleSettings['displayThumbs']) ? ' checked="checked"' : '') . 'id="checkDisplayThumbs" />',
                'checked' => $this->moduleSettings['displayThumbs'],
            ],
            'recursive' => [
                'enabled' => true,
                'label' => $this->getLanguageService()->sL('LLL:EXT:wv_file_cleanup/Resources/Private/Language/locallang_mod_cleanup.xlf:search_folders_recursive'),
                'html' => '<input ' . $attributesRecursive . (isset($this->moduleSettings['recursive']) ? ' checked="checked"' : '') . 'id="checkRecursive" />',
                'checked' => $this->moduleSettings['recursive'] ?? false,
            ],
        ]);


        return $this->moduleTemplate->renderResponse('Cleanup/Index');
    }

    /**
     * Cleanup files
     *
     * @param array $files
     *
     * @return ResponseInterface
     * @throws ExistingTargetFolderException
     * @throws InsufficientFolderAccessPermissionsException
     * @throws \TYPO3\CMS\Core\Exception
     */
    public function cleanupAction(array $files): ResponseInterface
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
                        \TYPO3\CMS\Core\Type\ContextualFeedbackSeverity::ERROR,
                        true
                    );
                }
            } catch (Exception\FileDoesNotExistException $e) {
                // If given doesn't exist we silently ignore the file
            }
        }

        if ($movedFilesCount) {
            $this->addFlashMessage(
                str_replace('%d', $movedFilesCount, 'Moved %d files to recycler')
            );
        } else {
            $this->addFlashMessage(
                'No files moved',
                '',
                \TYPO3\CMS\Core\Type\ContextualFeedbackSeverity::WARNING,
                true
            );
        }

        return $this->redirect('index');
    }

    /**
     * Creates a Message object and adds it to the FlashMessageQueue.
     *
     * @param string $messageBody The message
     * @param string $messageTitle Optional message title
     * @param int $severity Optional severity, must be one of \TYPO3\CMS\Core\Messaging\FlashMessage constants
     * @param bool $storeInSession Optional, defines whether the message should be stored in the session
     *
     * @throws \InvalidArgumentException When the message body is no string
     * @throws \TYPO3\CMS\Core\Exception
     */
    public function addFlashMessage(
        $messageBody,
        $messageTitle = '',
        $severity = \TYPO3\CMS\Core\Type\ContextualFeedbackSeverity::OK,
        $storeInSession = true
    ): void {
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
        GeneralUtility::makeInstance(FlashMessageQueue::class, 'core.template.flashMessages')->enqueue($flashMessage);
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function getBackendUserTsconfig(): array
    {
        return $this->getBackendUser()->getTSConfig();
    }

    private function getExtbaseRequest(): RequestInterface
    {
        /** @var ServerRequestInterface $request */
        $request = $GLOBALS['TYPO3_REQUEST'];

        // We have to provide an Extbase request object
        return new Request(
            $request->withAttribute('extbase', new ExtbaseRequestParameters()),
        );
    }
}
