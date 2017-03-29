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

/**#@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 *
 */

/**
 * codiert oder decodiert den übergebenen String mit $session->runtimeOptions->fileSystemEncoding ausgehend von utf-8
 */
class ZfExtended_Controller_Helper_LocalEncoded extends Zend_Controller_Action_Helper_Abstract {
    /**
     * @var string
     */
    protected $_fileSystemEncoding;

    public function __construct(){
        $session = new Zend_Session_Namespace();
        $this->_fileSystemEncoding = $session->runtimeOptions->fileSystemEncoding;
    }
    
    /**
     * codiert den übergebenen String mit $session->runtimeOptions->fileSystemEncoding ausgehend von utf-8
     * @param string $path
     * @return string $path 
     */
    public function encode (string $path) {
        return iconv('UTF-8', $this->_fileSystemEncoding, $path);
    }
    /**
     * decodiert den übergebenen String mit $session->runtimeOptions->fileSystemEncoding ausgehend von utf-8
     * 
     * @param string $path
     * @return string $path 
     */
    public function decode (string $path) {
        return iconv($this->_fileSystemEncoding, 'UTF-8', $path);
    }
}
