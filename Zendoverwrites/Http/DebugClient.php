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
 * Debug Mockup of Zend_Http_Client class
 * Can be invoked with factory overwrite mechanisms
 * Logs communication to the PHP log
 */
class  ZfExtended_Zendoverwrites_Http_DebugClient extends Zend_Http_Client {
    public function request($method = null){
        $randKey = substr(md5(rand()), 0, 7);
        
        error_log("Method ($randKey): ".(empty($method) ? $this->method : $method));
        error_log("URL ($randKey):".$this->getUri(true));
        //error_log("\n\nDATA ($randKey): \n".$this->raw_post_data."\n\n");
        error_log("Bytes ($randKey):".mb_strlen($this->raw_post_data));
        $response = parent::request($method);
        error_log("Status ($randKey): ".print_r($response->getStatus(),1));
        error_log("Raw Body ($randKey):".print_r($response->getRawBody(),1));
        error_log("Headers ($randKey):".($response->getHeadersAsString()));
        return $response;
    }
}
