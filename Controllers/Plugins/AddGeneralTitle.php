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
 * Fügt ein allgemeines Suffix für den title-Tag hinzu
 *
 */
class ZfExtended_Controllers_Plugins_AddGeneralTitle extends Zend_Controller_Plugin_Abstract
{
    /**
     * @var Zend_Controller_Action_Helper_ViewRenderer
     */
    protected  $_viewRenderer;

    /**
     * Wird vor der Dispatcher Schleife aufgerufen
     *
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     */
    public function  postDispatch(Zend_Controller_Request_Abstract $request) {
        parent::postDispatch($request);
        $this->_viewRenderer = ZfExtended_Zendoverwrites_Controller_Action_HelperBroker::getStaticHelper(
            'ViewRenderer'
        );
        $translate = ZfExtended_Zendoverwrites_Translate::getInstance();
        /* @var $translate ZfExtended_Zendoverwrites_Translate */;
        $this->_viewRenderer->view->headTitle()->setSeparator(' - ')
                ->append($translate->_('GeneralMetatagTitleSuffix'));
    }
}
