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
 * Module: WebVision/WvFileCleanup/Cleanup
 * @exports WebVision/WvFileCleanup/Cleanup
 */
define(['jquery'], function($) {

    $('.js-cleanup-all').on('click', function(){
        if ($(this).is(':checked')) {
            $('.js-cleanup-checkbox').attr('checked', true);
        } else {
            $('.js-cleanup-checkbox').attr('checked', false);
        }
    });

});