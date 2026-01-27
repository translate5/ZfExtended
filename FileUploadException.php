<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library

 Copyright (c) 2013 - 2021 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU LESSER GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file lgpl3-license.txt
 included in the packaging of this file.  Please review the following information
 to ensure the GNU LESSER GENERAL PUBLIC LICENSE version 3.0 requirements will be met:
https://www.gnu.org/licenses/lgpl-3.0.txt

 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU LESSER GENERAL PUBLIC LICENSE version 3
             https://www.gnu.org/licenses/lgpl-3.0.txt

END LICENSE AND COPYRIGHT
*/

use MittagQI\ZfExtended\Localization;

class ZfExtended_FileUploadException extends ZfExtended_UnprocessableEntity
{
    //Since such errors are mainly intresting for the uploading user, we just log it as debug
    protected $level = ZfExtended_Logger::LEVEL_INFO;

    /**
     * returns a localized error message to the given file upload error code
     * TODO currently not used by the excption itself.
     */
    public static function getUploadErrorMessage(int $errorNr, string $locale = null)
    {
        return match ($errorNr) {
            UPLOAD_ERR_OK => Localization::trans(
                'No error occurred, the file was uploaded successfully.',
                $locale
            ),
            UPLOAD_ERR_INI_SIZE => Localization::trans(
                'The uploaded file exceeds the size that is set in ' .
                'the upload_max_filesize instruction in php.ini.',
                $locale
            ),
            UPLOAD_ERR_FORM_SIZE => Localization::trans(
                'The uploaded file exceeds the maximum size set ' .
                'in the HTML form via the MAX_FILE_SIZE value.',
                $locale
            ),
            UPLOAD_ERR_PARTIAL => Localization::trans('The file was only partially uploaded.', $locale),
            UPLOAD_ERR_NO_FILE => Localization::trans('No file was uploaded.', $locale),
            UPLOAD_ERR_NO_TMP_DIR => Localization::trans('Missing temporary folder.', $locale),
            UPLOAD_ERR_CANT_WRITE => Localization::trans(
                'Failed to save the file to disk.',
                $locale
            ),
            UPLOAD_ERR_EXTENSION => Localization::trans(
                'A PHP extension stopped the file upload. PHP does not ' .
                'provide a way to determine which extension caused this. ' .
                'Checking all loaded extensions using phpinfo() may help.',
                $locale
            ),
            default => Localization::trans('An unknown error occurred', $locale),
        };
    }
}
