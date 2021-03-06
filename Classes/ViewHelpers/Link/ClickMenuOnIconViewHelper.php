<?php
namespace WebVision\WvFileCleanup\ViewHelpers\Link;

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

/**
 * Class ClickMenuOnIconViewHelper
 */
class ClickMenuOnIconViewHelper extends \TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper
{
    /**
     * @var string
     */
    protected $tagName = 'a';

    /**
     * @return void
     */
    public function initializeArguments()
    {
        $this->registerUniversalTagAttributes();
        $this->registerArgument('uid', 'string', 'File identifier');
    }

    /**
     * Renders click menu link (context sensitive menu)
     *
     * @param string $table
     *
     * @return string
     * @see \TYPO3\CMS\Backend\Utility\BackendUtility::wrapClickMenuOnIcon()
     */
    public function render()
    {
        $this->tag->addAttribute('class', 't3js-contextmenutrigger ');
        $this->tag->addAttribute('data-table', 'sys_file');
        $this->tag->addAttribute('data-uid', $this->arguments['uid']);
        $this->tag->addAttribute('href', '#');

        $this->tag->setContent($this->renderChildren());

        return $this->tag->render();
    }
}
