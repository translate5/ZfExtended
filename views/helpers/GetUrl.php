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
 * Gibt die aktuelle URL zurück
 *
 */
class ZfExtended_View_Helper_GetUrl extends Zend_View_Helper_Abstract{
    public $view;

    /**
     * generiert die aktuelle url
     *
     * @param array excludeParamKeys array mit einer Liste von Parameterschlüsseln
     *       als Values, die nicht in der zurückgegebenen URL zurückgegeben werden sollen | Default NULL
     */
    public function getUrl(array $excludeParamKeys = NULL){
        $params = Zend_Controller_Front::getInstance()->getRequest()->getParams();
        $url = APPLICATION_RUNDIR;
        if(isset($params['error_handler'])){
            unset($params['error_handler']);
        }
        if ($params['module'] !== 'default') {
            $url .= '/' . $params['module'];
        }
        unset($params['module']);
        $url .= '/' . $params['controller'];
        unset($params['controller']);
        $url .= '/' . $params['action'];
        unset($params['action']);
        if(!is_null($excludeParamKeys)){
            foreach($excludeParamKeys as $key){
                if(isset($params[$key]))unset($params[$key]);
            }
        }
        foreach ($params as $key => $val) {
            $url .= '/' . $key . '/' . print_r($val,1); //$val can be an array, fastest fix is print_r
        }
        return $url;
    }

}
