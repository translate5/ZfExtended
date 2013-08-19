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
 * Klasse zur Initialisierung aller Formulare
 *
 * - liest Form aus ini ein entsprechend der Konvention "controllerAction" des aufrufenden Controllers und der aufrufenden Action
 * - Kinder können auch zur Datenvalidierung im Modell herangezogen werden
 */
class  ZfExtended_Zendoverwrites_Form extends Zend_Form
{
    /**
     * @var string Falls nicht NULL Dateiname der zu ladenden ini-Config-Datei für die Form
     */
    protected  $_form;
    /**
     * Sorgt dafür, dass Optionen übergeben werden können
     *
     * - sorgt dafür, dass der eigene noCsrf-Validator NoCsrfs ausgeführt werden kann
     *   indem alle nocsrf-Hashs mit dem Key $bisheriger_key.'__nocsrfCopyOld' in der
     *   Session gespeichert werden. Grund: Sobald der Kontruktor von Zend_Form
     *   für ein Formular aufgerufen wird, wird der vorhandene Hash aus der Session
     *   gelöscht.
     * 
     * @param string form Dateiname inkl. Endung einer ini-Konfigurationsdatei
     *               für das Formular
     * @param mixed options options-Paramter von Zend_Form
     * @return void
     */
     public function __construct(string $form, $options = null) {
         foreach($_SESSION as $key => $val){
             if(strpos(strtolower($key), 'nocsrf')!== false and strpos($key, '__nocsrfCopyOld')=== false){
                 $_SESSION[$key.'__nocsrfCopyOld']= $val;
             }
         }
         $this->_form = lcfirst($form);
         parent::__construct($options);
     }
    /**
     * Lädt Form
     *
     * @return void
     */
    public function init()
    {
        $module = Zend_Registry::get('module');
        $this->addElementPrefixPath( 'Views_', APPLICATION_PATH . '/modules/'.$module.'/views' );
        $session = new Zend_Session_Namespace();
        foreach($session->libraryNames as $library){
            $this->addElementPrefixPath( $library.'_', APPLICATION_PATH . '/../library/'.$library );
        }
        $ini_paths = array();
        $ini_paths[] = APPLICATION_PATH.'/modules/'.$module.'/configs/forms/' . $this->_form;
        foreach($session->libraryNames as $library){
            $ini_paths[] = APPLICATION_PATH. '/../library/'.$library.'/configs/forms/' . $this->_form;
        }
        $ini_path = false;
        foreach ($ini_paths as $path) {
            if(file_exists($path)){
                $ini_path = $path;
                break;
            }
        }
        if(!$ini_path){
            throw new Zend_Exception('the ini-file '.$this->_form.' does not exist', 0);
        }
        $config = new Zend_Config_Ini($ini_path);
        $this->setConfig($config);
        $action = $this->getAction();
        if($action != ''){
            $this->setAction(APPLICATION_RUNDIR.$action);
        }
    }
}
