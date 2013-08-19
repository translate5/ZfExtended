###################################
Anleitung für angepasste Mail Texte.
###################################

Verwendung von HTML E-Mails
***************************
Eine HTML Datei muss zusätzlich den Inhalt als normalen Text beinhalten (HTML-Mails
enthalten immer einen Plain-Text-Bereich für Mail-Clients, die keine HTML-Mails
anzeigen können oder sollen).
Der normale Text für den Plain-Text-Bereich wird dabei in der Variablen
"$this->textbody" abgespeichert.
Sobald die Variable "$this->textbody" befüllt ist, kann im Bereich ab
echo sprintf($this->translate->_( in der phtml-Datei)
Text HTML zur Formatierung verwendet werden.


Verwendung von Grafiken in der HTML-E-Mail
******************************************
Um Grafiken in die HTML Mail einzubinden, muss ein weiteres Verzeichnis für die
Grafiken angelegt werden. Das neue Verzeichnis muss innerhalb dem
kundenspezifischen Verzeichnis befinden. Die Grafiken werden Template spezifisch
festgelegt. Dabei besteht der Verzeichnisname aus dem Templatenamen, wobei das
Suffix .phtml durch Images ersetzt wird.
Sprich für das Template "adminMailhistory.phtml" liegen die Grafiken im
Verzeichnis "adminMailhistoryImages".
Die in diesem Verzeichnis abgelegten Datei werden automatisch in die E-Mail
eingebunden. Um die Grafiken im HTML verwenden zu können, wird der MD5 Hash des
Orginaldateinamens benötigt. Beispiel: <img src="cid:123123" />
Wobei 123123 der MD5 Hash der Datei ist.

Eine exemplarische Einbindung findet sich im Template des Demo Verzeichnises.