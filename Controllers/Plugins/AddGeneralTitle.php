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
 * Fügt ein allgemeines Suffix für den title-Tag hinzu
 * FIXME this plugin is also called on REST calls which is completly nonsense!
 *   Should be moved into the default module layout phtml for ExtJS / API based applications!
 */
class ZfExtended_Controllers_Plugins_AddGeneralTitle extends Zend_Controller_Plugin_Abstract
{
    /**
     * @var Zend_Controller_Action_Helper_ViewRenderer
     */
    protected $_viewRenderer;

    /**
     * Wird vor der Dispatcher Schleife aufgerufen
     */
    public function postDispatch(Zend_Controller_Request_Abstract $request)
    {
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
