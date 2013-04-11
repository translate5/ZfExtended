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
 * @package zfExtended
 * @version 2.0
 *
 */
/**
 * Falls der Request ein Fork ist wird die Session des Forks mit den Werten der
 * Muttersession befüllt
 *
 * - Näheres zur Interaktion siehe ZfExtended_Controllers_Helper_GeneralHelper->forkHttp
 * - ein Hacking des Benutzerzugangs durch diese Funktion wird ausgeschlossen durch
 *   den Vergleich der $_GET['forkNoRegenerateId'] mit
 *   $session->runtimeOptions->forkNoRegenerateId. Sprich nur wer den in application.ini
 *   gesetzten Wert runtimeOptions->forkNoRegenerateId kennt kann sich mittels dieser
 *   Klasse einloggen und auch dies nur, wenn ein cache der zu $_GET['uniqid'] passt
 *   vorhanden ist
 * - setzt im Default-Namespace den Parameter ->isFork auf false, und schaltet ihn auf
 *   true, falls es ein Fork ist - die Kinder sollten hierauf prüfen, ob sie mittels
 *   Fork aufgerufen sind und wenn nein einen Fehler werfen
 * 
 *
 */
class ZfExtended_Resource_InitForkSession extends Zend_Application_Resource_ResourceAbstract {
    public function init()
    {
        $bootstrap = $this->getBootstrap();
        $bootstrap->bootstrap('ZfExtended_Resource_InitRegistry');
        $bootstrap->bootstrap('ZfExtended_Resource_Session');
        $bootstrap->bootstrap('cachemanager');
        $session = new Zend_Session_Namespace();
        $session->isFork = false;
        $config = Zend_Registry::get('config');
        if(isset($_GET['forkNoRegenerateId']) and isset($_GET['uniqid']) and
                $_GET['forkNoRegenerateId'] == $config->runtimeOptions->forkNoRegenerateId){
            $dirs = scandir($config->resources->cachemanager->zfExtended->backend->options->cache_dir);
            $cache = $bootstrap->getResource('cachemanager')->getCache('zfExtended');
            $sessionCacheFilePrefix = 'zend_cache---'.
                        $config->resources->cachemanager->zfExtended->frontend->
                        options->cache_id_prefix;
            foreach($dirs as $file){
                if(strpos( $file,$sessionCacheFilePrefix)!== false and strpos( $file,$_GET['uniqid'])!== false){
                    $SessionCacheFile = preg_replace('"^'.$sessionCacheFilePrefix.'"','',$file);
                    $SessionNameSpace = preg_replace('"^session_([^_]*)_.*"','\\1',$SessionCacheFile);
                    $sessionCache = $cache->load($SessionCacheFile);
                    $this->setSessionNameSpace($SessionNameSpace, $sessionCache);
                    $cache->remove($SessionCacheFile);
                }
            }
            $session->isFork = true;
        }
    }
    
    /**
     * befüllt den angegebene Session Name Space aus dem Session Cache
     * @param string $SessionNameSpace
     * @param ArrayObject $sessionCache
     * @return Zend_Session_Namespace
     */
    protected function setSessionNameSpace(string $SessionNameSpace, ArrayObject $sessionCache) {
        $session = new Zend_Session_Namespace($SessionNameSpace);
        foreach($sessionCache as $key => $entry){
            $session->$key = $entry;
        }
        return $session;
    }
}