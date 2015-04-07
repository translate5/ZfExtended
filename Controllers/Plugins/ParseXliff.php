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