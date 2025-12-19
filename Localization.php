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

namespace MittagQI\ZfExtended;

use Zend_Locale;

/**
 * Central class to manage the App Localizations
 */
final class Localization
{
    /**
     * The primary locale translations exist in the code with
     * Must match runtimeOptions.translation.sourceCodeLocale
     */
    public const PRIMARY_LOCALE = 'de';

    /**
     * Additional locales translate5 is shipped with
     */
    public const SECONDARY_LOCALES = ['en', 'fr'];

    /**
     * The locales, that are selectable for the GUI
     * These can be the sum of PRIMARY_LOCALE + SECONDARY_LOCALES
     * but locales can be deactivated when not yet complete or have deficiancies
     */
    public const FRONTEND_LOCALES = [
        'de' => 'Deutsch',
        'en' => 'Englisch',
        'fr' => 'Französisch',
    ];

    /**
     * The default source locale
     * that is encoded in the
     */
    public const DEFAULT_SOURCE_LOCALE = 'ha';

    /**
     * The file-extension for the used XLIFF translation-files
     */
    public const FILE_EXTENSION = 'zxliff';

    /**
     * The file-extension preceided by "." for the used translation-files
     */
    public const FILE_EXTENSION_WITH_DOT = '.zxliff';

    /**
     * Retrieves all locales that are available as GUI languages in translate5
     * @return string[]
     */
    public static function getAvailableLocales(): array
    {
        return array_keys(self::FRONTEND_LOCALES);
    }

    /**
     * Retrieves the locales model for the frontend
     */
    public static function getFrontendLocales(\ZfExtended_Zendoverwrites_Translate $translate): string
    {
        $locales = [];
        foreach (self::FRONTEND_LOCALES as $locale => $name) {
            $locales[$locale] = $translate->_($name);
        }

        return json_encode($locales, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Retrieves the GUI locale:
     *  by argument - if it's a valid and available locale,
     *  or by applicationLocale-config,
     *  or by browser,
     *  or by fallbackLocale-config
     */
    public static function getLocale(?string $desiredLocale = null): string
    {
        // Get [localeCode => localeName] pairs for valid locales
        $available = [];
        foreach (self::getAvailableLocales() as $locale) {
            $available[$locale] = true;
        }
        // TODO FIXME: shouldn't we take the locales found by the Translate Adapter into account ?
        // $available = ZfExtended_Zendoverwrites_Translate::getInstance()->getAvailableTranslations();

        // If $desiredLocale is given, and it's valid and available - use it
        if ($desiredLocale) {
            if (Zend_Locale::isLocale($desiredLocale)) {
                if (isset($available[$desiredLocale])) {
                    return $desiredLocale;
                }
            }
        }

        // Get runtimeOptions and fallback locale from there
        $rop = \Zend_Registry::get('config')->runtimeOptions;
        $fallback = $rop->translation->fallbackLocale;

        // If fallback is not available - then use first among available
        if (! isset($available[$fallback])) {
            $fallback = key($available);
        }

        // If app locate is set
        if ($appLocale = $rop->translation->applicationLocale) {
            // If it's valid - use that
            if (Zend_Locale::isLocale($appLocale) && isset($available[$appLocale])) {
                return $appLocale;
                // Else - report that and use fallback
            } else {
                error_log(
                    'Configured runtimeOptions.translation.applicationLocale is no valid locale, using ' . $fallback
                );

                return $fallback;
            }
        }

        // Else use browser language or fallback
        return self::getLocaleFromBrowser() ?: $fallback;
    }

    /**
     * Retrieves the desired locale from the browser - if available
     */
    protected static function getLocaleFromBrowser(): ?string
    {
        $config = \Zend_Registry::get('config');
        $localeObj = new Zend_Locale();
        $userPrefLangs = array_keys($localeObj->getBrowser());
        if (count($userPrefLangs) > 0) {
            //Prüfe, ob für jede locale, ob eine xliff-Datei vorhanden ist - wenn nicht fallback
            foreach ($userPrefLangs as $testLocale) {
                $testLocaleObj = new Zend_Locale($testLocale);
                $testLang = $testLocaleObj->getLanguage();
                $localePath = $config->runtimeOptions->dir->locales . DIRECTORY_SEPARATOR . $testLang .
                    self::FILE_EXTENSION_WITH_DOT;
                if (file_exists($localePath)) {
                    return $testLang;
                }
            }
        }

        return null;
    }
}
