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
/***
 * Regenerate the session only for non rest requests
 */
class ZfExtended_Controllers_Plugins_SessionRegenerate extends Zend_Controller_Plugin_Abstract {

    /**
     * Wird vor dem Start des Dispatcher Laufes ausgeführt
     *
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     */
    public function dispatchLoopShutdown() {
        $layouthelper = ZfExtended_Zendoverwrites_Controller_Action_HelperBroker::getStaticHelper(
            'layout'
        );
        $user = new Zend_Session_Namespace('user');
        $isAuthenticated = !empty($user->data->userGuid);
        // if you are not in the rest context, we want this to happen.
        ZfExtended_Session::updateSession($layouthelper->isEnabled() && !$isAuthenticated && !Zend_Session::isDestroyed());
    }
}