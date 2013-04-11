<?php
 /*
   START LICENSE AND COPYRIGHT

  This file is part of the ZfExtended library and build on Zend Framework

  Copyright (c) 2013 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

  Contact:  http://www.MittagQI.com/  /  service (ÄTT) MittagQI.com

  This file is multi-licensed under the EPL, LGPL and GPL licenses. 

  It may be used under the terms of the Eclipse Public License - v 1.0
  as published by the Eclipse Foundation and appearing in the file eclipse-license.txt 
  included in the packaging of this file.  Please review the following information 
  to ensure the Eclipse Public License - v 1.0 requirements will be met:
  http://www.eclipse.org/legal/epl-v10.html.

  Also it may be used under the terms of the GNU LESSER GENERAL PUBLIC LICENSE version 3
  or the GNU GENERAL PUBLIC LICENSE version 3 as published by the 
  Free Software Foundation, Inc. and appearing in the files lgpl-license.txt and gpl3-license.txt
  included in the packaging of this file.  Please review the following information 
  to ensure the GNU LESSER GENERAL PUBLIC LICENSE version 3 requirements or the
  GNU GENERAL PUBLIC LICENSE version 3 requirements will be met:
  http://www.gnu.org/licenses/lgpl.html   /  http://www.gnu.org/licenses/gpl.html
  
  @copyright  Marc Mittag, MittagQI - Quality Informatics
  @author     MittagQI - Quality Informatics
  @license    Multi-licensed under Eclipse Public License - v 1.0 http://www.eclipse.org/legal/epl-v10.html, GNU LESSER GENERAL PUBLIC LICENSE version 3 http://www.gnu.org/licenses/lgpl.html and GNU GENERAL PUBLIC LICENSE version 3 http://www.gnu.org/licenses/gpl.html

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
