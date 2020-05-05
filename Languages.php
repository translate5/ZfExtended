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

/**
 *
 * @method int getId() getId()
 * @method void setId() setId(integer $id)
 * @method int getLangName() getLangName()
 * @method void setLangName() setLangName(string $langName)
 * @method int getLcid() getLcid()
 * @method void setLcid() setLcid(string $lcid)
 * @method string getRfc5646() getRfc5646()
 * @method void setRfc5646() setRfc5646(string $lang)
 * @method string getIso3166Part1alpha2() getIso3166Part1alpha2()
 * @method void setIso3166Part1alpha2() setIso3166Part1alpha2(string $lang)
 * @method int getSublanguage() getSublanguage()
 * @method void setSublanguage() setSublanguage(string $sublang)
 * @method int getRtl() getRtl()
 * @method void setRtl() setRtl(boolean $rtl)
 * @method string getIso6393() getIso6393()
 * @method void setIso6393() setIso6393(string $lang)
 */
abstract class ZfExtended_Languages extends ZfExtended_Models_Entity_Abstract {

    const LANG_TYPE_ID = 'id';
    const LANG_TYPE_RFC5646 = 'rfc5646';
    const LANG_TYPE_LCID = 'lcid';

    /**
     * @var Zend_Cache_Core
     */
    protected $memCache;
    
    public function __construct() {
        parent::__construct();
        $this->memCache = Zend_Cache::factory('Core', new ZfExtended_Cache_MySQLMemoryBackend(), ['automatic_serialization' => true]);
    }
    
    /**
     * Lädt die Sprache anhand dem übergebenen Sprachkürzel (nach RFC5646)
     * @param string $lang
     * @return Zend_Db_Table_Row_Abstract | null
     */
    public function loadByRfc5646($lang){
        return $this->loader($lang, 'rfc5646');
    }

    /**
     * Lädt die Sprache anhand der übergebenen LCID
     * @param string $lcid
     * @return Zend_Db_Table_Row_Abstract | null
     */
    public function loadByLcid($lcid){
        return $this->loader($lcid, 'lcid');
    }

    /**
     * loads the language by the given DB ID
     * @param int $id
     * @return Zend_Db_Table_Row_Abstract | null
     */
    public function loadById($id){
        return $this->loader($id, 'id');
    }

    /**
     * loads the languages by the given DB ID's
     * @param string $id's
     * @return Zend_Db_Table_Row_Abstract | null
     */
    public function loadByIds($ids){
        return $this->loaderByIds($ids, 'id');
    }
    
    /***
     * Load languages by rfc values
     * @param array $rfc
     * @return array
     */
    public function loadByRfc(array $rfc){
        $s = $this->db->select();
        $s->where('lower(rfc5646) IN(?)',$rfc);
        $retval = $this->db->fetchAll($s)->toArray();
        if(empty($retval)){
            return [];
        }
        return $retval;
    }
    /**
     * @param mixed $lang
     * @param string $field
     * @return Zend_Db_Table_Row_Abstract | null
     */
    protected function loader($lang, $field) {
        $s = $this->db->select();
        $s->where('lower('.$field.') = ?',strtolower($lang));
        $this->row = $this->db->fetchRow($s);
        if(empty($this->row)){
            $this->notFound('#by'.ucfirst($field), $lang);
        }
        return $this->row;
    }

    /**
     * @param mixed $lang
     * @param string $field
     * @return Zend_Db_Table_Row_Abstract | null
     */
    protected function loaderByIds($langs, $field) {
        $s = $this->db->select();
        $s->where(''.$field.' IN('.$langs.')');
        $retval = $this->db->fetchAll($s)->toArray();
        if(empty($retval)){
            $this->notFound('#by'.ucfirst($field), $langs);
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
    public function getAvailableLanguages() {
        $langs = $this->loadAll();
        $result = array();
        foreach ($langs as $lang) {
            $name =$lang['langName'];
            $result[$name] = array(
                    'id'=>$lang['id'],
                    'value'=>$lang['rfc5646'],
                    'text'=>$name.' ('.$lang['rfc5646'].')');
        }
        ksort($result); //sort by name of language
        if(empty($result)){
            throw new Zend_Exception('No languages defined. Please use /docs/003fill-LEK-languages-after-editor-sql or define them otherwhise.');
        }
        return array_values($result);
    }
    
    /**
     * Gibt die interne Sprach ID anhand der übergebenen Sprache im spezifizierten Typ zurück
     * @param mixed $lang
     * @param string $type
     */
    public function getLangId($lang, $type = self::LANG_TYPE_RFC5646) {
        $this->loadLang($lang, $type);
        return $this->getId();
    }

    /**
     * Gibt die interne Sprach ID (PK der Sprach Tabelle) zu einer LCID zurück
     * @param int $lcid LCID, wie in Tabelle languages hinterlegt
     * @return int id der gesuchten Sprache
     */
    public function getLangIdByLcid($lcid){
        $this->loadByLcid($lcid);
        return $this->getId();
    }

    /**
     * Gibt die interne Sprach ID (PK der Sprach Tabelle) zu einem Sprachkürzel nach RFC5646 zurück
     * @param int $lang Sprachkürzel nach RFC5646, wie in Tabelle languages hinterlegt
     * @return int id der gesuchten Sprache
     */
    public function getLangIdByRfc5646($lang){
        $this->loadByRfc5646($lang);
        return $this->getId();
    }
    
    /**
     * Gibt die interne default Sublanguage zu einem Sprachkürzel nach RFC5646 zurück
     * @param int $lang Sprachkürzel nach RFC5646, wie in Tabelle languages hinterlegt
     * @return string sublanguage der gesuchten Sprache
     */
    public function getSublanguageByRfc5646($lang){
        $this->loadByRfc5646($lang);
        return $this->getSublanguage();
    }
    
    /**
     * Gibt den Sprachteil von RFC5646-Sprachkürzel zurück (unabh. vom Land),
     * z.B. für "de" für "de-AT" oder "de" für "de" oder "sr" für "Sr-Cyrl".
     * @param int $lang Sprachkürzel nach RFC5646, wie in Tabelle languages hinterlegt
     * @return string mainlanguage der gesuchten Sprache
     */
    public function getMainlanguageByRfc5646($lang){
        $parts = explode("-", $lang);
        return strtolower($parts[0]);
    }
    
    /**
     * Gibt die interne ISO_3166-1_alpha-2 zu einem Sprachkürzel nach RFC5646 zurück
     * @param int $lang Sprachkürzel nach RFC5646, wie in Tabelle languages hinterlegt
     * @return string ISO_3166-1_alpha-2 der gesuchten Sprache
     */
    public function getIso3166Part1alpha2ByRfc5646($lang){
        $this->loadByRfc5646($lang);
        return $this->getIso3166Part1alpha2();
    }

    /**
     * Gibt ein Language-Entity anhand der übergebenen Sprache im spezifizierten Typ zurück
     * @param mixed $lang
     * @param string $type
     * @return Zend_Db_Table_Row_Abstract | null
     */
    public function loadLang($lang, $type = self::LANG_TYPE_RFC5646) {
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
    public function getAutoDetectedType($lang) {
        if(is_int($lang)) {
            return self::LANG_TYPE_LCID;
        }
        return self::LANG_TYPE_RFC5646;
    }

    /**
     * Reorders a RFC5646 language list based to the order given in $preordered (ex. de-De,fr-fr,it,mk ...)
     * @param array $languages
     * @param array $preorderd
     * @return array
     */
    public function orderLanguages($languages, array $preorderd){
        if(empty($preorderd)){
            return $languages;
        }
        foreach ($preorderd as $lng){
            $oldIndex = array_search($lng, array_column($languages, 'value'));
            if(!$oldIndex){
                continue;
            }
            $newIndex =array_search($lng, $preorderd);
            $tmp = $languages[$newIndex];
            $languages[$newIndex] =$languages[$oldIndex];
            $languages[$oldIndex] = $tmp;
        }
        return $languages;
    }
    
    /**
     * Return language rfc5646 value for given language id
     * @param int $langId
     * @return string
     */
    public function loadLangRfc5646($langId){
        $this->loadById($langId);
        return $this->getRfc5646();
    }
    
    /***
     * Find languages which are belonging to the same language group
     * Ex:
     * when the $rfc = "de"
     * the result will be the ids from the de-at, de-ch, de-de, de-lu etc...
     * @param string $rfc
     */
    public function findLanguageGroup($rfc){
        $rfc=explode('-',$rfc);
        $rfc=$rfc[0];
        $s = $this->db->select();
        $s->where('lower(rfc5646) LIKE lower(?)',$rfc.'%');
        $retval = $this->db->fetchAll($s)->toArray();
        if(empty($retval)){
            return [];
        }
        return $retval;
    }
    
    /***
     * Return fuzzy languages for the given language id.
     * Languages with '-' in the rfc field are not searched for fuzzy.
     *
     * ex:
     *    de -> de-DE,de-AT,de-CH,de-LI,de-LU
     *
     * @param int $id
     * @param string $field: the field name wich will be returned(see languages model for available fields)
     * @return array
     */
    public function getFuzzyLanguages($id,$field='id'){
        $cacheId = __FUNCTION__.'_'.$id.'_'.$field;
        $result = $this->memCache->load($cacheId);
        if($result !== false) {
            return $result;
        }
        
        $rfc = $this->loadLangRfc5646($id);
        //check if language fuzzy matching is needed
        if(strpos($rfc, '-') !== false){
            $result = [$id];
        }
        else {
            $result = array_column($this->findLanguageGroup($rfc), $field);
        }
        $this->memCache->save($result, $cacheId);
        return $result;
    }
    
    /***
     * Search languages by given search string.
     * The search will provide any match on langName field.
     *
     * @param string $searchString
     * @return array|array
     */
    public function search($searchString,$fields=array()) {
        $s = $this->db->select();
        if(!empty($fields)){
            $s->from($this->tableName,$fields);
        }
        $s->where('lower(langName) LIKE lower(?)','%'.$searchString.'%');
        return $this->db->fetchAll($s)->toArray();
    }
    
    /***
     * Load all languages where the return array will be with $key(lek_languages field) as key and $value(lek_languages field) as value
     *
     * @param string $key
     * @param string $value
     * @param bool $lowercase: lowercase key and value
     * @return array
     */
    public function loadAllKeyValueCustom(string $key,string $value,bool $lowercase=false){
        $rfcToIsoLanguage=array();
        if(!isset($key) || !isset($value)){
            return $rfcToIsoLanguage;
        }
        $lngs=$this->loadAll();
        foreach($lngs as $l){
            if($lowercase){
                $rfcToIsoLanguage[strtolower($l[$key])]=strtolower($l[$value]);
            }else{
                $rfcToIsoLanguage[$l[$key]]=$l[$value];
            }
        }
        return $rfcToIsoLanguage;
    }
}
