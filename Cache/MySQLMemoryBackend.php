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
 * @see Zend_Cache_Backend_Interface
 */
require_once 'Zend/Cache/Backend/Interface.php';

/**
 * @see Zend_Cache_Backend
 */
require_once 'Zend/Cache/Backend.php';

class ZfExtended_Cache_MySQLMemoryBackend extends Zend_Cache_Backend implements Zend_Cache_Backend_Interface
{
    public const DATE_MYSQL = 'Y-m-d H:i:s';

    /**
     * Available options
     * @var array Available options
     */
    protected $_options = [
    ];

    /**
     * DB ressource
     *
     * @var Zend_Db_Adapter_Abstract
     */
    private $db = null;

    /**
     * Constructor
     *
     * @param array $options Associative array of options
     * @throws Zend_Exception
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);
        $this->db = Zend_Registry::get('db');
    }

    /**
     * Test if a cache is available for the given id and (if yes) return it (false else)
     *
     * @param  string  $id                     Cache id
     * @param  bool    $doNotTestCacheValidity If set to true, the cache validity won't be tested
     * @return string|false Cached datas
     */
    public function load($id, $doNotTestCacheValidity = false)
    {
        $sql = 'SELECT `content` FROM `Zf_memcache` WHERE `id` = ?';
        $params = [$id];
        if (! $doNotTestCacheValidity) {
            $sql = $sql . ' AND (`expire` = 0 OR `expire` > ? )';
            $params[] = date(self::DATE_MYSQL, time());
        }
        $res = $this->db->query($sql, $params);
        $row = $res->fetchObject();
        if ($row) {
            return $row->content;
        }

        return false;
    }

    /**
     * Updates the cache identified by $id with $value only, if lastModfied is older as the given amount of $seconds
     * returns true if the cache was updated by this request or false if not
     *
     * @param string $id
     * @param string $value
     * @param int $seconds
     * @return boolean
     */
    public function updateIfOlderThen($id, $value, $seconds)
    {
        $this->checkLength($id, $value);
        $now = time();
        $elapsed = date(self::DATE_MYSQL, $now - $seconds);
        $sql = 'INSERT INTO `Zf_memcache` (`id`, `content`, `lastModified`, `expire`)';
        $sql .= ' VALUES (?, ?, now(), date_add(now(), interval 1 hour))';
        $sql .= ' ON DUPLICATE KEY UPDATE `expire` = if(`lastModified` < ?, VALUES(`expire`), `expire`),';
        $sql .= ' `content` = if(`lastModified` < ?, VALUES(`content`), `content`)';
        $res = $this->db->query($sql, [$id, $value, $elapsed, $elapsed]);
        if ($res && $res->rowCount() > 0) {
            return true;
        }

        return false;
    }

    /**
     * Test if a cache is available or not (for the given id)
     *
     * @param string $id Cache id
     * @return mixed|false (a cache is not available) or "last modified" timestamp of the available cache record
     */
    public function test($id)
    {
        $sql = 'SELECT `lastModified` FROM `Zf_memcache` WHERE `id` = ? AND (`expire` is null OR `expire` > ? )';
        $res = $this->db->query($sql, [$id, time()]);
        $row = $res->fetchObject();
        if ($row) {
            return $row->lastModified;
        }

        return false;
    }

    /**
     * Save some string datas into a cache record
     *
     * Note : $data is always "string" (serialization is done by the
     * core not by the backend)
     *
     * @param  string $data             Datas to cache
     * @param  string $id               Cache id
     * @param  array  $tags             Array of strings, the cache record will be tagged by each string entry
     * @param  int    $specificLifetime If != false, set a specific lifetime for this cache record (null => infinite lifetime)
     * @throws Zend_Cache_Exception
     * @return boolean True if no problem
     */
    public function save($data, $id, $tags = [], $specificLifetime = false)
    {
        $this->checkLength($id, $data);
        $lifetime = $this->getLifetime($specificLifetime);
        $mktime = time();
        if ($lifetime === null) {
            $expire = 0;
        } else {
            $expire = $mktime + $lifetime;
        }
        $mktime = date(self::DATE_MYSQL, $mktime);
        $expire = date(self::DATE_MYSQL, $expire);
        $this->db->query('DELETE FROM Zf_memcache WHERE id = ?', [$id]);
        $sql = 'INSERT INTO Zf_memcache (id, content, lastModified, expire) VALUES (?, ?, ?, ?)';
        $sql .= ' ON DUPLICATE KEY UPDATE `content` = VALUES(`content`), `lastModified` = VALUES(`lastModified`),`expire` = VALUES(`expire`)';
        $res = $this->db->query($sql, [$id, $data, $mktime, $expire]);
        if (! $res) {
            $this->_log("ZfExtended_Cache_MySQLMemoryBackend::save() : impossible to store the cache id=$id");

            return false;
        }

        return $res;
    }

    /**
     * Remove a cache record
     *
     * @param  string $id Cache id
     * @return boolean True if no problem
     */
    public function remove($id)
    {
        $res = $this->db->query('DELETE FROM Zf_memcache WHERE id = ?', [$id]);

        return $res;
    }

    /**
     * Clean some cache records
     *
     * Available modes are :
     * Zend_Cache::CLEANING_MODE_ALL (default)    => remove all cache entries ($tags is not used)
     * Zend_Cache::CLEANING_MODE_OLD              => remove too old cache entries ($tags is not used)
     * Zend_Cache::CLEANING_MODE_MATCHING_TAG     => remove cache entries matching all given tags
     *                                               ($tags can be an array of strings or a single string)
     * Zend_Cache::CLEANING_MODE_NOT_MATCHING_TAG => remove cache entries not {matching one of the given tags}
     *                                               ($tags can be an array of strings or a single string)
     * Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG => remove cache entries matching any given tags
     *                                               ($tags can be an array of strings or a single string)
     *
     * @param  string $mode Clean mode
     * @param  array  $tags Array of tags
     * @return boolean True if no problem
     */
    public function clean($mode = Zend_Cache::CLEANING_MODE_ALL, $tags = [])
    {
        $return = $this->_clean($mode);

        return $return;
    }

    /**
     * Return an associative array of capabilities (booleans) of the backend
     *
     * The array must include these keys :
     * - automatic_cleaning (is automating cleaning necessary)
     * - tags (are tags supported)
     * - expired_read (is it possible to read expired cache records
     *                 (for doNotTestCacheValidity option for example))
     * - priority does the backend deal with priority when saving
     * - infinite_lifetime (is infinite lifetime can work with this backend)
     * - get_list (is it possible to get the list of cache ids and the complete list of tags)
     *
     * @return array associative of with capabilities
     */
    public function getCapabilities()
    {
        return [
            'automatic_cleaning' => true,
            'tags' => false,
            'expired_read' => true,
            'priority' => false,
            'infinite_lifetime' => true,
            'get_list' => false,
        ];
    }

    /**
     * Since MySQL engine memory is limited to varchar fields and is not able to use blobs
     *  we have to ensure the string length is not exceeding that limit to avoid cut off serialized strings.
     * @param string $id
     * @param string $data
     */
    protected function checkLength($id, $data)
    {
        if (strlen($data) > 4096) {
            throw new Zend_Cache_Exception('Given data to id ' . $id . ' was to long for Zend_Cache backend mysql memory (max 4096 bytes)');
        }
    }

    /**
     * Clean some cache records
     *
     * Available modes are :
     * Zend_Cache::CLEANING_MODE_ALL (default)    => remove all cache entries ($tags is not used)
     * Zend_Cache::CLEANING_MODE_OLD              => remove too old cache entries ($tags is not used)
     *
     * @param  string $mode Clean mode
     * @return boolean True if no problem
     */
    private function _clean($mode = Zend_Cache::CLEANING_MODE_ALL)
    {
        switch ($mode) {
            case Zend_Cache::CLEANING_MODE_ALL:
                return $this->db->query('DELETE FROM Zf_memcache');
            case Zend_Cache::CLEANING_MODE_OLD:
                return $this->db->query('DELETE FROM Zf_memcache WHERE expire>0 AND expire <= now()');
            default:
                break;
        }

        return false;
    }

    /**
     * Returns all rows where the given idPart is in the id.
     * @param  string  $idPart Part of cache id
     * @return array|false
     */
    public function getAllForPartOfId(string $idPart)
    {
        $sql = 'SELECT * FROM `Zf_memcache` WHERE `id` LIKE ?';
        $params = ['%' . $idPart . '%'];
        $res = $this->db->query($sql, $params);
        if ($res && $res->rowCount() > 0) {
            return $res->fetchAll();
        }

        return false;
    }
}
