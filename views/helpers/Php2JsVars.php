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
 * Stellt sicherheitsunkritische php-Variablen als globale JS-Variablen zur Verfügung
 * Geht davon aus, dass im Javascript bereits ein JS Namespace = Modulnamen angelegt ist!
 *
 * @return string JS
 */
class ZfExtended_View_Helper_Php2JsVars extends Zend_View_Helper_Abstract{
    protected $data;
    public function __construct() {
        $this->data = new stdClass();
    }

    /**
     * setzt eine PHP variable im JS Portal.data Objekt
     * akzeptiert als ersten Parameter entweder array(foo, bar, name) oder 'foo.bar.name'
     * erzeugt bei Bedarf den Objektbaum, und setzt den übergebenen Wert
     * @param mixed $name string oder array oder object
     * @param mixed $data
     */
    public function set($name, $data) {
        if(is_string($name)){
            $name = explode('.',$name);
        }

        //Das letzte Element bezeichnet den Wert selbst,
        // wird daher nicht ins create mit einbezogen
        $nameOfValue = array_pop($name);
        $this->create($name)->{$nameOfValue} = $data;
    }

    /**
     * akzeptiert als Parameter array(foo, bar) oder 'foo.bar' erzeugt den Objektbaum, und gibt bar zurück
     * @param mixed $name string oder array
     * @throws Zend_Exception
     * @return stdObject
     */
    public function create($name){
        if(is_string($name)){
            $name = explode('.',$name);
        }
        $debugInfo = 'Php2JsVars->data';
        // root Element für die Rekursion
        $target = $this->data;
        foreach($name as $attribute) {
            $debugInfo .= '->'.$attribute;
            // neuen Objekt Knoten erzeugen
            if(!isset($target->{$attribute})){
                $target->{$attribute} = new stdClass();
            }
            // Falls es Objekt Knoten bereits gab, und dieser kein Objekt ist: Exception
            elseif(! $target->{$attribute} instanceof stdClass){
                throw new Zend_Exception('Ist KEINE Instanz von stdClass: '.$debugInfo);
            }
            //Rekursions Root neu setzen
            $target = $target->{$attribute};
        }
        return $target;
    }

    /**
     * holt eine PHP variable aus dem JS Portal.data Objekt
     * @param string $name
     * @return mixed
     */
    public function get($name) {
        if(isset($this->data->{$name}))return $this->data->{$name};
        return new stdClass();
    }

    /**
     * Helper Aufruf
     * @return ZfExtended_View_Helper_Php2JsVars
     */
    public function php2JsVars(){
        return $this;
    }

    /**
     * gibt die String Repräsentations diesen Helpers aus
     * Geht davon aus, dass ein JS Namespace = Modulnamen bereits vorhanden ist!
     * @return string
     */
    public function  __toString() {
        $module = Zend_Controller_Front::getInstance()->getRequest()->getModuleName();
        return ucfirst($module).'.data = '.Zend_Json::encode($this->data);
    }
}
