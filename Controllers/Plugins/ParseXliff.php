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
 * Erstellt eine vernünftige xliff-Datei aus den notFoundTranslations
 */
class ZfExtended_Controllers_Plugins_ParseXliff extends Zend_Controller_Plugin_Abstract
{
    /**
     *
     * @var ZfExtended_Zendoverwrites_Translate 
     */
    protected $translate;

    /**
     *
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     */
    public function dispatchLoopShutdown(){
        $this->translate = ZfExtended_Zendoverwrites_Translate::getInstance();
        $logPath = $this->translate->getLogPath();
        $logArr = $this->parseXliff($logPath);
        if(!empty($logArr)){
            $tLang = $this->translate->getTargetLang();
            $sLang = $this->translate->getSourceCodeLocale();
            if($tLang===$sLang){
                $session = new Zend_Session_Namespace();
                
                $xliffPath = APPLICATION_PATH.DIRECTORY_SEPARATOR.'modules'.
                        DIRECTORY_SEPARATOR.APPLICATION_MODULE.DIRECTORY_SEPARATOR.
                        'locales'.DIRECTORY_SEPARATOR.$sLang.'.xliff';
                $integratedPath = $session->runtimeOptions->dir->logs.'/integratedTranslations-'.
                        APPLICATION_MODULE.'-'.$sLang.'-'.$tLang.'.xliff';
                
                $xliffArr = $this->parseXliff($xliffPath);
                $xliffArr = array_merge($xliffArr,$logArr);
                $this->saveXliff($xliffArr, $xliffPath);
                
                $integratedArr = $this->parseXliff($integratedPath);
                $integratedArr = array_merge($integratedArr,$logArr);
                $this->saveXliff($integratedArr, $integratedPath);
                unlink($logPath);
                return;
            }
            $this->saveXliff($logArr, $logPath);
        }
    }
    
    protected function saveXliff(array $fileArr,string $path) {
        $fileArr = array_unique($fileArr);
        $file = implode("</trans-unit>\n", $fileArr).'</trans-unit>';
        $file = str_replace("<trans-unit id=''><source></source><target></target></trans-unit>","",$file);
        $file = $this->translate->getXliffStartString().$file.$this->translate->getXliffEndString();
        file_put_contents($path, $file);
    }


    protected function parseXliff(string $path) {
        if(file_exists($path)){
            $file = file_get_contents($path);
            $file = preg_replace(
                    array(
                        '"^.*<body[^>]*>"s',
                        '"</body>.*$"s',
                        '"\s*<trans-unit"s',
                        '"</trans-unit>\s*"s',
                        '"\s*<source"s',
                        '"</source>\s*"s',
                        '"\s*<target"s',
                        '"</target>\s*"s',
                        ),
                    array(
                        '',
                        '',
                        '<trans-unit',
                        '</trans-unit>',
                        '<source',
                        '</source>',
                        '<target',
                        '</target>',
                        ),$file);
            $fileArr = explode("</trans-unit>", $file);
            array_pop($fileArr);
            return $fileArr;
        }
        return array();
    }
}