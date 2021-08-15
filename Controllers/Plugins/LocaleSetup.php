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
        // Initialisiere Sprachschlüssel
        $session = new Zend_Session_Namespace();
        $config = Zend_Registry::get('config');
        
        // when locale is given as parameter, this overrides all other locale calculation, but only if it valid.
        $locale = $request->getParam('locale');
        if ($locale && Zend_Locale::isLocale($locale)) {
            $session->locale = $locale;
            $this->updateUserLocale($locale);
            $this->registerLocale($locale);
            return;
        }
        
        $fallback = $config->runtimeOptions->translation->fallbackLocale;
        
        //check for application configuration
        if(!isset($session->locale) && $appLocale = $config->runtimeOptions->translation->applicationLocale) {
            if (!Zend_Locale::isLocale($appLocale)) {
                error_log('Configured runtimeOptions.translation.applicationLocale is no valid locale, using '.$fallback);
                $appLocale = $fallback;
            }
            $session->locale = $appLocale;
        }
        
        //use browser language as fallback
        if (!isset($session->locale)) {
            $session->locale = $this->getLocaleFromBrowser();
            //fallback
            if(!$session->locale){
                $session->locale = $fallback;
            }
        }
        $this->updateUserLocale($session->locale);
        $this->registerLocale($session->locale);
    }
    
    /**
     * gets locale from browser
     * @return string
     */
    protected function getLocaleFromBrowser() {
        $config = Zend_Registry::get('config');
        $localeObj = new Zend_Locale();
        $userPrefLangs = array_keys($localeObj->getBrowser());
        if(count($userPrefLangs)>0){
            //Prüfe, ob für jede locale, ob eine xliff-Datei vorhanden ist - wenn nicht fallback
            foreach($userPrefLangs as $testLocale){
                $testLocaleObj = new Zend_Locale($testLocale);
                $testLang = $testLocaleObj->getLanguage();
                if(file_exists($config->runtimeOptions->dir->locales.DIRECTORY_SEPARATOR.$testLang.'.xliff')){
                    return $testLang;
                }
            }
        }
    }
    
    /**
     * registers the given locale in the application as locale to be used
     * @param string $locale
     */
    protected function registerLocale($locale) {
        // Speicher locale und translation-object in Registry - so gilt sie für alle locale und
        $localeRegObj = new Zend_Locale($locale);
        
        //Prüfe, ob für die locale eine xliff-Datei vorhanden ist - wenn nicht fallback
        Zend_Registry::set('Zend_Locale', $localeRegObj);
    }
    
    /**
     * updates the locale of the currently authenticated user
     */
    protected function updateUserLocale($locale) {
        if(!Zend_Auth::getInstance()->hasIdentity()){
            return;
        }
        $sessionUser = new Zend_Session_Namespace('user');
        $user = ZfExtended_Factory::get('ZfExtended_Models_User');
        /* @var $user ZfExtended_Models_User */
        try {
            $user->load($sessionUser->data->id);
            $user->setLocale($locale);
            $user->save();
        }
        catch(ZfExtended_Models_Entity_NotFoundException $e) {
            //if the user does not exist (anymore) do nothing here
        }
    }
}