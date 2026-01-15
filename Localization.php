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
use ZfExtended_Zendoverwrites_Translate;

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
     * The locale being used for strings not found in other locales
     */
    public const FALLBACK_LOCALE = 'en';

    /**
     * Additional locales translate5 is shipped with
     */
    public const SECONDARY_LOCALES = ['en', 'fr', 'it'];

    /**
     * The locales, that are selectable for the GUI
     * These can be the sum of PRIMARY_LOCALE + SECONDARY_LOCALES
     * locales can be deactivated when e.g. not yet complete
     */
    public const FRONTEND_LOCALES = [
        'en' => 'Englisch',
        'de' => 'Deutsch',
        'fr' => 'Französisch',
        'it' => 'Italienisch',
    ];

    /**
     * The default source locale. A locale, that never can be a localized language
     * that is encoded in the PRIMARY_LOCALE ZXLIFF files as source ...
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
     * Global API to ranslate localized strings in translate5
     */
    public static function trans(string $messageId, string $locale = null): string
    {
        return ZfExtended_Zendoverwrites_Translate::getInstance()->_($messageId, $locale);
    }

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
     *  or by fallback-locale
     */
    public static function getLocale(?string $desiredLocale = null): string
    {
        // If $desiredLocale is given, and it's valid and available - use it
        if ($desiredLocale !== null && self::isAvailableLocale($desiredLocale)) {
            return $desiredLocale;
        }

        return self::getApplicationLocale(true);
    }

    /**
     * Retrieves the application-locale representing the desired fallback-locale for texts where no user is involved
     */
    public static function getApplicationLocale(bool $evaluateBrowserLocale = false): string
    {
        $rop = \Zend_Registry::get('config')->runtimeOptions;
        if (! empty($rop->translation->applicationLocale) &&
            Zend_Locale::isLocale((string) $rop->translation->applicationLocale) &&
            array_key_exists((string) $rop->translation->applicationLocale, self::FRONTEND_LOCALES)
        ) {
            return (string) $rop->translation->applicationLocale;
        } elseif (! empty($rop->translation->applicationLocale)) {
            error_log('Configured runtimeOptions.translation.applicationLocale is not valid, using “'
                . self::FALLBACK_LOCALE . '”');
        }

        if ($evaluateBrowserLocale) {
            return self::getLocaleFromBrowser();
        }

        return self::FALLBACK_LOCALE;
    }

    /**
     * Retrieves the desired locale from the browser - if available
     */
    protected static function getLocaleFromBrowser(): string
    {
        $localeObj = new Zend_Locale();
        $userPrefLangs = array_keys($localeObj->getBrowser());
        if (count($userPrefLangs) > 0) {
            foreach ($userPrefLangs as $testLocale) {
                $locale = new Zend_Locale($testLocale);
                if (array_key_exists($locale->getLanguage(), self::FRONTEND_LOCALES)) {
                    return $locale->getLanguage();
                }
            }
        }

        return self::FALLBACK_LOCALE;
    }

    /**
     * Retrieves, if the passed locale-string is a valid locale in translate5
     */
    public static function isAvailableLocale(string $language): bool
    {
        if (Zend_Locale::isLocale($language)) {
            $locale = new Zend_Locale($language);

            return array_key_exists($locale->getLanguage(), self::FRONTEND_LOCALES) &&
                $locale->getLanguage() === $language;
        }

        return false;
    }
}
