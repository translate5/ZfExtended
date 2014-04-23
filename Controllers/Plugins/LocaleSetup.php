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
        // Prüfe auf Sprachschlüssel in URL
        if ($request->getParam('locale')) {
            // Hole locale
            $session->locale = $request->getParam('locale');
            //fange Falscheingaben ab
            if (!Zend_Locale::isLocale($session->locale)) {
                throw new Zend_Exception('$request->getParam(\'locale\') war keine gültige locale', 0 );
            }
            
            $this->updateUserLocale($session->locale);
        }
        elseif (!isset($session->locale)) {
            $localeObj = new Zend_Locale();
            $userPrefLangs = array_keys($localeObj->getBrowser());
            if(count($userPrefLangs)>0){
                //Prüfe, ob für jede locale, ob eine xliff-Datei vorhanden ist - wenn nicht fallback
                foreach($userPrefLangs as $testLocale){
                    $testLocaleObj = new Zend_Locale($testLocale);
                    $testLang = $testLocaleObj->getLanguage();
                    if(file_exists($session->runtimeOptions->dir->locales.DIRECTORY_SEPARATOR.$testLang.'.xliff')){
                        $session->locale = $testLang;
                        break;
                    }
                }
            }
            if(!$session->locale){
                $session->locale = $session->runtimeOptions->translation->sourceCodeLocale;
            }
        }
        // Speicher locale und translation-object in Registry - so gilt sie für alle locale und
        $localeRegObj = new Zend_Locale($session->locale);
        
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
        $user->load($sessionUser->data->id);
        $user->setLocale($locale);
        $user->save();
    }
}