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
* @package editor
* @version 1.0
*
*/
/**
 * Diese Klasse muss mittels factoryOverwrites überschrieben werden,
* da die Herkunft der Sprachinformationen nicht Teil des Editor-Moduls ist,
* sondern vom Default-Modul gestellt werden muss.
*
* @method string getRfc5646() getRfc5646()
* @method int getLcid() getLcid()
* @method int getId() getId()
*/
class ZfExtended_Languages extends ZfExtended_Models_Entity_Abstract {

    const LANG_TYPE_ID = 'id';
    const LANG_TYPE_RFC5646 = 'rfc5646';
    const LANG_TYPE_LCID = 'lcid';

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
     * @param integer $id
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
     * in format  [Mazedonisch] => Array
     *               (
     *                  [0] => 301
     *                  [1] => mk
     *                  [2] => Mazedonisch (mk)
     *                )
     */
    public function getAvailableLanguages() {
        $langs = $this->loadAll();
        $result = array();
        foreach ($langs as $lang) {
            $name =$lang['langName'];
            $result[$name] = array($lang['id'],$lang['rfc5646'], $name.' ('.$lang['rfc5646'].')');
        }
        ksort($result); //sort by name of language
        if(empty($result)){
            throw new Zend_Exception('No languages defined. Please use /docs/003fill-LEK-languages-after-editor-sql or define them otherwhise.');
        }
        return array_values($result);
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
     * Gibt die interne Sprach ID anhand der übergebenen Sprache im spezifizierten Typ zurück
     * @param mixed $lang
     * @param unknown_type $lang
     */
    public function getLangId($lang, $type = self::LANG_TYPE_RFC5646) {
        $this->loadLang($lang, $type);
        return $this->getId();
    }

    /**
     * Gibt ein Language-Entity anhand der übergebenen Sprache im spezifizierten Typ zurück
     * @param mixed $lang
     * @param unknown_type $lang
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

    /***
     * Reorders a RFC5646 language list based to the order given in $preordered (ex. DE,FR,IT,MK ...)
     * @param array $languages
     * @param array $preorderd
     * @return array
     */
    public function orderLanguages($languages, array $preorderd){
        if(empty($preorderd)){
            return $languages;
        }
        foreach ($preorderd as $lng){
            $oldIndex = array_search($lng, array_column($languages, 1));
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
}
