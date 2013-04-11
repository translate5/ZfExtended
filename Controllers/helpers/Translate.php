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
/*
 * Methoden, die den Translate-Prozess unterstützen
 *
 * 
 */

class ZfExtended_Controller_Helper_Translate extends Zend_Controller_Action_Helper_Abstract {

       /*
     * läd die zur aktuellen locale passenden Übersetzungen
     *
     * - nutzt den Cache zur Beschleunigung
     * - nutzt Zend_Log, um Logfile mit nicht in der Überseztzungen gefundene Ausgangstexte zu schreiben
     * - nutzt xliff als Dateiformat für die Übersetzungen
     * - zieht ausgangssprachliche Locale und Pfade zu Locale- und Logverzeichnissen aus application.ini (runtimeOptions)
     * - sorgt dafür, dass im logfile mit nicht gefundenen Übersetzungen mit Ausnahme
     *   der Übersetzungen des letzten Requests keine Dubletten vorhanden sind
     *   und die vorangestellten Datums und Notice-Angaben entfernt werden
     */
    public function setZendTranslate() {
        $session = new Zend_Session_Namespace();
        //setzte die sourceLang in der Session  ///
        $sourceLocale = $session->runtimeOptions->translation->sourceLocale;
        if (!Zend_Locale::isLocale($sourceLocale)) {
            throw new Zend_Exception('$sourceLocale war keine gültige locale - fehlerhafte Konfiguration in application.ini (runtimeOptions.translation.locale)', 0 );
        }
        $sourceLocaleObj = new Zend_Locale($sourceLocale);
        $session->sourceLang = $sourceLocaleObj->getLanguage();
        $targetLocaleObj = Zend_Registry::get('Zend_Locale');
        $targetLang = $targetLocaleObj->getLanguage();
        //definiere logfile für nicht gefundene Übersetzungen
        $logfile = $session->runtimeOptions->dir->logs.'/notFoundTranslation-'.
            $session->sourceLang.'-'.$targetLang.'.log';
        try {
            $log = Zend_Registry::get('translationLog');
        }
        catch (Exception $exc) {
            $writer = new Zend_Log_Writer_Stream($logfile);
            $log    = new Zend_Log($writer);
            Zend_Registry::set('translationLog', $log);
        }

        // Lade Übersetzungen und speichere Translate Objekt in der Session
         //$cache = Zend_Registry::get('cache'); //Caching deaktiviert, da der Aufruf der selben Seite mehrmals innerhalb von Millisekunden bei der Nutzung des Caches für Zend_Translate zu Fatal Error führt
         //Zend_Translate::setCache($cache);
        $Zend_Translate = new Zend_Translate(
            array(
                'adapter' => 'xliff',
                'content' => $session->runtimeOptions->dir->locales.'/'.$session->sourceLang.'.xliff',
                'locale'  => $session->sourceLang,
                'disableNotices' => true,
                'log'             => $log,
                'logUntranslated' => true,
                'logMessage' => '<trans-unit id=\'%message%\'>'.
                    '<source>%message%</source>'.
                    '<target>%message%</target>'.
                    '</trans-unit>',
                'useId' => true
            )
        );
        $Zend_Translate->addTranslation(array(
            'content' => $session->runtimeOptions->dir->locales.'/'.$targetLang.'.xliff',
            'locale' => $targetLang)
        );
        Zend_Registry::set('Zend_Translate', $Zend_Translate);
    }
}
