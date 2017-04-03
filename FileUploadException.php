<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
 Copyright (c) 2013 - 2017 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

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

/**
 * TODO currently this exception is not usable, it provides only a mapping between upload error code and error message
 */
class ZfExtended_FileUploadException extends ZfExtended_Exception {
    /**
     * @var string
     */
    protected $defaultMessage = 'Fehler beim Datei Upload: ';
    
    /**
     * @var integer
     */
    protected $defaultCode = 400;
    
    public function __construct($msg = '', $code = 0, Exception $previous = null, $origin = 'core') {
        //This class is not implemented yet for direct usage.
        // On creation of this class we needed just a place to put a ERROR_CODE to MSG map (getErrorMessage)
        // In future it makes sense to use this class for file uploads
        die("IMPLEMENT ME");
    }
    
    /**
     * returns a german error message to the given file upload error code 
     * @param unknown $errorNr
     */
    public static function getErrorMessage($errorNr) {
        switch ($errorNr) {
            case UPLOAD_ERR_OK:
                return 'Es liegt kein Fehler vor, die Datei wurde erfolgreich hochgeladen.';
            case UPLOAD_ERR_INI_SIZE:
                return 'Die hochgeladene Datei überschreitet die in der Anweisung upload_max_filesize in php.ini festgelegte Größe.';
            case UPLOAD_ERR_FORM_SIZE:
                return 'Die hochgeladene Datei überschreitet die in dem HTML Formular mittels der Anweisung MAX_FILE_SIZE angegebene maximale Dateigröße.';
            case UPLOAD_ERR_PARTIAL:
                return 'Die Datei wurde nur teilweise hochgeladen.';
            case UPLOAD_ERR_NO_FILE:
                return 'Es wurde keine Datei hochgeladen.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Fehlender temporärer Ordner.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Speichern der Datei auf die Festplatte ist fehlgeschlagen.';
            case UPLOAD_ERR_EXTENSION:
                return 'Eine PHP Erweiterung hat den Upload der Datei gestoppt. PHP bietet keine Möglichkeit an, um festzustellen welche Erweiterung das Hochladen der Datei gestoppt hat. Überprüfung aller geladenen Erweiterungen mittels phpinfo() könnte helfen.';
        }
    }
}