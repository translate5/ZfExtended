<?php
 /*
 START LICENSE AND COPYRIGHT
 
 This file is part of Translate5 Editor PHP Serverside and build on Zend Framework
 
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
 * manuall helper methods for the translation process - not intended to use in programmatic routines
 */

class TranslateController extends ZfExtended_Controllers_Action
{
    /**
     * @var integer
     */
    protected $pathToEnXliff = '/../data/locales/en.xliff';
    

    /**
    * Uses the existent /data/locales/en.xliff to generate a translateable 
    * xliff-file by deleting the current English translation, copying the German source
    * to the target and then sending it out to the browser
    */
    public function getxliffAction()
    {
        $this->view->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        $xliff = file_get_contents(APPLICATION_PATH.$this->pathToEnXliff);
        $xliff = preg_replace('"<target[^>]*>.*?(?!/target>)</target>"','',$xliff);
        $xliff = preg_replace('"(<source[^>]*>(.*?)(?!/source>)</source>)"','\\1<target>\\2</target>',$xliff);
        header('Content-type: application/xml');
        header('Content-Disposition: attachment; filename="toTranslate.xliff"');
        echo $xliff;
    }
    public function generatexliffAction(){
        $this->view->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        $index = ZfExtended_BaseIndex::getInstance();
        /* @var $index ZfExtended_BaseIndex */
        $libs = $index->getLibPaths();
        foreach ($libs as $lib) {
            $this->collectAndWriteXliff($lib);
        }
        $modules = $index->getModulePaths();
        foreach ($modules as $module) {
            $this->collectAndWriteXliff($module);
        }
        $this->collectAndWriteXliff(APPLICATION_PATH);
        echo "Generation of xliff-files done";
    }
    /**
     * 
     * @param string $path
     */
    protected function collectAndWriteXliff(string $path) {
        $translate =  ZfExtended_Zendoverwrites_Translate::getInstance();
        /* @var $translate ZfExtended_Zendoverwrites_Translate */
        $xliff = array();
        $error = false;
        $path = realpath($path);
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,RecursiveDirectoryIterator::FOLLOW_SYMLINKS));
        while($it->valid()) {
            if (!$it->isDot() && !$it->isDir()) {
                if(($it->getExtension() === 'php' || $it->getExtension() === 'phtml')&&
                        ($path !== realpath(APPLICATION_PATH) || strpos($it->key(), APPLICATION_PATH.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR)===false)){
                    $file = file_get_contents($it->key());
                    if(strpos( $file,'translate->_')!==false){
                        while(preg_match('"(->_\(\'[^\']*)\"([^\']*\')"s',$file)){
                            $file = preg_replace('"(->_\(\'[^\']*)\"([^\']*\')"s','\\1___transquot___\\2',$file);
                        }
                        $file = preg_replace('"(->_\()\'([^\']*)\'"s','\\1"\\2"',$file);
                        $file = preg_split('"(->_\(\")([^\"]*)(\")"s',$file , NULL, PREG_SPLIT_DELIM_CAPTURE);
                        $file = str_replace('___transquot___', '"', $file);
                        $count = count($file);
                        if($count>1){
                            for ($i = 2; $i < $count;$i = $i + 2) {
                                
                                $id = base64_encode($file[$i]);
                                $xliff[]= '<trans-unit id="'.$id.'"><source>'.
                                        $file[$i].'</source><target>'.$file[$i].
                                        '</target></trans-unit>';
                                $i = $i + 2;
                                if($file[$i][0]!== ')'){
                                    error_log('In file '.$it->key().
                                            ' a translation could not be extracted, because the parser did not match');
                                    $error = true;
                                }
                            }
                        }
                    }
                }
            }
            $it->next();
        }
        if($path === realpath(APPLICATION_PATH))
            $path = APPLICATION_PATH.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'data';
        $xliff2write = $path.DIRECTORY_SEPARATOR.'locales'.
                    DIRECTORY_SEPARATOR.$translate->getSourceCodeLocale().'.xliff';
        
            
        if(!file_exists($xliff2write)||(!is_null($this->_getParam('overwrite')))){
            file_put_contents($xliff2write, $translate->getXliffStartString().
                    implode("\n", array_unique($xliff)).$translate->getXliffEndString());
        }
        elseif(file_exists($xliff2write)){
            $message = "The file ".$xliff2write." already exists";
            echo $message.'<br/>';
            error_log($message);
        }
        if($error){
            throw new Zend_Exception('Some translations could not be extracted. Please have a look into the error-log');
        }
    }

    /**
     * migrates xliff with IDs that are not base64-encoded to those where they are
     */
    public function migratexliffAction() {
        $this->view->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        $session = new Zend_Session_Namespace();
        $index = ZfExtended_BaseIndex::getInstance();
        /* @var $index ZfExtended_BaseIndex */
        $dirs = $index->getModuleDirs();
        $allXliffs = array();
        foreach ($dirs as &$dir) {
            $dir = APPLICATION_PATH.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.$dir.DIRECTORY_SEPARATOR.'locales';
        }
        unset($dir);
        $dirs[] = $session->runtimeOptions->dir->locales;
        foreach ($dirs as $dir) {
            if(!is_dir($dir))continue;
            $xliffs = scandir($dir);
            foreach ($xliffs as $key => &$xliff) {
                if(is_dir($xliff) || $xliff === '.' || $xliff === '..' || !preg_match('"\.xliff$"', $xliff)){
                    unset($xliffs[$key]);
                    continue;
                }
                $xliff = $dir.DIRECTORY_SEPARATOR.$xliff;
            }
            $allXliffs = array_merge($allXliffs,$xliffs);
        }
        $marker = '<!-- ids are base64-encoded -->';
        foreach ($allXliffs as $key => $file) {
            $xliff = file_get_contents($file);
            if(strpos($xliff, $marker)!==false){
                echo "file ".$file. " already is migrated\r\n\r\n";
                continue;
            }
            //$xliff = str_replace('<!-- trans-unit id=1 dient dem Unit-Testing - bitte nicht verändern!!! -->', '', $xliff);
            while(preg_match('"(<trans-unit[^>]*id=\'[^\']*)\"([^\']*\')"s',$xliff)){
                $xliff = preg_replace('"(<trans-unit[^>]*id=\'[^\']*)\"([^\']*\')"s','\\1___transquot___\\2',$xliff);
            }
            $xliff = preg_replace('"(<trans-unit[^>]*id=)\'([^\']*)\'"s','\\1"\\2"',$xliff);
            $xliff = preg_split('"(<trans-unit[^>]*id=\")([^\"]*)(\")"s', $xliff, NULL, PREG_SPLIT_DELIM_CAPTURE);
            $count = count($xliff);
            for ($i = 1; $i < $count;) {
                $i++;
                $xliff[$i] = base64_encode(html_entity_decode(str_replace('___transquot___', '"', $xliff[$i]),ENT_QUOTES,'utf-8'));
                $i = $i + 3;
            }
            $xliff = implode("", $xliff);
            $xliff = str_replace('</xliff>',$marker.'</xliff>', $xliff);
            file_put_contents($file, $xliff);
        }
    }
}
