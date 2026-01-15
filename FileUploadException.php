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
                'Es liegt kein Fehler vor, die Datei wurde erfolgreich hochgeladen.',
                $locale
            ),
            UPLOAD_ERR_INI_SIZE => Localization::trans(
                'Die hochgeladene Datei überschreitet die in der Anweisung ' .
                'upload_max_filesize in php.ini festgelegte Größe.',
                $locale
            ),
            UPLOAD_ERR_FORM_SIZE => Localization::trans(
                'Die hochgeladene Datei überschreitet die in dem HTML Formular ' .
                'mittels der Anweisung MAX_FILE_SIZE angegebene maximale Dateigröße.',
                $locale
            ),
            UPLOAD_ERR_PARTIAL => Localization::trans('Die Datei wurde nur teilweise hochgeladen.', $locale),
            UPLOAD_ERR_NO_FILE => Localization::trans('Es wurde keine Datei hochgeladen.', $locale),
            UPLOAD_ERR_NO_TMP_DIR => Localization::trans('Fehlender temporärer Ordner.', $locale),
            UPLOAD_ERR_CANT_WRITE => Localization::trans(
                'Speichern der Datei auf die Festplatte ist fehlgeschlagen.',
                $locale
            ),
            UPLOAD_ERR_EXTENSION => Localization::trans(
                'Eine PHP Erweiterung hat den Upload der Datei gestoppt. PHP bietet keine ' .
                'Möglichkeit an, um festzustellen welche Erweiterung das Hochladen der Datei gestoppt hat. ' .
                'Überprüfung aller geladenen Erweiterungen mittels phpinfo() könnte helfen.',
                $locale
            ),
            default => Localization::trans('Unknown error occurred', $locale),
        };
    }
}
