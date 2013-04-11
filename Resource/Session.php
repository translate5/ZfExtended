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

/**
 * Startet die Session
 *
 * - legt die Variable $session->internalSessionUniqId im allgemeinen
 *   Session-Namespace fest. Sie wird nur intern in der Programmierung und für
 *   Flag-Files verwendet und darf aus Sicherheitsgründen nicht nicht in Cookies
 *   gesetzt werden. Sie ist persistent über die gesamte Session
 * - hinterlegt in der Tabelle sessionMapInternalUniqId ein Mapping zwischen der
 *   session_id (die durch ZfExtended_Controllers_Plugins_SessionRegenerate bei
 *   jedem Aufruf neu gesetzt wird) und der $session->internalSessionUniqId
 */
class ZfExtended_Resource_Session extends Zend_Application_Resource_ResourceAbstract {
    /*
     * @var array config Konfiguration der Parameter für die DB-Sessioninitialisierung
     */
    private $_sessionConfig = array(
        'name'              => 'session', //table name as per Zend_Db_Table
        'primary'           => array(
            'session_id',   //the sessionID given by PHP
            'name',         //session name
        ),
        'primaryAssignment' => array(
            //you must tell the save handler which columns you
            //are using as the primary key. ORDER IS IMPORTANT
            'sessionId', //first column of the primary key is of the sessionID
            'sessionName', //second column of the primary key is the session name
        ),
        'modifiedColumn'    => 'modified',     //time the session should expire
        'dataColumn'        => 'session_data', //serialized data
        'lifetimeColumn'    => 'lifetime',     //end of life for a specific record
    );

    public function init()
    {
        $bootstrap = $this->getBootstrap();
        $bootstrap->bootstrap('db');
        $bootstrap->bootstrap('ZfExtended_Resource_ErrorHandler');
        $config = new Zend_config($bootstrap->getOptions());
        $this->garbageCollector();
        Zend_Session::setOptions($config->resources->ZfExtended_Resource_Session->toArray());
        //im if: wichtiger workaround für swfuploader, welcher in awesomeuploader 
        //verwendet wird. flash überträgt keine session-cookies außerhalb IE korrekt, 
        //daher wird hier die session im post übergeben
        if (isset($_POST[$config->resources->ZfExtended_Resource_Session->name])) {
            Zend_Session::setId($_POST[$config->resources->ZfExtended_Resource_Session->name]);
        }
        Zend_Session::setSaveHandler(new Zend_Session_SaveHandler_DbTable($this->_sessionConfig));

        Zend_Session::start();
        $this->setInternalSessionUniqId();
    }
    /*
     * Setzt internalSessionUniqId wie im Klassenkopf beschrieben
     */
    private function setInternalSessionUniqId(){
        $session = new Zend_Session_Namespace();
        if(!isset($session->internalSessionUniqId)){
            $session->internalSessionUniqId =  md5(uniqid(__FUNCTION__, true));
            $row = ZfExtended_Factory::get('ZfExtended_Models_Entity',
                        array('ZfExtended_Models_Db_SessionMapInternalUniqId',
                            array()));
            $row->setSession_id(Zend_Session::getId());
            $row->setInternalSessionUniqId($session->internalSessionUniqId);
            $row->setModified(time());
            $row->save();
        }
    }
    /*
     * Löscht verfallene Sessions aus der Session-Tabelle
     * 
     * - Einbindung von Session-Models ohne ZfExtended_Factory, da die Factory eine initialisierte Session benötigt
     */
    private function garbageCollector(){
        $config = Zend_Registry::get('config');
        $sessionTable = new ZfExtended_Models_Db_Session();
        $sessionTable->delete('modified < '.(string)(time()-$config->runtimeOptions->ZfExtendedGarbageColletorLifetime));
        $SessionMapInternalUniqIdTable = new ZfExtended_Models_Db_SessionMapInternalUniqId();
        $SessionMapInternalUniqIdTable->delete('modified < '.(string)(time()-$config->runtimeOptions->ZfExtendedGarbageColletorLifetime));
    }
}