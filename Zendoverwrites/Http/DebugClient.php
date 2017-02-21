<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
 Copyright (c) 2013 - 2015 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file agpl3-license.txt 
 included in the packaging of this file.  Please review the following information 
 to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3.0 requirements will be met:
 http://www.gnu.org/licenses/agpl.html

 There is a plugin exception available for use with this release of translate5 for
 open source applications that are distributed under a license other than AGPL:
 Please see Open Source License Exception for Development of Plugins for translate5
 http://www.translate5.net/plugin-exception.txt or as plugin-exception.txt in the root
 folder of translate5.
  
 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execptions
			 http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt

END LICENSE AND COPYRIGHT
*/

/**#@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 *
 */
/**
 * Debug Mockup of Zend_Http_Client class
 * Can be invoked with factory overwrite mechanisms
 * Logs communication to the PHP log
 */
class  ZfExtended_Zendoverwrites_Http_DebugClient extends Zend_Http_Client {
    public function request($method = null){
        $category = 'plugin';
        $section = 'MatchResource';
        
        if(ZfExtended_Debug::hasLevel($category, $section, 2)) {
            error_log("Method: ".$method);
            error_log("URL: ".$this->getUri(true));
            error_log("\n\nDATA: \n".$this->raw_post_data."\n\n");
        }
        $response = parent::request($method);
        if(ZfExtended_Debug::hasLevel($category, $section, 2)) {
            error_log("Status: ".print_r($response->getStatus(),1));
            error_log("Raw Body: ".print_r($response->getRawBody(),1));
        }
        return $response;
    }
}
