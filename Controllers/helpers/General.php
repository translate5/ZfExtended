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
 * Helper für Methoden, die an verschiedenen ansonsten nicht zusammengehörenden Orten verwendet werden
 */

class ZfExtended_Controller_Helper_General extends Zend_Controller_Action_Helper_Abstract {
    /**
     * Definiert die Zeichen, die von den Sortierungsfunktionen getauscht werden,
     * um ohne Unicode-Fähigkeit natürlich sortieren zu können. Unterstützt bislang
     * nur DE und FR
     */
    var $charList = array(
                    'Ä' => 'Ae',
                    'Ö' => 'Oe',
                    'Ü' => 'Ue',
                    'ä' => 'ae',
                    'ö' => 'oe',
                    'ü' => 'ue',
                    'ß' => 'ss',
                    'É' => 'E',
                    'È' => 'E',
                    'Ë' => 'E',
                    'ë' => 'e',
                    'é' => 'e',
                    'è' => 'e',
                    'À' => 'A',
                    'à' => 'a',
                    'Ç' => 'C',
                    'ç' => 'c'
                );
    /**
     * Gibt letzten Namensteil einer Pfadangabe zurück -
     *
     * - funktioniert analog zu basename, allerdings unabhängig von System-Locale-Einstellungen
     *
     * @return string
     * @deprecated This method is completly nonsense 
     */
    public function basenameLocaleIndependent(string $path) {
        $pathArr = explode(DIRECTORY_SEPARATOR, $path);
        return array_pop($pathArr);
    }

    /**
     * Wandelt ein Mehrdimensionales Array in ein Objekt um
     *
     * @param array
     * @return stdClass | false, falls Konvertierung nicht möglich
     */
    public function arrayToObject($array) {
        if (!is_array($array)) {
            return $array;
        }
        $config = Zend_Registry::get('config');
        $object = new stdClass();
        if (is_array($array) && count($array) > 0) {
            foreach ($array as $name => $value) {
                $name = mb_strtolower(trim($name),$config->phpsettings->default_charset);
                if (!empty($name)) {
                    $object->$name = arrayToObject($value);
                }
            }
            return $object;
        } else {
            return FALSE;
        }
    }

    /**
     * Versendet eine Mail
     *
     * - für das Bodyrendering wird als Viewscript eine phtml-Datei im Verzeichnis
     *   /views/scripts/mail/ erwartet
     * - der Name des viewscripts entspricht der Klasse und Funktionsnamen  der
     *   aufrufenden Funktion ohne schließendes "Controller" oder "Action",
     *   (also z. B. loginPasswdreset für die Funktion passwdresetAction aus der Klasse LoginController)
     *   und ohne führenden Klassenpfad (also z. B. generalhelperForkhttp.phtml für einen
     *   Aufruf aus der Funktion forkHttp der Klasse Portal_Controller_Helper_GeneralHelper).
     *   Ausnahme: Die Variable $template ist gesetzt - dann wird dieses Template gewählt.
     * - Datei ist der Klassenname komplett in Lowercase und der Funktionsname ebenso
     *   mit Ausnahme des ersten Buchstabens
     * @param string $toMail Mailadresse, an die die Mail versandt wird
     * @param string $toName Name des Empfängers der Mail
     * @param array $params Parameter, die zur Befüllung des Mailtemplates benötigt
     *                      werden der Form array('paramName'=>'paramVal',...)
     * @param string $fromMail From-Mailadresse, falls $fromMail oder $fromName nicht gesetzt wird die
     *                      Default-Mail des Portals verwendet (application.ini)
     * @param string $fromName From-Name, falls $fromMail oder $fromName nicht gesetzt wird der
     *                      Default-From-Name des Portals verwendet (application.ini)
     * @param array $attachments array(array('body'=>'','mimeType'=>'','disposition' =>'',
      'encoding'=>'','filename'=>''))
     *      Falls vorhanden werden die im array enthaltenen
     *      Strings mimecodiert als Attachment angehängt.
     * @param string template Name eines Template-Files aus dem Verzeichnis
     *      /views/scripts/mail/, der für das Rendering verwendet werden soll
     * @return void
     *
     */
    public function mail(string $toMail, string $toName, string $subject, array $params = array(), $fromMail = false, $fromName = false, array $attachments = array(), $template = false) {
        $mailer = new ZfExtended_Mail();
        $mailer->setParameters($params);
        $mailer->setAttachment($attachments);

        $mailer->setSubject($subject);
        if (!empty($template)) {
            $mailer->setTemplate($template);
        }
        if ($fromMail && $fromName) {
            $mailer->setFrom($fromMail, $fromName);
        }

        $mailer->send($toMail, $toName);
    }

    /**
     */
    public function logoutUser() {
        $session = new Zend_Session_Namespace();
        $internalSessionUniqId = $session->internalSessionUniqId;
        $sessionId = Zend_Session::getId();
        $SessionMapInternalUniqIdTable = ZfExtended_Factory::get('ZfExtended_Models_Db_SessionMapInternalUniqId');
        $SessionMapInternalUniqIdTable->delete("internalSessionUniqId  = '".$internalSessionUniqId."'");
        $auth = Zend_Auth::getInstance();
        // Delete the information from the session
        $auth->clearIdentity();
        Zend_Session::destroy(TRUE);
        Zend_Registry::set('logoutDeletedSessionId', ['sessionId' => $sessionId, 'internalSessionUniqId' => $internalSessionUniqId]);
    }
  /**
     * Sortiert ein Array in natürlicher Reihenfolge nach Werten unter Berücksichtigung von Sonderzeichen
     *
     * - Achtung: Berücksichtigt Nicht-ASCII-Zeichen derzeit nur aus DE und FR korrekt
     *
     * @param array
     *
     * @return array sortierter Array
     *
     */
    public function natcasesortUtf($array)
    {
        $clean_vals_arr = [];
        $original_keys_arr = array();
        $original_values_arr = array();
        $clean_keys_arr = array();
        $i = 0;
        foreach ($array AS $key => $value)
        {
            $original_keys_arr[$i] = $key;
            $original_values_arr[$i] = $value;
            $clean_vals_arr[$i] = str_replace(array_flip($this->charList), $this->charList, $value);
            $i++;
        }
        natcasesort($clean_vals_arr);
        $result_arr = array();
        foreach ($clean_vals_arr AS $key => $value)
        {
            $original_key = $original_keys_arr[$key];
            $original_value = $original_values_arr[$key];
            $result_arr[$original_key] = $original_value;
        }
        return $result_arr;
    }
    /**
     * Sortiert ein Array in natürlicher Reihenfolge nach Schlüsseln
     *
     * - Achtung: Berücksichtigt Nicht-ASCII-Zeichen derzeit nur aus DE und FR korrekt
     *
     * @param array
     *
     * @return array sortierter Array
     *
     */
    public function natksortUtf($array)
    {
        $original_keys_arr = array();
        $original_values_arr = array();
        $clean_keys_arr = array();

        $i = 0;
        foreach ($array AS $key => $value)
        {
            $original_keys_arr[$i] = $key;
            $original_values_arr[$i] = $value;
            $clean_keys_arr[$i] = str_replace(array_flip($this->charList), $this->charList, $key);
            $i++;
        }
        natcasesort($clean_keys_arr);
        $result_arr = array();
        foreach ($clean_keys_arr AS $key => $value)
        {
            $original_key = $original_keys_arr[$key];
            $original_value = $original_values_arr[$key];
            $result_arr[$original_key] = $original_value;
        }
        return $result_arr;
    }
    /**
     * Wie natksortUtf, nur wird die Reihenfolge der Schlüssel umgekehrt.
     *
     * - Schlüssel-Wert-Zuordnungen bleiben erhalten
     *
     * @param array
     *
     * @return array sortierter Array
     *
     */
    public function natksortReverseUtf($array)
    {
        $array = $this->natksortUtf($array);
        return array_reverse($array, true);
    }
    
    /**
     * ruft mittels einem Fork den übergebenen ActionController mit der übergebenen Action auf
     *
     * - Das Resource-Plugin Portal_Resource_InitForkSession setzt
     *   $session->isFork = true - die Kinder sollten hierauf prüfen, ob sie mittels
     *   Fork aufgerufen sind und wenn nein einen Fehler werfen (Sicherheitsmaßnahme)
     * - die aktuelle Session wird durch forkHttp für alle Namespaces in jeweils eigenen Caches gespeichert.
     *   Der Kindprozess realisiert durch das Resource-Plugin
     *   Portal_Resource_InitForkSession, dass er ein Kind ist und übernimmt die
     *   Session-Daten des parent aus der Session. Danach löscht
     *   Portal_Resource_InitForkSession den Cache
     * - Vorsicht: Eingesetzte Session-Namespaces dürfen keine Unterstriche enthalten
     *   aufgrund des Algorithmus von Portal_Resource_InitForkSession
     * - In der Session des Elternprozesses können unterhalb von
     *   $session->forkHttpCalls[$forkHttpId]['uniqid']['toChild'] Informationen
     *   des Mutterprozesses, die der Kindprozess ggf. für seine spezifische
     *   Verarbeitung benötigt, die also dediziert nur von ihm (und nicht einem
     *   ggf. artgleichen Kindprozess) bearbeitet werden dürfen.
     *
     * @param string $controller Modul und Controller (modul/cont) default Modul kann weggelassen werden
     * @param string $action
     * @param string forkHttpId Eine eindeutige ID, die für alle artgleichen Kindprozesse gleich
     *               sein sollte und als Session-Index auf der Ebene $session->forkHttpCalls[$forkHttpId]
     *               verwendet wird. forkHttpId darf nur Zeichen aus der Menge [a-zA-Z0-9_] enthalten
     * @param bool   callOnlyOnce forkHttp prüft bei callOnlyOnce true, ob ein
     *   File mit der Namen 'callOnlyOnce_'.$forkHttpId.'_'.$session->internalSessionUniqId
     *   im tmp-Verzeichnis existiert. Falls ja, gibt forkHttp false zurück
     *   und setzt keinen Fork ab. Falls das File nicht existiert wird es angelegt
     *   und der Fork abgesetzt. Das Child muss das Flagfile dann als seine letzte
     *   Aktion wieder löschen.
     * @param string $uniqid Muss dem regex [a-zA-Z0-9_]  entsprechen, da sonst
     *             nicht auf dieser Basis ein Cache gebildet werden kann.
     *             Die unique-ID wird dem Kindprozess / Fork mit übergeben und
     *             dient als Erkennungsschlüssel für in der Session abgelegte Daten.
     * @param array $getParams
     *              Alle Werte müssen vom Typ String sein. Die Parameterwerte werden
     *              von forkHttp im Aufruf der Kindfunktion urlencodiert als GET-
     *              Parameter übergeben. Die Schlüssel bilden die
     *              Parameterschlüssel, die Werte die Parameterwerte von getParams
     *
     * @return boolean gibt true zurück, falls fork abgesetzt wurde und false, falls
     *              fork nicht abgesetzt wurde, da bereits ein Kindprozess für
     *              $controller/$action in $session->forkHttpCalls registriert wurde
     *
     *
     */
    function forkHttp(string $controller, string $action, string $forkHttpId, bool $callOnlyOnce, string $uniqid, array $getParams) {
    	if(preg_match('"[^a-zA-Z0-9_]"', $forkHttpId)){
    		throw new Zend_Exception('$forkHttpId enthält Zeichen, die nicht der Menge [a-zA-Z0-9_] entstammen', 0);
    	}
    	$session = new Zend_Session_Namespace();
    	$ro = $session->runtimeOptions;
    	$cache = Zend_Registry::get('cache');
    	if(!isset($session->forkHttpCalls)){
    		$session->forkHttpCalls = array();
    	}
    	if(!isset($session->forkHttpCalls[$forkHttpId])){
    		$session->forkHttpCalls[$forkHttpId] = array();
    	}
    	$sessionUniqId = $session->internalSessionUniqId;
    	//@todo Dieses flagfile Konstrukt ist nicht Race Condition Sicher!
    	if($callOnlyOnce and file_exists($ro->dir->tmp.
    			'/callOnlyOnce_'.$forkHttpId.'_'.$sessionUniqId)){
    			return false;
    	}
    	$session->forkHttpCalls[$forkHttpId][] = $uniqid;
    	if($callOnlyOnce){
    		file_put_contents($ro->dir->tmp.'/callOnlyOnce_'.
    				$forkHttpId.'_'.$sessionUniqId, 'dummy');
    		$session->parentInternalSessionUniqId = $sessionUniqId;
    	}
    	$conn = fsockopen($ro->server->name, 80, $errno, $errstr, 30);
    	if (!$conn) {
    		throw new Zend_Exception('forkHttp konnte keine Verbindung zu '.
    				$ro->server->name.' aufbauen. Fehlernummer: '.
    				$errno.' - Fehlermeldung: '. $errstr, 0 );
    	}
    	//stelle get parameter zusammen
    	$addParams = '';
    	if(count($getParams)>0){
    		foreach($getParams as $key => $val){
    			if(!is_string($val)){
    				throw new Zend_Exception('Der an forkHTTP übergebene get-Parameter '.$key.' hat keinen String als Value. Übergeben durch '.$controller.' '.$action, 0);
    			}
    			$addParams .= '&'.$key.'='.urlencode($val);
    		}
    
    	}
    	//clone die Session in den Cache
    	$sessionArray = $session->getIterator();
    	$cache->save($sessionArray,'session_'.$session->getNamespace().'_Child_communication_'.$uniqid);
    	$employee = new Zend_Session_Namespace('user');
    	$employeeArray = $employee->getIterator();
    	$cache->save($employeeArray,'session_user_Child_communication_'.$uniqid);
    
    	$cmd = 'GET '.APPLICATION_RUNDIR.'/'.$controller.'/'.$action.'?forkNoRegenerateId='.
    			$ro->forkNoRegenerateId.
    			'&uniqid='.$uniqid.'&forkHttpId='.urlencode($forkHttpId).$addParams." HTTP/1.1\r\n";
    	$cmd .= 'Host: '.$ro->server->name."\r\n\r\n";
    	if (!fwrite($conn, $cmd)) {
    		throw new Zend_Exception('forkHttp konnte Verbindung  aufbauen, fwrite dann aber keine Daten schreiben', 0 );
    	}
    	fclose($conn);
    
    	return true;
    }
}
