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
        $session = new Zend_Session_Namespace();
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
