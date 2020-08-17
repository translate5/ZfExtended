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
/**
 * Klasse zur Initialisierung aller Formulare
 *
 * - liest Form aus ini ein entsprechend der Konvention "controllerAction" des aufrufenden Controllers und der aufrufenden Action
 * - Kinder können auch zur Datenvalidierung im Modell herangezogen werden
 */
class  ZfExtended_Zendoverwrites_Translate extends Zend_Translate
{
    /**
     * @var boolean if the to translated string should be json-encoded before return
     */
    protected $_jsonEncode = false;
    
    protected static $_instance = null;
    
    /**
     * @var string the language part of the locale
     */
    protected $sourceLang;
    
    /**
     * @var string the language part of the locale
     */
    protected $targetLang;
    
    /**
     * @var string
     */
    protected $logPath;
    
    /**
     * @var array
     */
    protected $translationPaths;
    
    /**
     * @var Zend_Config
     */
    protected $config;
    
    public function __construct($targetLang = null) {
        $this->config = Zend_Registry::get('config');
        
        //this is to have an translation adapter set if an error occurs inside translation
        //process before translation adapter is set - but errorcontroller needs one
        parent::__construct('Zend_Translate_Adapter_Array', array('' => ''), 'en');

        //store translation instance only in registry if no targetLang is given and its therefore the default instance
        if(empty($targetLang)) {
            Zend_Registry::set('Zend_Translate', $this);
        }
        $this->setSourceLang();
        $this->setTargetLang($targetLang);
        $this->getLogPath();
        
        
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
        $cache = Zend_Registry::get('cache'); //Caching deaktiviert, da der Aufruf der selben Seite mehrmals innerhalb von Millisekunden bei der Nutzung des Caches für Zend_Translate zu Fatal Error führt
        Zend_Translate::setCache($cache);
        parent::__construct(
            array(
                'adapter' => 'ZfExtended_Zendoverwrites_Translate_Adapter_Xliff',
                'content' => $this->config->runtimeOptions->dir->locales.'/'.$this->sourceLang.'.xliff',
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
    }
    
    /**
     * always sets jsonEncode to false to ensure strings are only jsonencoded if explicitly set after instance is fetched
     * @param bool $init causes getInstance, to create the singleton new
     * @return ZfExtended_Zendoverwrites_Translate
     */
    public static function getInstance($init = false)
    {
        if (null === self::$_instance || $init) {
            //warning overwriting this method changes also the class
            //name since self point to the method defining class!
            try {
                self::$_instance = ZfExtended_Factory::get(__CLASS__);
                self::$_instance->setJsonEncode(false);
                Zend_Registry::set('Zend_Translate', self::$_instance);
            }
            catch(Exception $e){
                self::$_instance = new ZfExtended_Zendoverwrites_TranslateError($e);
                return self::$_instance;
            }
        }
        
        return self::$_instance;
    }
    
    /**
     * returns an assoc array with the available translations.
     * Key is the localeKey (de/en), value is the name of the language.
     * The name is already translated into the current language!
     * @return array
     */
    public function getAvailableTranslations() {
        $session = new Zend_Session_Namespace();
        $sourceLocale = $this->config->runtimeOptions->translation->sourceLocale;
        
        $generalHelper = ZfExtended_Zendoverwrites_Controller_Action_HelperBroker::getStaticHelper(
                        'General'
        );
        $locales = array();
        $xliffFiles = scandir($this->config->runtimeOptions->dir->locales);
        foreach ($xliffFiles as $key => &$file) {
            $pathinfo = pathinfo($file);
            if (empty($pathinfo['extension']) || $pathinfo['extension'] !== 'xliff') {
                continue;
            }
            $locale = preg_replace('"^.*-([a-zA-Z]{2,3})$"i', '\\1', $pathinfo['filename']);
            if ($locale == $sourceLocale) {
                continue;
            }
            Zend_Locale::setCache(Zend_Registry::get('cache'));
            $lang = Zend_Locale::getTranslation($locale, 'language', $session->locale);
            $locales[$locale] = $lang;
        }
        return $generalHelper->natcasesortUtf($locales);
    }
    
    public function getXliffStartString() {
        return '<?xml version="1.0" ?>
<xliff version=\'1.1\' xmlns=\'urn:oasis:names:tc:xliff:document:1.1\'>
<!-- the transunit-ID should contain the base64-encoded source-string als used in the php source-code. This ID is used for matching. -->
<!-- html-tags inside the target-string must not be encoded as they should regarding xliff but should be left as plain hmtl. At this stage ZfExtended does not support xliff inline tags -->
 <file original=\'php-sourcecode\' source-language=\''.$this->getSourceLang().
                '\' target-language=\''.$this->getTargetLang().'\' datatype=\'php\' xml:space=\'preserve\'>
  <body>';
    }
    public function getXliffEndString() {
        return "
  </body>
 </file>
</xliff>";
    }

    /**
     * protected, because if public the instances has to be newly instantiated, which is unneccessary currently
     *
     * @param string $lang
     */
    protected function setSourceLang($lang = null) {
        $this->sourceLang = $lang;
        if(is_null($this->sourceLang)){
            //setzte die sourceLang in der Session  ///
            $sourceLocale = $this->config->runtimeOptions->translation->sourceLocale;
            if (!Zend_Locale::isLocale($sourceLocale)) {
                throw new Zend_Exception('$sourceLocale war keine gültige locale - fehlerhafte Konfiguration in application.ini (runtimeOptions.translation.locale)', 0 );
            }
            $session = new Zend_Session_Namespace();
            $sourceLocaleObj = new Zend_Locale($sourceLocale);
            $session->sourceLang = $sourceLocaleObj->getLanguage();
            $this->sourceLang = $session->sourceLang;
        }
    }
    /**
     * protected, because if public the instances has to be newly instantiated, which is unneccessary currently
     *
     * @param string $lang
     */
    protected function setTargetLang($lang = null) {
        $this->targetLang = $lang;
        if(is_null($this->targetLang)){
            $targetLocaleObj = Zend_Registry::get('Zend_Locale');
            $this->targetLang = $targetLocaleObj->getLanguage();
        }
    }
    
    protected function addTranslations() {
        $paths = $this->getTranslationPaths();
        foreach ($paths as $path) {
            $this->addTranslation(array(
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
        if(!empty($this->translationPaths)){
            return $this->translationPaths;
        }
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
        $this->translationPaths[] = APPLICATION_PATH.$ds.'modules'.$ds.APPLICATION_MODULE.$ds.'locales'.$ds.$xliff;
        $this->translationPaths[] =  $this->config->runtimeOptions->dir->locales.$ds.$xliff;
        
        $this->addPluginPaths($xliff);
        
        // add client-specific Translations
        $this->translationPaths[] = APPLICATION_PATH.$ds.'..'.$ds.'client-specific'.$ds.'locales'.$ds.$xliff;
        
        foreach ($this->translationPaths as $key => &$path) {
            if(!file_exists($path)){
                unset($this->translationPaths[$key]);
            }
        }
        return $this->translationPaths;
    }

    /**
     * Adds the locales of the plugins - if any - to the locales system
     * @param string $xliff
     */
    protected function addPluginPaths($xliff) {
        $pluginmanager = Zend_Registry::get('PluginManager');
        /* @var $pluginmanager ZfExtended_Plugin_Manager */
        $paths = $pluginmanager->getActiveLocalePaths();
        if(empty($paths)) {
            return;
        }
        foreach($paths as $path) {
            $this->translationPaths[] = $path.'/'.$xliff;
        }
    }
    
    /**
     *
     * @return string
     */
    public function getLogPath() {
        if(empty($this->logPath)){
            $this->logPath = $this->config->runtimeOptions->dir->logs.'/notFoundTranslation-'.
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
        return $this->config->runtimeOptions->translation->sourceCodeLocale;
    }
    /**
     * Returns the language part of the locale
     *
     * @return string
     */
    public function getSourceLang() {
        return $this->sourceLang;
    }
    
    /***
     *
     * @param string $s: string to be translated
     * @param string $locale: language locale
     * @throws Zend_Exception
     * @return string
     */
    public function _($s, $locale = null){
        $s = parent::_($s, $locale);
        if($this->_jsonEncode){
            $s = json_encode($s,JSON_HEX_APOS);
            $length = strlen($s);
            if($s[0] =='"' && $s[$length-1] == '"'){
                $s = substr($s, 1, -1);//we do not use trim here, because then a second trailing quote would be removed also
            }
            else{
                throw new Zend_Exception('Beginning and trailing Quotes of jsonEncode could not be removed properly.');
            }
        }
        return $s;
    }
    public function setJsonEncode(bool $val) {
        $this->_jsonEncode = $val;
    }
    public function getJsonEncode(){
        return $this->_jsonEncode;
    }

    /**
     * Singleton Instanz auf NULL setzen, um sie neu initialiseren zu können
     *
     * @return void
     */
    public static function reset() {
        self::$_instance = NULL;
    }
}
