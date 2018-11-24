<?php
namespace WebVision\WvFileCleanup\ViewHelpers;

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

use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3\CMS\Fluid\Core\ViewHelper\Exception\InvalidVariableException;
use TYPO3\CMS\Fluid\Core\ViewHelper\Facets\CompilableInterface;

use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

/**
 * This viewHelper was copied from core but with one special feature added:
 * in versions 7 and 8 path to files of extension 'lang' is different, in
 * version 9 it even never exists anymore. Therefore paths had to be adjusted in
 * viewhelpers and it's difficult to provide templates for several TYPO3 versions.
 * So conversion of the path(s) is done and in version 9 the default path is set
 * to 'core/Resources/Private/Language/' instead of 'lang/(.../)'.
 * For the used file 'locallang_mod_file_list.xlf' in this extension 'wv_file_cleanup'
 * the extensionfolder is then replaced to the extension that is including this
 * file: 'filelist'. So in Version 9 for that file the following path is used:
 * 'filelist/Resources/Private/Language/'
 * 
 * ADVISE:
 * For using other core-labels than provided by default by this extension it's
 * the best to note the old paths for TYPO3 version 7 with respect to backwards-compatibility.
 *
 * Main feature of using core-translations is that the translations never have to be
 * maintained by the extension-author but are used from the core. As consequence
 * the labels are available in many languages without any additional work.
 *
 * This viewHelper might only work for this extension but the concept might be
 * portable and used for other extensions too.
 *
 * Integration and special adjustments: David Bruchmann, <david.bruchmann@gmail.com>
 *
 * *****************************************************************************
 * Translate a key from locallang. The files are loaded from the folder
 * "Resources/Private/Language/".
 *
 * == Examples ==
 *
 * <code title="Translate key">
 * <f:translate key="key1" />
 * </code>
 * <output>
 * value of key "key1" in the current website language
 * </output>
 *
 * <code title="Keep HTML tags">
 * <f:format.raw><f:translate key="htmlKey" /></f:format.raw>
 * </code>
 * <output>
 * value of key "htmlKey" in the current website language, no htmlspecialchars applied
 * </output>
 *
 * <code title="Translate key from custom locallang file">
 * <f:translate key="LLL:EXT:myext/Resources/Private/Language/locallang.xlf:key1" />
 * </code>
 * <output>
 * value of key "key1" in the current website language
 * </output>
 *
 * <code title="Inline notation with arguments and default value">
 * {f:translate(key: 'argumentsKey', arguments: {0: 'dog', 1: 'fox'}, default: 'default value')}
 * </code>
 * <output>
 * value of key "argumentsKey" in the current website language
 * with "%1" and "%2" are replaced by "dog" and "fox" (printf)
 * if the key is not found, the output is "default value"
 * </output>
 *
 * <code title="Inline notation with extension name">
 * {f:translate(key: 'someKey', extensionName: 'SomeExtensionName')}
 * </code>
 * <output>
 * value of key "someKey" in the current website language
 * the locallang file of extension "some_extension_name" will be used
 * </output>
 *
 * <code title="Translate id as in TYPO3 Flow">
 * <f:translate id="key1" />
 * </code>
 * <output>
 * value of id "key1" in the current website language
 * </output>
 */
class TranslateViewHelper extends AbstractViewHelper
{
    # use CompileWithRenderStatic;

    /**
     * Output is escaped already. We must not escape children, to avoid double encoding.
     *
     * @var bool
     */
    protected $escapeChildren = false;

    /**
     * Initialize arguments.
     *
     * @throws \TYPO3Fluid\Fluid\Core\ViewHelper\Exception
     */
    public function initializeArguments()
    {
        $mainVersion = explode('.', TYPO3_branch)[0];
        if ($mainVersion == 9) {
            $this->registerArgument('key', 'string', 'Translation Key');
            $this->registerArgument('id', 'string', 'Translation Key compatible to TYPO3 Flow');
            $this->registerArgument('default', 'string', 'If the given locallang key could not be found, this value is used. If this argument is not set, child nodes will be used to render the default');
            $this->registerArgument('arguments', 'array', 'Arguments to be replaced in the resulting string');
            $this->registerArgument('extensionName', 'string', 'UpperCamelCased extension key (for example BlogExample)');
            $this->registerArgument('languageKey', 'string', 'Language key ("dk" for example) or "default" to use for this translation. If this argument is empty, we use the current language');
            $this->registerArgument('alternativeLanguageKeys', 'array', 'Alternative language keys if no translation does exist');
        }
    }
    
    /**
     * Render translation
     *
     * @param string $key Translation Key
     * @param string $id Translation Key compatible to TYPO3 Flow
     * @param string $default If the given locallang key could not be found, this value is used. If this argument is not set, child nodes will be used to render the default
     * @param bool $htmlEscape TRUE if the result should be htmlescaped. This won't have an effect for the default value
     * @param array $arguments Arguments to be replaced in the resulting string
     * @param string $extensionName UpperCamelCased extension key (for example BlogExample)
     * @return string The translated key or tag body if key doesn't exist
     */
    public function render($key = null, $id = null, $default = null, $htmlEscape = null, array $arguments = null, $extensionName = null)
    {
        return static::renderStatic(
            [
                'key' => $key,
                'id' => $id,
                'default' => $default,
                'htmlEscape' => $htmlEscape,
                'arguments' => $arguments,
                'extensionName' => $extensionName,
            ],
            $this->buildRenderChildrenClosure(),
            $this->renderingContext
        );
    }

    /**
     * Return array element by key.
     *
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @throws InvalidVariableException
     * @return string
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        $key = $arguments['key'];
        $id = $arguments['id'];
        $default = $arguments['default'];
        $extensionName = $arguments['extensionName'];
        $translateArguments = $arguments['arguments'];

        // Wrapper including a compatibility layer for TYPO3 Flow Translation
        if ($id === null) {
            $id = $key;
        }

        if ((string)$id === '') {
            die('An argument "key" or "id" has to be provided. <br>Please try reloading the frame-page. <br>Execution terminated.');
            # throw new InvalidVariableException('An argument "key" or "id" has to be provided', 1351584844);
        }
        
        if (strpos($key, 'LLL:EXT:lang/') === 0) {
            $mainVersion = explode('.', TYPO3_branch)[0];
            $file = substr($key, strrpos($key, '/')+1);
            switch ($mainVersion) {
                case '7':
                    $langFilesPath = 'EXT:lang/';
                break;
                case '8':
                    $langFilesPath = 'EXT:lang/Resources/Private/Language/';
                break;
                default:
                    // 9 and above
                    $langFilesPath = 'EXT:core/Resources/Private/Language/';
                    if (strpos($file,'locallang_mod_file_list.xlf') === 0) {
                        $langFilesPath = 'EXT:filelist/Resources/Private/Language/';
                    }
                break;
            }
            if ($id==$key) {
               $id = 'LLL:' . $langFilesPath.$file;
            }
            $key = 'LLL:' . $langFilesPath.$file;
        }

        $request = $renderingContext->getControllerContext()->getRequest();
        $extensionName = $extensionName ?? $request->getControllerExtensionName();
        try {
            $value = static::translate($id, $extensionName, $translateArguments, $arguments['languageKey'], $arguments['alternativeLanguageKeys']);
        } catch (\InvalidArgumentException $e) {
            $value = null;
        }
        if ($value === null) {
            $value = $default ?? $renderChildrenClosure();
            if (!empty($translateArguments)) {
                $value = vsprintf($value, $translateArguments);
            }
        }
        return $value;
    }

    /**
     * Wrapper call to static LocalizationUtility
     *
     * @param string $id Translation Key compatible to TYPO3 Flow
     * @param string $extensionName UpperCamelCased extension key (for example BlogExample)
     * @param array $arguments Arguments to be replaced in the resulting string
     * @param string $languageKey Language key to use for this translation
     * @param string[] $alternativeLanguageKeys Alternative language keys if no translation does exist
     *
     * @return string|null
     */
    protected static function translate($id, $extensionName, $arguments, $languageKey, $alternativeLanguageKeys)
    {
        return LocalizationUtility::translate($id, $extensionName, $arguments, $languageKey, $alternativeLanguageKeys);
    }
}
