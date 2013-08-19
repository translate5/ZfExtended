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

/**#@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 *
 */
/**
 * Stellt Laden der ControllerActionHelper sicher
 *
 *
 *
 */
class ZfExtended_Resource_AddHelper extends Zend_Application_Resource_ResourceAbstract {
    /**
     * @var array
     */
    protected $_pathsToAdd;

    /**
     * @var Zend_Session_Namespace
     */
    protected $_session;

    public function init()
    {
        $bootstrap = $this->getBootstrap();
        $bootstrap->bootstrap('ZfExtended_Resource_PreFillSession');
        $this->_session = new Zend_Session_Namespace();
        if(!isset($this->_session->zfExtendedPaths2Add[APPLICATION_MODULE])){
            $this->_pathsToAdd = array();
            $this->_genPaths();
            $this->_session->zfExtendedPaths2Add[APPLICATION_MODULE] = $this->_pathsToAdd;
        }
        foreach($this->_session->zfExtendedPaths2Add[APPLICATION_MODULE] as $prefix => $path){
            ZfExtended_Zendoverwrites_Controller_Action_HelperBroker::addPath($path,$prefix);
        }
    }

    /**
     * Generiert die Prefix und Pfad Kombinationen
     */
    protected function _genPaths() {
        if(APPLICATION_MODULE === "default"){
            $modulePrefix = '';
        }
        else {
            $modulePrefix = ucfirst(APPLICATION_MODULE).'_';
        }

        $agency = ucfirst(APPLICATION_AGENCY);
        $fowBase = APPLICATION_PATH .'/factoryOverwrites/'.APPLICATION_AGENCY;
        $fowMod = $fowBase.'/modules/'.APPLICATION_MODULE;
        $modulesBase = APPLICATION_PATH .'/modules/'.APPLICATION_MODULE;
        $c_h = 'Controller_Helper_';
        $c_h_path = '/Controllers/helpers';
        $v_h = 'View_Helper_';
        $v_h_path = '/views/helpers';

        //Kunden Module Controller Helper
        $this->_addPath($agency.'_'.$modulePrefix.$c_h, $fowMod.$c_h_path);
        //Module Controller Helper
        $this->_addPath($modulePrefix.$c_h, $modulesBase.$c_h_path);
        //Kunden Module View Helper
        $this->_addPath($agency.'_'.$modulePrefix.$v_h, $fowMod.$v_h_path);
        //Module View Helper
        $this->_addPath($modulePrefix.$v_h, $modulesBase.$v_h_path);

        //Es folgen die Controller und View Helper Pfade der einzelnen Libs,
        // ebenfalls in Abhängigkeit des Kunden (Agency)
        $config = Zend_Registry::get('config');
        $libs = array_reverse($config->runtimeOptions->libraries->order->toArray());
        foreach ($libs as $lib) {
            $libPrefix = ucfirst($lib).'_';
            $this->_addPath($agency.'_'.$libPrefix.$c_h, $fowBase.'/library/'.$lib.$c_h_path);
            $this->_addPath($libPrefix.$c_h, APPLICATION_PATH .'/../library/'.$lib.$c_h_path);
            $this->_addPath($agency.'_'.$libPrefix.$v_h, $fowBase.'/library/'.$lib.$v_h_path);
            $this->_addPath($libPrefix.$v_h, APPLICATION_PATH .'/../library/'.$lib.$v_h_path);
        }
    }

    /**
     * Fügt Prefix und Pfad hinzu wenn der Pfad existiert
     * @param string $prefix
     * @param string $path
     */
    protected function _addPath(string $prefix, string $path) {
        if(is_dir($path)) {
            $this->_pathsToAdd[$prefix] = $path;
        }
    }
}