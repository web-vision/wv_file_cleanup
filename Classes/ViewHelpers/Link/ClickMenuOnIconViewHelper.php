<?php

namespace WebVision\WvFileCleanup\ViewHelpers\Link;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

/**
 * Class ClickMenuOnIconViewHelper
 */
class ClickMenuOnIconViewHelper extends AbstractTagBasedViewHelper
{
    /**
     * @var string
     */
    protected $tagName = 'a';

    public function initializeArguments(): void
    {
        $this->registerUniversalTagAttributes();
        $this->registerArgument('uid', 'string', 'File identifier');
    }

    /**
     * @see \TYPO3\CMS\Backend\Utility\BackendUtility::wrapClickMenuOnIcon()
     */
    public function render(): string
    {
        $this->tag->addAttribute('class', 't3js-contextmenutrigger ');
        $this->tag->addAttribute('data-table', 'sys_file');
        $this->tag->addAttribute('data-uid', $this->arguments['uid']);
        $this->tag->addAttribute('href', '#');

        $this->tag->setContent($this->renderChildren());

        return $this->tag->render();
    }
}
