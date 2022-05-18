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

/**#@+ 
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 * 
 */
/**
 * Wrapper Class for using additional, normal routes, which fake REST-Routes.
 * This is needed since Rest Routes just know and call GET PUT POST DELETE actions. 
 * All other additionally actions must configured as either RestFakeRoute or RestLikeRoute
 * 
 * RestFakeRoute and RestLikeRoute Routes are treated in error and access handling like a a rest route (called via API)
 * 
 * Difference between RestFakeRoute and RestLikeRoute:
 * RestFakeRoute: No automatic request result conversion is done - the result is outputted as generated (HTML, binary data from a file, JSON manually encoded)
 * RestLikeRoute: REST_Controller_Plugin_RestHandler is invoked, so the data in the view is returned in the format as requested by the caller  (mainly JSON)
 */
class ZfExtended_Controller_RestLikeRoute extends Zend_Controller_Router_Route {
    
    private array $operationPatterns = [
        '@([a-zA-Z]+)/([a-zA-Z_]+)/([a-zA-Z0-9]+)/([a-zA-Z]+)/operation@m',// module/controller/entityId/operationName/operation
        '@([a-zA-Z]+)/([a-zA-Z_]+)/([a-zA-Z]+)/operation@m',// module/controller/operationName/operation
    ];
    /**
     * Matches a user submitted path with parts defined by a map. Assigns and
     * returns an array of variables on a successful match.
     *
     * @param string  $path Path used to match against this routing map
     * @param bool $partial
     * @throws Zend_Controller_Router_Exception
     * @return array|false An array of assigned values or a false on a mismatch
     */
    public function match($path, $partial = false)
    {
        $return=parent::match($path,$partial);

        foreach ($this->operationPatterns as $operationPattern) {
            //match operation path route
            if(preg_match($operationPattern, $path)){

                //inject the operation action
                if(!empty($this->_defaults) && isset($this->_defaults['action']) && $this->_defaults['action']==""){
                    $this->_defaults['action']="operation";
                    $return['action']="operation";
                    return $return;
                }
            }
        }

        return $return; 
    }
}