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

use MittagQI\ZfExtended\Localization;

/**
 * Plugin to register the locale to be used by the app
 */
class ZfExtended_Controllers_Plugins_LocaleSetup extends Zend_Controller_Plugin_Abstract
{
    /**
     * Is called after routing
     * initializes the session locale as the user locale - if no valid session locale exists
     */
    public function routeShutdown(Zend_Controller_Request_Abstract $request)
    {
        // Get session
        $session = new Zend_Session_Namespace();

        // Update locale, if not already in session or invalid
        if (empty($session->locale) || ! Localization::isAvailableLocale($session->locale)) {
            // Get browser-locale or fallback-locale
            $session->locale = Localization::evaluateLocale();
        }

        // register locale
        $zendLocale = new Zend_Locale($session->locale);
        Zend_Registry::set('Zend_Locale', $zendLocale);
    }
}
