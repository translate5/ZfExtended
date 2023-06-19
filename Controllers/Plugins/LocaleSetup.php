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

/**
 * Plugin, das Locale und Sprache aufsetzt
 */
class ZfExtended_Controllers_Plugins_LocaleSetup extends Zend_Controller_Plugin_Abstract
{
    /**
     * Wird nach dem Routing aufgerufen
     *
     * <ul><li>liest locale aus den get oder post parametern aus<li>
     * <li>falls in locale in get oder post vorhanden
     *   <ul>
     *   <li>wird Zend_Locale anhand der locale initialisiert und in
     *       $session->Zend_Locale gespeichert</li>
     *   <li>wird Zend_Translation im Cache für die locale initialisiert</li>
     *   </ul>
     * </li>
     * <li>ansonsten: Falls Zend_Locale bereits in Session, passiert nichts weiter</li>
     * <li>falls alles nicht der Fall:
     *   <ul>
     *   <li>Hole locale aus den Browserpräferenzen</li>
     *   <li>falls Präferenz nicht als xliff-Datei im Portal verfügbar, gehe zur nächsten Präferenz, etc.</li>
     *   <li>falls immer noch nicht beziehbar nehme die default-locale aus der application.ini</li>
     *   </ul>
     * </li>
     * <li>Darüber hinaus: Hinterlege Zend_Locale-Objekt in der Registry als
     *     Default für alle Zend_Locale und Zend_Translation-Operationen</li>
     * </ul>
     *
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     */
    public function routeShutdown(Zend_Controller_Request_Abstract $request)
    {
        // Get session
        $session = new Zend_Session_Namespace();

        // Update locale, if need
        if ($locale = $request->getParam('locale')) {
            //$session->lock();
            $locale = ZfExtended_Utils::getLocale($locale);
            //$session->unlock();
            $session->locale = $locale;
        }

        // Register locale
        $this->registerLocale($session->locale);
    }

    /**
     * registers the given locale in the application as locale to be used
     * @param string $locale
     */
    protected function registerLocale($locale) {
        // Speicher locale und translation-object in Registry - so gilt sie für alle locale und
        $localeRegObj = new Zend_Locale($locale);
        $localeRegObj->getLanguage();
        //Prüfe, ob für die locale eine xliff-Datei vorhanden ist - wenn nicht fallback
        Zend_Registry::set('Zend_Locale', $localeRegObj);
    }
}