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
 * @method ZfExtended_Zendoverwrites_Translate_Adapter_Xliff getAdapter()
 */
class ZfExtended_Zendoverwrites_Translate extends Zend_Translate
{
    /**
     * @var ZfExtended_Zendoverwrites_Translate|null
     */
    protected static $_instance = null;

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
            } catch (Exception $e) {
                self::$_instance = new ZfExtended_Zendoverwrites_TranslateError($e);

                return self::$_instance;
            }
        }

        return self::$_instance;
    }

    /**
     * Singleton Instanz auf NULL setzen, um sie neu initialiseren zu können
     */
    public static function reset()
    {
        self::$_instance = null;
    }

    /**
     * @var boolean if the to translated string should be json-encoded before return
     */
    protected $_jsonEncode = false;

    /**
     * @var string the language part of the locale
     */
    protected $sourceLang;

    /**
     * @var string the language part of the locale
     */
    protected $targetLang;

    protected array $translationPathes = [];

    /**
     * @var Zend_Config
     */
    protected $config;

    /**
     * @throws Zend_Exception
     * @throws Zend_Log_Exception
     * @throws Zend_Translate_Exception
     */
    public function __construct($targetLang = null)
    {
        $this->config = Zend_Registry::get('config');

        // this is to have a translation adapter set if an error occurs inside translation
        // process before translation adapter is set - but errorcontroller needs one
        parent::__construct(
            'Zend_Translate_Adapter_Array',
            [
                '' => '',
            ],
            Localization::FALLBACK_LOCALE
        );

        //store translation instance only in registry if no targetLang is given and its therefore the default instance
        if (empty($targetLang)) {
            Zend_Registry::set('Zend_Translate', $this);
        }
        $this->setSourceLang();
        $this->setTargetLang($targetLang);

        $config = [
            'adapter' => ZfExtended_Zendoverwrites_Translate_Adapter_Xliff::class,
            'content' => $this->config->runtimeOptions->dir->locales . '/' . $this->sourceLang .
                Localization::FILE_EXTENSION_WITH_DOT,
            'locale' => $this->sourceLang,
            'disableNotices' => true,
            'logUntranslated' => true,
            'logMessage' => 'Localization: translation missing for id: "%id%", string: "%message%"',
            'useId' => true,
        ];

        if (ZfExtended_Utils::isDevelopment()) {
            $writer = new Zend_Log_Writer_Stream($this->getLogPath());
            $writer->setFormatter(new Zend_Log_Formatter_Simple('%message%' . PHP_EOL));
            $config['log'] = new Zend_Log($writer);
        } else {
            //cache translations in production
            $cache = Zend_Registry::get('cache');
            Zend_Translate::setCache($cache);
        }

        parent::__construct($config);
        $this->addTranslations();
    }

    /**
     * returns an assoc array with the available translations.
     * Key is the localeKey (de/en), value is the name of the language.
     * The name is already translated into the current language!
     * @return array
     */
    public function getAvailableTranslations()
    {
        $session = new Zend_Session_Namespace();
        $sourceLocale = Localization::DEFAULT_SOURCE_LOCALE;

        $locales = [];
        $xliffFiles = scandir($this->config->runtimeOptions->dir->locales);
        foreach ($xliffFiles as $key => &$file) {
            $pathinfo = pathinfo($file);
            if (empty($pathinfo['extension']) || $pathinfo['extension'] !== Localization::FILE_EXTENSION) {
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
        asort($locales, SORT_NATURAL);

        return $locales;
    }

    /**
     * protected, because if public the instances has to be newly instantiated, which is unneccessary currently
     *
     * @param string $lang
     */
    protected function setSourceLang($lang = null)
    {
        $this->sourceLang = $lang;
        if (is_null($this->sourceLang)) {
            // sets a general source language - that must never be a target language
            $session = new Zend_Session_Namespace();
            $sourceLocaleObj = new Zend_Locale(Localization::DEFAULT_SOURCE_LOCALE);
            $session->sourceLang = $sourceLocaleObj->getLanguage();
            $this->sourceLang = $session->sourceLang;
        }
    }

    /**
     * protected, because if public the instances has to be newly instantiated, which is unneccessary currently
     *
     * @param string $lang
     */
    protected function setTargetLang($lang = null)
    {
        $this->targetLang = $lang;
        if (is_null($this->targetLang)) {
            if (Zend_Registry::isRegistered('Zend_Locale')) {
                $targetLocaleObj = Zend_Registry::get('Zend_Locale');
                $this->targetLang = $targetLocaleObj->getLanguage();
            } else {
                //fallback EN if no locale registered
                $this->targetLang = Localization::FALLBACK_LOCALE;
            }
        }
    }

    protected function addTranslations()
    {
        foreach ($this->getTranslationPathes($this->getTargetLang()) as $path) {
            $this->addTranslation([
                'content' => $path,
                'locale' => $this->getTargetLang(),
            ]);
        }
    }

    /**
     * later pathes should overwrite previous ones
     */
    public function getTranslationPathes(string $language): array
    {
        if (array_key_exists($language, $this->translationPathes)) {
            return $this->translationPathes[$language];
        }

        $this->translationPathes[$language] = [];
        $dirs = $this->getTranslationDirectories();

        foreach ($dirs as $path) {
            $path = $path . $language . Localization::FILE_EXTENSION_WITH_DOT;
            if (file_exists($path)) {
                $this->translationPathes[$language][] = $path;
            }
        }

        return $this->translationPathes[$language];
    }

    /**
     * Returns the directories containing XLF files with trailing slash
     * @throws Zend_Exception
     */
    public function getTranslationDirectories(): array
    {
        $directories = [];
        $index = ZfExtended_BaseIndex::getInstance();
        $libs = $index->getLibPaths();
        //main locale overwrites module locale and module locale overwrites library locale
        foreach ($libs as $lib) {
            $directories[] = $lib . '/locales/';
        }
        $directories[] = APPLICATION_PATH . '/modules/' . APPLICATION_MODULE . '/locales/';

        $directories = array_merge($directories, array_values($this->addPluginPathes()));

        // add client-specific Translations
        $directories[] = APPLICATION_ROOT . '/client-specific/locales/';

        return array_values(array_unique($directories));
    }

    /**
     * Adds the locales of the plugins - if any - to the locales system
     * @throws Zend_Exception
     */
    protected function addPluginPathes(): array
    {
        if (! Zend_Registry::isRegistered('PluginManager')) {
            return [];
        }
        /** @var ZfExtended_Plugin_Manager $pluginmanager */
        $pluginmanager = Zend_Registry::get('PluginManager');
        $paths = $pluginmanager->getActiveLocalePaths();
        if (empty($paths)) {
            return [];
        }

        return array_map(function ($item) {
            return rtrim($item, '/') . '/';
        }, $paths);
    }

    /**
     * @return string
     */
    public function getLogPath()
    {
        return APPLICATION_DATA . '/logs/l10n.log';
    }

    /**
     * Returns the language part of the locale
     *
     * @return string
     */
    public function getTargetLang()
    {
        return $this->targetLang;
    }

    /**
     * Returns the language part of the locale
     *
     * @return string
     */
    public function getSourceLang()
    {
        return $this->sourceLang;
    }

    /**
     * @return string
     */
    public function _($s, $locale = null)
    {
        $s = $this->getAdapter()->translate($s, $locale);
        // if configured, we JSON-encode the translation to serve JSON translation-files
        if ($this->_jsonEncode) {
            $s = json_encode($s, JSON_HEX_APOS);
            $length = strlen($s);
            if ($s[0] == '"' && $s[$length - 1] == '"') {
                //we do not use trim here, because then a second trailing quote would be removed also
                $s = substr($s, 1, -1);
            }
        }

        return $s;
    }

    public function setJsonEncode(bool $val)
    {
        $this->_jsonEncode = $val;
    }

    public function getJsonEncode()
    {
        return $this->_jsonEncode;
    }
}
