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
 * @method string getId()
 * @method void setId(integer $id)
 * @method string getLangName()
 * @method void setLangName(string $langName)
 * @method string getLcid()
 * @method void setLcid(string $lcid)
 * @method string getRfc5646()
 * @method void setRfc5646(string $lang)
 * @method string getIso3166Part1alpha2()
 * @method void setIso3166Part1alpha2(string $lang)
 * @method string getSublanguage()
 * @method void setSublanguage(string $sublang)
 * @method string getRtl()
 * @method void setRtl(boolean $rtl)
 * @method string getIso6393()
 * @method void setIso6393(string $lang)
 */
abstract class ZfExtended_Languages extends ZfExtended_Models_Entity_Abstract
{
    /**
     * Retrieves the lowercased primary language of a RFC 5646 language (e.g. "en" from "en-GB")
     */
    public static function primaryCodeByRfc5646(string $rfc5646): string
    {
        $parts = explode('-', $rfc5646);

        return strtolower($parts[0]);
    }

    /**
     * Retrieves the lowercased sub language of a RFC 5646 language (e.g. "gb" from "en-GB" or empty string)
     */
    public static function sublangCodeByRfc5646(string $rfc5646): string
    {
        return strtolower(
            preg_replace('~^' . self::primaryCodeByRfc5646($rfc5646) . '-?~', '', $rfc5646)
        );
    }

    public const LANG_TYPE_ID = 'id';

    public const LANG_TYPE_RFC5646 = 'rfc5646';

    public const LANG_TYPE_LCID = 'lcid';

    /**
     * @var Zend_Cache_Core
     */
    protected $memCache;

    public function __construct()
    {
        parent::__construct();
        $this->memCache = Zend_Cache::factory('Core', new ZfExtended_Cache_MySQLMemoryBackend(), [
            'automatic_serialization' => true,
        ]);
    }

    /**
     * Gets the major RFC5646 language (e.g. "en", "ja", ...)
     */
    public function getMajorRfc5646(): string
    {
        return static::primaryCodeByRfc5646($this->getRfc5646());
    }

    /**
     * Lädt die Sprache anhand dem übergebenen Sprachkürzel (nach RFC5646)
     * @param string $lang
     * @return Zend_Db_Table_Row_Abstract | null
     */
    public function loadByRfc5646($lang)
    {
        return $this->loader($lang, self::LANG_TYPE_RFC5646);
    }

    /**
     * Lädt die Sprache anhand der übergebenen LCID
     * @param string $lcid
     * @return Zend_Db_Table_Row_Abstract | null
     */
    public function loadByLcid($lcid)
    {
        return $this->loader($lcid, self::LANG_TYPE_LCID);
    }

    /**
     * loads the language by the given DB ID
     * @param int $id
     * @return Zend_Db_Table_Row_Abstract | null
     */
    public function loadById($id)
    {
        return $this->loader($id, self::LANG_TYPE_ID);
    }

    /**
     * loads the languages by the given DB ID's
     * @return array | null
     */
    public function loadByIds($ids)
    {
        return $this->loaderByIds($ids, self::LANG_TYPE_ID);
    }

    /**
     * Load languages by rfc values
     * @return array
     */
    public function loadByRfc(array $rfc)
    {
        $s = $this->db->select();
        $s->where('lower(rfc5646) IN(?)', $rfc);
        $retval = $this->db->fetchAll($s)->toArray();
        if (empty($retval)) {
            return [];
        }

        return $retval;
    }

    /**
     * @param mixed $lang
     * @param string $field
     * @return Zend_Db_Table_Row_Abstract | null
     */
    protected function loader($lang, $field)
    {
        $s = $this->db->select();
        $field = strtolower($field);
        if ($field == self::LANG_TYPE_ID || $field == self::LANG_TYPE_LCID) {
            $s->where($field . ' = ?', $lang);
        } else {
            $s->where('lower(' . $field . ') = ?', strtolower($lang));
        }

        $this->row = $this->db->fetchRow($s);
        if (empty($this->row)) {
            $this->notFound('#by' . ucfirst($field), $lang);
        }

        return $this->row;
    }

    /**
     * @param string $field
     * @return array | null
     */
    protected function loaderByIds($langs, $field)
    {
        $s = $this->db->select();
        //FIXME convert that to array only after merge of TRANSLATE-2834
        if (is_array($langs)) {
            $s->where($field . ' IN(?)', $langs);
        } else {
            $s->where($field . ' IN(' . $langs . ')');
        }
        $s->order('rfc5646 ASC');
        $retval = $this->db->fetchAll($s)->toArray();
        if (empty($retval)) {
            $this->notFound('#by' . ucfirst($field), $langs);
        }

        return $retval;
    }

    /**
     * Returns all configured languages in an array for displaying in frontend
     * in format :
     *
     * [Mazedonisch] => Array
     *               (
     *                  [id] => 301
     *                  [value] => mk
     *                  [text] => Mazedonisch (mk)
     *                )
     */
    public function getAvailableLanguages()
    {
        $langs = $this->loadAll();
        $result = [];
        foreach ($langs as $lang) {
            $name = $lang['langName'];
            $result[$name] = [
                'id' => $lang['id'],
                'value' => $lang['rfc5646'],
                'text' => $name . ' (' . $lang['rfc5646'] . ')',
            ];
        }
        ksort($result); //sort by name of language
        if (empty($result)) {
            throw new Zend_Exception('No languages defined. Please use /docs/003fill-LEK-languages-after-editor-sql or define them otherwhise.');
        }

        return array_values($result);
    }

    /**
     * Gibt die interne Sprach ID anhand der übergebenen Sprache im spezifizierten Typ zurück
     * @param mixed $lang
     * @param string $type
     */
    public function getLangId($lang, $type = self::LANG_TYPE_RFC5646)
    {
        $this->loadLang($lang, $type);

        return $this->getId();
    }

    /**
     * Gibt die interne Sprach ID (PK der Sprach Tabelle) zu einer LCID zurück
     * @param int $lcid LCID, wie in Tabelle languages hinterlegt
     * @return int id der gesuchten Sprache
     */
    public function getLangIdByLcid($lcid)
    {
        $this->loadByLcid($lcid);

        return $this->getId();
    }

    /**
     * Gibt die interne Sprach ID (PK der Sprach Tabelle) zu einem Sprachkürzel nach RFC5646 zurück
     * @param int $lang Sprachkürzel nach RFC5646, wie in Tabelle languages hinterlegt
     * @return int id der gesuchten Sprache
     */
    public function getLangIdByRfc5646($lang)
    {
        $this->loadByRfc5646($lang);

        return $this->getId();
    }

    /**
     * Gibt die interne default Sublanguage zu einem Sprachkürzel nach RFC5646 zurück
     * @param int $lang Sprachkürzel nach RFC5646, wie in Tabelle languages hinterlegt
     * @return string sublanguage der gesuchten Sprache
     */
    public function getSublanguageByRfc5646($lang)
    {
        $this->loadByRfc5646($lang);

        return $this->getSublanguage();
    }

    /**
     * @param string $lang Sprachkürzel nach RFC5646, wie in Tabelle languages hinterlegt
     * @return string mainlanguage der gesuchten Sprache
     * @deprecated: Use static ::primaryCodeByRfc5646 API
     * Gibt den Sprachteil von RFC5646-Sprachkürzel zurück (unabh. vom Land),
     * z.B. für "de" für "de-AT" oder "de" für "de" oder "sr" für "Sr-Cyrl".
     */
    public function getMainlanguageByRfc5646($lang)
    {
        return static::primaryCodeByRfc5646($lang);
    }

    /**
     * Gibt die interne ISO_3166-1_alpha-2 zu einem Sprachkürzel nach RFC5646 zurück
     * @param int $lang Sprachkürzel nach RFC5646, wie in Tabelle languages hinterlegt
     * @return string ISO_3166-1_alpha-2 der gesuchten Sprache
     */
    public function getIso3166Part1alpha2ByRfc5646($lang)
    {
        $this->loadByRfc5646($lang);

        return $this->getIso3166Part1alpha2();
    }

    /**
     * Gibt ein Language-Entity anhand der übergebenen Sprache im spezifizierten Typ zurück
     * @param mixed $lang
     * @param string $type
     * @return Zend_Db_Table_Row_Abstract | null
     */
    public function loadLang($lang, $type = self::LANG_TYPE_RFC5646)
    {
        switch ($type) {
            case self::LANG_TYPE_ID:
                return $this->loadById($lang);
            case self::LANG_TYPE_LCID:
                return $this->loadByLcid($lang);
            case self::LANG_TYPE_RFC5646:
                return $this->loadByRfc5646($lang);
            default:
                return $this->loadLang($lang, $this->getAutoDetectedType($lang));
        }
    }

    /**
     * Versucht anhand der übergebenen Sprache die Art der Sprachspezifikation zu bestimmen
     * @param string $lang
     * @return string
     */
    public function getAutoDetectedType($lang)
    {
        if (is_int($lang)) {
            return self::LANG_TYPE_LCID;
        }

        return self::LANG_TYPE_RFC5646;
    }

    /**
     * Reorders a RFC5646 language list based to the order given in $preordered (ex. de-De,fr-fr,it,mk ...)
     * @param array $languages
     * @return array
     */
    public function orderLanguages($languages, array $preorderd)
    {
        if (empty($preorderd)) {
            return $languages;
        }
        foreach ($preorderd as $lng) {
            $oldIndex = array_search($lng, array_column($languages, 'value'));
            if (! $oldIndex) {
                continue;
            }
            $newIndex = array_search($lng, $preorderd);
            $tmp = $languages[$newIndex];
            $languages[$newIndex] = $languages[$oldIndex];
            $languages[$oldIndex] = $tmp;
        }

        return $languages;
    }

    /**
     * Return language rfc5646 value for given language id
     * @param int $langId
     * @return string
     */
    public function loadLangRfc5646($langId)
    {
        $this->loadById($langId);

        return $this->getRfc5646();
    }

    /**
     * Find languages which are belonging to the same language group
     * Ex:
     * when the $rfc = "de"
     * the result will be the ids from the de, de-at, de-ch, de-de, de-lu etc...
     * @param string $rfc
     */
    public function findLanguageGroup($rfc): array
    {
        $rfc = explode('-', $rfc);
        $rfc = strtolower($rfc[0]);
        $s = $this->db->select();
        $s->where('lower(rfc5646) = ?', $rfc);
        $s->orWhere('lower(rfc5646) LIKE ?', $rfc . '-%');
        $s->order('length(rfc5646)');
        $retval = $this->db->fetchAll($s)->toArray();
        if (empty($retval)) {
            return [];
        }

        return $retval;
    }

    /**
     * Return fuzzy languages for the given language id.
     *
     * Examples:
     *    de -> de, de-DE, de-AT, de-CH
     *
     *    $includeMajor == false
     *    de-DE -> de-DE, de-AT, de-CH
     *
     *    $includeMajor == true
     *    de-DE -> de, de-DE, de-AT, de-CH
     *
     * @param int $id
     * @param string $field the field to be returned
     * @param boolean $includeMajor include major language when fuzzy matching for sub-languages (no effect on querying major itself!)
     * @throws Zend_Cache_Exception
     */
    public function getFuzzyLanguages($id, string $field = 'id', bool $includeMajor = false): array
    {
        $cacheId = __FUNCTION__ . '_' . $id . '_' . $field . '_' . var_export($includeMajor, true) . '_includeMajor';
        $result = $this->memCache->load($cacheId);
        if ($result !== false) {
            return $result;
        }

        $rfc = $this->loadLangRfc5646($id); //loads the language internally
        //if it is a sub language (de-DE), take only that but include the general (major) language (de) if set via param
        if (str_contains($rfc, '-')) {
            $result = [$this->get($field)];
            if ($includeMajor) {
                $major = $this->findMajorLanguage($rfc);
                if (! empty($major)) {
                    $result[] = $major[$field];
                }
            }
        } // if it is general language, include all sub languages too
        else {
            $result = array_column($this->findLanguageGroup($rfc), $field);
        }
        $this->memCache->save($result, $cacheId);

        return $result;
    }

    /**
     * Search languages by given search string.
     * The search will provide any match on rfc5646 field.
     */
    public function search(string $searchString, array $fields = []): array
    {
        $s = $this->db->select();
        if (! empty($fields)) {
            $s->from($this->tableName, $fields);
        }
        $s->where('lower(rfc5646) LIKE lower(?)', '%' . $searchString . '%');

        return $this->db->fetchAll($s)->toArray();
    }

    /**
     * Load all languages where the return array will be with $key(lek_languages field) as key
     * and $value(lek_languages field) as value
     *
     * @param bool $lowercase : lowercase key and value
     */
    public function loadAllKeyValueCustom(string $key, string $value, bool $lowercase = false): array
    {
        $keyValuePairs = [];
        if (! isset($key) || ! isset($value)) {
            return $keyValuePairs;
        }
        $languages = $this->loadAll();
        foreach ($languages as $l) {
            if ($lowercase) {
                $keyValuePairs[strtolower($l[$key] ?? '')] = strtolower($l[$value] ?? '');
            } else {
                $keyValuePairs[$l[$key]] = $l[$value];
            }
        }

        return $keyValuePairs;
    }

    /**
     * Load all languages for front-end display (language name + (rfc value) ).
     * Language array result layout:
     *  [
     *      "0"=>" language id ",
     *      "1"=>" language name + (rfc)",
     *      "2"=>" rtl ",
     *      "3"=>" rfc ",
     *      "4"=>" iso3166Part1alpha2 ", // 'public/modules/editor/images/flags/{iso3166Part1alpha2}.png'
     * ]
     * If field key is provided, this lek_languages field will be used as key for each language in the return array.
     * If optional param $onlyName is sumbitted with TRUE, only the name without rfc5646 in brackets will be returned
     *
     * @throws Zend_Exception
     */
    public function loadAllForDisplay(string $fieldKey = '', bool $onlyName = false): array
    {
        $translate = ZfExtended_Zendoverwrites_Translate::getInstance();
        $langs = $this->loadAll();
        $result = [];
        foreach ($langs as $lang) {
            $name = $translate->_($lang['langName']);
            $key = empty($fieldKey) ? $name : $lang[$fieldKey];
            $tempName = $name . (($onlyName !== true) ? ' (' . $lang['rfc5646'] . ')' : '');
            $result[$key] = [$lang['id'], $tempName, $lang['rtl'], $lang['rfc5646'], $lang['iso3166Part1alpha2']];
        }
        ksort($result); //sort by name of language
        if (empty($result)) {
            throw new Zend_Exception('No languages defined. ' .
                'Please use /docs/003fill-LEK-languages-after-editor-sql or define them otherwhise.');
        }

        return empty($fieldKey) ? array_values($result) : $result;
    }

    /**
     * Find mayor language by given sub langauge.
     * Ex: "de-CH" will find "de"
     * @return array
     */
    public function findMajorLanguage(string $rfcSub)
    {
        $rfcSub = explode('-', $rfcSub);
        $rfcSub = $rfcSub[0];
        $mayor = $this->loadByRfc([$rfcSub]);

        return empty($mayor) ? [] : reset($mayor);
    }
}
