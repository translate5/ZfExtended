<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of translate5
 
 Copyright (c) 2013 - 2015 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file agpl3-license.txt 
 included in the packaging of this file.  Please review the following information 
 to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3.0 requirements will be met:
 http://www.gnu.org/licenses/agpl.html

 There is a plugin exception available for use with this release of translate5 for
 open source applications that are distributed under a license other than AGPL:
 Please see Open Source License Exception for Development of Plugins for translate5
 http://www.translate5.net/plugin-exception.txt or as plugin-exception.txt in the root
 folder of translate5.
  
 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execptions
			 http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt

END LICENSE AND COPYRIGHT
*/

/**
 * Config Controller, currently only indexAction
 */
class ZfExtended_ConfigController extends ZfExtended_RestController {
    
    protected $entityClass = 'ZfExtended_Models_Config';
    
    /**
     * @var ZfExtended_Models_Config
     */
    protected $entity;
    
    /**
     * (non-PHPdoc)
     * @see ZfExtended_RestController::indexAction()
     */
    public function indexAction() {
        $iniOptions = $this->getInvokeArg('bootstrap')->getApplication()->getOptions();
        parent::indexAction();
        $rows = $this->view->rows;
        foreach($rows as $row) {
            $this->mergeWithIni($iniOptions, explode('.', $row['name']), $row);
        }
    }
    
    /**
     * Merges the ini config values into the DB result before returning by REST API
     * @param array $root
     * @param array $path
     * @param array $row given as reference, the ini values are set in here
     */
    protected function mergeWithIni(array $root, array $path, array &$row) {
        $row['origin'] = 'db';
        $row['dbValue'] = null;
        $part = array_shift($path);
        if(!isset($root[$part])) {
            return;
        }
        if(!empty($path)){
            $this->mergeWithIni($root[$part], $path, $row);
            return;
        }
        $row['origin'] = 'ini';
        $row['overwritten'] = $row['value'];
        $row['value'] = $root[$part];
        if($row['type'] == ZfExtended_Resource_DbConfig::TYPE_MAP || $row['type'] == ZfExtended_Resource_DbConfig::TYPE_LIST){
            $row['value'] = json_encode($row['value'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    }
    
    /**
     * (non-PHPdoc)
     * @see ZfExtended_RestController::postAction()
     * CRUD is currently not implemented, so BadMethod here
     */
    public function postAction() {
        throw new ZfExtended_BadMethodCallException(__CLASS__.'->'.__FUNCTION__);
    }
    
    /**
     * (non-PHPdoc)
     * @see ZfExtended_RestController::putAction()
     * CRUD is currently not implemented, so BadMethod here
     */
    public function putAction() {
        throw new ZfExtended_BadMethodCallException(__CLASS__.'->'.__FUNCTION__);
    }
    
    
    /**
     * (non-PHPdoc)
     * @see ZfExtended_RestController::putAction()
     * CRUD is currently not implemented, so BadMethod here
     */
    public function getAction() {
        throw new ZfExtended_BadMethodCallException(__CLASS__.'->'.__FUNCTION__);
    }
    
    /**
     * (non-PHPdoc)
     * @see ZfExtended_RestController::putAction()
     * CRUD is currently not implemented, so BadMethod here
     */
    public function deleteAction() {
        throw new ZfExtended_BadMethodCallException(__CLASS__.'->'.__FUNCTION__);
    }
}