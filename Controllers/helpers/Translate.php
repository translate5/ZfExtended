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
    /**
     *
     * @var string the language part of the locale 
     */
    protected $sourceLang;
    /**
     *
     * @var string the language part of the locale 
     */
    protected $targetLang;
    /**
     *
     * @var string  
     */
    protected $logPath;
    /**
     *
     * @var Zend_Translate 
     */
    protected $translate;
    /**
     *
     * @var array
     */
    protected $translationPaths;
    
    public function __construct() {
        //this is to have an translation adapter set if an error occurs inside translation
        //process before translation adapter is set - but errorcontroller needs one
        $translate = new Zend_Translate('Zend_Translate_Adapter_Array', array('' => ''), 'en');
        Zend_Registry::set('Zend_Translate', $translate);
        $this->setSourceLang();
        $this->setTargetLang();
        $this->getLogPath();
    }
    public function getXliffStartString() {
        return '<?xml version="1.0" ?>
<xliff version=\'1.1\' xmlns=\'urn:oasis:names:tc:xliff:document:1.1\'>
<!-- the transunit-ID should contain the base64-encoded source-string als used in the php source-code. This ID is used for matching. -->
<!-- html-tags inside the target-string must not be encoded as they should regarding xliff but should be left as plain hmtl. At this stage ZfExtended does not support xliff inline tags -->
 <file original=\'php-sourcecode\' source-language=\''.$this->getSourceLang().
                '\' target-language=\''.$this->getTargetLang().'\' datatype=\'php\'>
  <body>';
    }
    public function getXliffEndString() {
        return "
  </body>
 </file>
</xliff>";
    }

    /**
     * 
     * @param string $lang  
     */
    public function setSourceLang($lang = null) {
        $this->sourceLang = $lang;
        if(is_null($this->sourceLang)){
            $session = new Zend_Session_Namespace();
            //setzte die sourceLang in der Session  ///
            $sourceLocale = $session->runtimeOptions->translation->sourceLocale;
            if (!Zend_Locale::isLocale($sourceLocale)) {
                throw new Zend_Exception('$sourceLocale war keine gültige locale - fehlerhafte Konfiguration in application.ini (runtimeOptions.translation.locale)', 0 );
            }
            $sourceLocaleObj = new Zend_Locale($sourceLocale);
            $session->sourceLang = $sourceLocaleObj->getLanguage();
            $this->sourceLang = $session->sourceLang;
        }
    }
    /**
     * 
     * @param string $lang  
     */
    public function setTargetLang($lang = null) {
        $this->targetLang = $lang;
        if(is_null($this->targetLang)){
            $targetLocaleObj = Zend_Registry::get('Zend_Locale');
            $this->targetLang = $targetLocaleObj->getLanguage();
        }
    }
    
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
        $this->setSourceLang();
        
        try {
            $log = Zend_Registry::get('translationLog');
        }
        catch (Exception $exc) {
            $writer = new Zend_Log_Writer_Stream($this->getLogPath());
            $formatter = new Zend_Log_Formatter_Simple('%message%' . PHP_EOL);
            $writer->setFormatter($formatter);
            $log    = new Zend_Log($writer);
            Zend_Registry::set('translationLog', $log);
        }

        // Lade Übersetzungen und speichere Translate Objekt in der Session
         //$cache = Zend_Registry::get('cache'); //Caching deaktiviert, da der Aufruf der selben Seite mehrmals innerhalb von Millisekunden bei der Nutzung des Caches für Zend_Translate zu Fatal Error führt
         //Zend_Translate::setCache($cache);
        $this->translate = new Zend_Translate(
            array(
                'adapter' => 'ZfExtended_Zendoverwrites_Translate_Adapter_Xliff',
                'content' => $session->runtimeOptions->dir->locales.'/'.$this->sourceLang.'.xliff',
                'locale'  => $this->sourceLang,
                'disableNotices' => true,
                'log'             => $log,
                'logUntranslated' => true,
                'logMessage' => '<trans-unit id=\'%id%\'>'.
                    '<source>%message%</source>'.
                    '<target>%message%</target>'.
                    '</trans-unit>',
                'useId' => true
            )
        );
        $this->addTranslations();
        Zend_Registry::set('Zend_Translate', $this->translate);
    }
    
    protected function addTranslations() {
        $paths = $this->getTranslationPaths(); 
        foreach ($paths as $path) {
            $this->translate->addTranslation(array(
                'content' => $path,
                'locale' => $this->getTargetLang())
            );
        }
    }
    /**
     * later paths should overwrite previous ones
     * @return array
     */
    public function getTranslationPaths() {
        if(empty($this->translationPaths)){
            $session = new Zend_Session_Namespace(); 
            $ds = DIRECTORY_SEPARATOR;
            $xliff = $this->getTargetLang().'.xliff';
            $this->translationPaths = array();
            $index = ZfExtended_BaseIndex::getInstance();
            /* @var $index ZfExtended_BaseIndex */
            $libs = $index->getLibPaths();
            //main locale overwrites module locale and module locale overwrites library locale
            foreach ($libs as $lib) {
                $this->translationPaths[] = $lib.$ds.'locales'.$ds.$xliff;
            }
            $this->translationPaths[] = APPLICATION_PATH.$ds.'modules'.$ds.
                    APPLICATION_MODULE.$ds.'locales'.$ds.$xliff;
            $this->translationPaths[] =  $session->runtimeOptions->dir->locales.
                    $ds.$xliff; 
            foreach ($this->translationPaths as $key => &$path) {
                if(!file_exists($path)){
                    unset($this->translationPaths[$key]);
                }
            }
        }
        return $this->translationPaths;
    }

    /**
     * 
     * @return string
     */
    public function getLogPath() {
        $session = new Zend_Session_Namespace();
        if(empty($this->logPath)){
            $this->logPath = $session->runtimeOptions->dir->logs.'/notFoundTranslation-'.
        APPLICATION_MODULE.'-'.$this->sourceLang.'-'.$this->getTargetLang().'.xliff';
        }
       return $this->logPath;
    }
    /**
     * Returns the language part of the locale
     *
     * @return string
     */
    public function getTargetLang() {
        return $this->targetLang;
    }
    /**
     *
     * @return string
     */
    public function getSourceCodeLocale() {
        $session = new Zend_Session_Namespace();
        return $session->runtimeOptions->translation->sourceCodeLocale;
    }
    /**
     * Returns the language part of the locale
     *
     * @return string
     */
    public function getSourceLang() {
        return $this->sourceLang;
    }
}
