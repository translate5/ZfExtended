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
 * Registriert RegisterRestControllerPluginRestHandler als Controller-Plugin,
 * falls die aktuelle Route Zend_Rest_Route entspricht
 *
 */
class ZfExtended_Controllers_Plugins_RegisterRestControllerPluginRestHandler extends Zend_Controller_Plugin_Abstract {
    /**
     * Wird vor dem Start des Dispatcher Laufes ausgeführt
     * 
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     */
    public function RouteShutdown(Zend_Controller_Request_Abstract $request)
    {
        $front = Zend_Controller_Front::getInstance();
        if(get_class($front->getRouter()->getCurrentRoute()) === 'Zend_Rest_Route'){
            $front->registerPlugin(new REST_Controller_Plugin_RestHandler($front));
        }
    }
}