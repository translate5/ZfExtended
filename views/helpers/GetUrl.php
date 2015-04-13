<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of translate5
 
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
 * Gibt die aktuelle URL zurück
 *
 */
class ZfExtended_View_Helper_GetUrl extends Zend_View_Helper_Abstract{
    public $view;

    public function setView(Zend_View_Interface $view) {
        $this->view = $view;
    }
    
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
