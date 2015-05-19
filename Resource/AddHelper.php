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