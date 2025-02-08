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
 * Registriert RegisterRestControllerPluginRestHandler als Controller-Plugin,
 * falls die aktuelle Route Zend_Rest_Route entspricht
 */
class ZfExtended_Controllers_Plugins_RegisterRestControllerPluginRestHandler extends Zend_Controller_Plugin_Abstract
{
    /**
     * Wird vor dem Start des Dispatcher Laufes ausgeführt
     */
    public function routeShutdown(Zend_Controller_Request_Abstract $request)
    {
        $front = Zend_Controller_Front::getInstance();
        $restFulRoutes = [
            Zend_Rest_Route::class,
            ZfExtended_Controller_RestLikeRoute::class,
            ZfExtended_Controller_RestLikeRouteRegex::class,
            ZfExtended_Controller_CustomPathRestRoute::class,
        ];
        $routeClass = get_class($front->getRouter()->getCurrentRoute());
        if (in_array($routeClass, $restFulRoutes, true)) {
            $front->registerPlugin(new REST_Controller_Plugin_RestHandler($front));
        }
    }
}
