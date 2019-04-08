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
 * @package ZfExtended
 * @version 2.0
 *
 */
/*
 * Serverseitige Methoden, die ExtJs unterstützen
 *
 * 
 */

class ZfExtended_Controller_Helper_ExtJs extends Zend_Controller_Action_Helper_Abstract {
    /**
     * @var int
     */
    protected $_extVersion = 0;
    
    /**
     * @var array extPaths Array mit allen basePath-Pfaden von extJs gemäß application.ini
     * sortiert gemäß ZfExtended_Controller_Helper_General->natksortReverseUtf
     */
    protected $_extPaths = array();
    
    /**
     * Pfad zur css Datei unterhalb des ext basepaths
     * @var string
     */
    protected $_cssPath;

    public function __construct(){
        $config = Zend_Registry::get('config');
        $general = ZfExtended_Zendoverwrites_Controller_Action_HelperBroker::getStaticHelper(
            'General'
        );
        $this->_cssPath = $config->runtimeOptions->extJs->cssFile;
        $this->_extPaths = $config->runtimeOptions->extJs->basepath->toArray();
        $this->_extPaths = $general->natksortReverseUtf($this->_extPaths);
        $extConfig = $config->extVersionMapping;
        
        if(!empty($extConfig)) {
            // In der Reihenfolge wie die Bestandteile abgearbeitet werden sollen hinzufügen
            $mcaPath[] = Zend_Registry::get('module');
            $mcaPath[] = $this->getRequest()->getControllerName();
            $mcaPath[] = $this->getRequest()->getActionName(); 
            $this->_extVersion = $this->getVersionRecursive($mcaPath, $extConfig);
        }
        
        //Kein Wert definiert, dann die höchste Version als Default verwenden: 
        if($this->_extVersion <= 0){
            reset($this->_extPaths);
            $this->_extVersion = (int)key($this->_extPaths);
        }
    }
    
    /**
     * Geht recursiv über die ExtJS Config anhand des Modul / Controller / Action Pfades
     * Gibt die Versionsnummer der gefunden Ext Variante zurück, 0 wenn nichts gefunden wurde. 
     * @param array $path
     * @param Zend_Config $config
     * @return int 
     */
    protected function getVersionRecursive(array $path, Zend_Config $config) {
        $step = array_shift($path);
        if(isset($config->$step)){
            if(!is_object($config->$step)){
                return (int)$config->$step;
            }
            $result = $this->getVersionRecursive($path, $config->$step);
            if($result > 0) {
                return $result;
            }
        }
        if(isset($config->DEFAULT) && !is_object($config->DEFAULT)){
            return (int)$config->DEFAULT;
        }
        return 0;
    }

    /**
     * Gibt den http-orientierten Pfad zu ExtJs auf Basis der application.ini zurück
     *
     * - sind unterhalb von runtimeOptions.extJs.basepath mehrere Ext-Pfade gelistet,
     *   wird der Pfad zurückgegeben, der in /application/extVersionMapping.ini
     *   der aktuellen Kombination aus Modul / Controller zugeordnet ist
     * - Ist die aktuelle Kombination dort nicht gelistet oder existiert die Datei nicht,
     *   wird der Pfad zurück gegeben, der gemäß der an letzter
     *   Stelle in der Sortierreihenfolge steht, wenn man die nach runtimeOptions.extJs.basepath
     *   folgenden Schlüssel mit ZfExtended_Controller_Helper_General->natksortUtf sortiert (also bei Verwendung
     *   von Versionsnummern als Schüssel die jeweils neueste gelistete Version)
     *
     * @return string http-orientierten Pfad zu ExtJs auf Basis der application.ini
     */
    public function getHttpPath() {
        return $this->_extPaths[$this->_extVersion];
    }

    /**
     * Gibt den konfigurierten Pfad zur ExtJS CSS Datei zurück (kompletter Pfad inkl. ExtJS Http Base Path)
     * @return string
     */
    public function getCssPath() {
        return $this->getHttpPath().$this->_cssPath;
    }
    /**
     * Gibt die ExtJs-Version auf Basis der application.ini zurück
     *
     * - nähere Infos zur Ermittlungslogik siehe $this->getHttpPath
     *
     * @return integer extVersion
     */
    public function getVersion() {
        return $this->_extVersion;
    }
}
