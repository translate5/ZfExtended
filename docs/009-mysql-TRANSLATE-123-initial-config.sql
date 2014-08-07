--  /*
--  START LICENSE AND COPYRIGHT
--  
--  This file is part of Translate5 Editor PHP Serverside and build on Zend Framework
--  
--  Copyright (c) 2013 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.
-- 
--  Contact:  http://www.MittagQI.com/  /  service (ÄTT) MittagQI.com
-- 
--  This file may be used under the terms of the GNU General Public License version 3.0
--  as published by the Free Software Foundation and appearing in the file gpl3-license.txt 
--  included in the packaging of this file.  Please review the following information 
--  to ensure the GNU General Public License version 3.0 requirements will be met:
--  http://www.gnu.org/copyleft/gpl.html.
-- 
--  For this file you are allowed to make use of the same FLOSS exceptions to the GNU 
--  General Public License version 3.0 as specified by Sencha for Ext Js. 
--  Please be aware, that Marc Mittag / MittagQI take no warranty  for any legal issue, 
--  that may arise, if you use these FLOSS exceptions and recommend  to stick to GPL 3. 
--  For further information regarding this topic please see the attached license.txt
--  of this software package.
--  
--  MittagQI would be open to release translate5 under EPL or LGPL also, if this could be
--  brought in accordance with the ExtJs license scheme. You are welcome to support us
--  with legal support, if you are interested in this.
--  
--  
--  @copyright  Marc Mittag, MittagQI - Quality Informatics
--  @author     MittagQI - Quality Informatics
--  @license    GNU General Public License version 3.0 http://www.gnu.org/copyleft/gpl.html
--              with FLOSS exceptions (see floss-exception.txt and ux-exception.txt at the root level)
--  
--  END LICENSE AND COPYRIGHT 
--  */
-- 

INSERT INTO Zf_configuration (`name`, `confirmed`, `module`, `category`, `value`, `default`, `defaults`, `type`, `description`) VALUES 
('resources.db.matViewLifetime', 1, 'editor', 'system', '14', '14', '', 'integer', 'define the default lifetime in days after which unused materialized views are deleted'),
('runtimeOptions.alike.defaultBehaviour', 1, 'editor', 'system', 'individual', 'individual', 'never,always,individual', 'string', 'Standardverhalten des Wiederholungseditors, mögliche Werte: \'never\', \'always\', \'individual\''),
('runtimeOptions.companyName', 1, 'app', 'company', 'MittagQI - Quality Informatics', 'MittagQI - Quality Informatics', '', 'string', 'Name der Firma unter welcher die Anwendung läuft.'),
('runtimeOptions.contactData.emergencyContactDepartment', 1, 'app', 'company', 'IT-Abteilung', 'IT-Abteilung', '', 'string', 'Ansprechpartner für technische Probleme (wird bei Fehlern angezeigt).'),
('runtimeOptions.contactData.emergencyTelephoneNumber', 1, 'app', 'company', '07473 / 220202', '07473 / 220202', '', 'string', 'Ansprechpartner für technische Probleme (wird bei Fehlern angezeigt).'),
('runtimeOptions.cronIP', 1, 'editor', 'cron', '127.0.0.1', '127.0.0.1', '', 'string', 'cron-IP - the IP-address, which is allowed to call cron scripts'),
('runtimeOptions.defines.ALLOWED_FILENAME_CHARS', 1, 'app', 'base', '\'[^.A-Za-z0-9_!@#$%^&()+={}\\[\\]\\\',~`-]\'', '\'[^.A-Za-z0-9_!@#$%^&()+={}\\[\\]\\\',~`-]\'', '', 'string', 'Regulärer Ausdruck, der innerhalb einer pcre-Zeichenklasse gültig sein muss -  bei Dateiuploads werden alle anderen Zeichen aus dem Dateinamen rausgeworfen'),
('runtimeOptions.defines.DATE_REGEX', 1, 'app', 'base', '"^\\d\\d\\d\\d-[01]\\d-[0-3]\\d [0-2]\\d:[0-6]\\d:[0-6]\\d$"', '"^\\d\\d\\d\\d-[01]\\d-[0-3]\\d [0-2]\\d:[0-6]\\d:[0-6]\\d$"', '', 'string', ''),
('runtimeOptions.defines.EMAIL_REGEX', 1, 'app', 'base', '"^[A-Za-z0-9._%+-]+@(?:[A-Za-z0-9-]+\\.)+[A-Za-z]{2,19}$"', '"^[A-Za-z0-9._%+-]+@(?:[A-Za-z0-9-]+\\.)+[A-Za-z]{2,19}$"', '', 'string', ''),
('runtimeOptions.defines.GUID_REGEX', 1, 'app', 'base', '"^(\\{){0,1}[0-9a-fA-F]{8}\\-[0-9a-fA-F]{4}\\-[0-9a-fA-F]{4}\\-[0-9a-fA-F]{4}\\-[0-9a-fA-F]{12}(\\}){0,1}$"', '"^(\\{){0,1}[0-9a-fA-F]{8}\\-[0-9a-fA-F]{4}\\-[0-9a-fA-F]{4}\\-[0-9a-fA-F]{4}\\-[0-9a-fA-F]{12}(\\}){0,1}$"', '', 'string', ''),
('runtimeOptions.defines.GUID_START_UNDERSCORE_REGEX', 1, 'app', 'base', '"^_[0-9a-fA-F]{8}\\-[0-9a-fA-F]{4}\\-[0-9a-fA-F]{4}\\-[0-9a-fA-F]{4}\\-[0-9a-fA-F]{12}$"', '"^_[0-9a-fA-F]{8}\\-[0-9a-fA-F]{4}\\-[0-9a-fA-F]{4}\\-[0-9a-fA-F]{4}\\-[0-9a-fA-F]{12}$"', '', 'string', ''),
('runtimeOptions.defines.ISO639_1_REGEX', 1, 'app', 'base', '"^([A-Za-z-]{2,3})|([A-Za-z]{2,3}-[A-Za-z]{2})$"', '"^([A-Za-z-]{2,3})|([A-Za-z]{2,3}-[A-Za-z]{2})$"', '', 'string', ''),
('runtimeOptions.dir.locales', 1, 'app', 'system', '../data/locales', '../data/locales', '', 'absolutepath', ''),
('runtimeOptions.dir.logs', 1, 'app', 'system', '../data/cache', '../data/cache', '', 'absolutepath', ''),
('runtimeOptions.dir.tagImagesBasePath', 1, 'editor', 'imagetag', 'modules/editor/images/imageTags', 'modules/editor/images/imageTags', '', 'string', 'Image Tags und Image Tags JSON Verzeichnisse: die Pfadangabe ist vom public-Verzeichnis aus zu sehen ohne beginnenden Slash (http-Pfad). Trennzeichen ist immer \'/\' (Slash).'),
('runtimeOptions.dir.tagImagesJsonBasePath', 1, 'editor', 'imagetag', 'modules/editor/images/imageTagsJson', 'modules/editor/images/imageTagsJson', '', 'string', 'Pfad für die Json-Dateien liegen, welche die Namen aller im Task enthaltenen Taggrafiken enthalten - die Pfadangabe ist vom public-Verzeichnis zu sehen'),
('runtimeOptions.dir.taskData', 1, 'editor', 'system', '../data/editorImportedTasks', '../data/editorImportedTasks', '', 'absolutepath', 'Pfad zu einem vom WebServer beschreibbaren, über htdocs nicht erreichbaren Verzeichnis, in diesem werden die kompletten persistenten (und temporären) Daten zu einem Task gespeichert'),
('runtimeOptions.dir.tmp', 1, 'app', 'system', '../data/tmp', '../data/tmp', '', 'absolutepath', ''),
('runtimeOptions.disableErrorMails.all', 1, 'app', 'logging', '0', '0', '', 'boolean', ''),
('runtimeOptions.disableErrorMails.default', 1, 'app', 'logging', '0', '0', '', 'boolean', 'deaktiviert ausschließlich den Versand der Error-Mails ohne dump'),
('runtimeOptions.disableErrorMails.fulldump', 1, 'app', 'logging', '0', '0', '', 'boolean', ''),
('runtimeOptions.disableErrorMails.minidump', 1, 'app', 'logging', '0', '0', '', 'boolean', 'deaktiviert ausschließlich den Versand der Error-Mails mit minidump'),
('runtimeOptions.disableErrorMails.notFound', 1, 'app', 'logging', '1', '1', '', 'boolean', 'deaktiviert ausschließlich den Versand der Error-Mails ohne dump'),
('runtimeOptions.editor.branding', 1, 'editor', 'layout', '', '', '', 'string', 'see editor skinning documentation'),
('runtimeOptions.editor.editorViewPort', 1, 'editor', 'system', 'Editor.view.ViewPortEditor', 'Editor.view.ViewPortEditor', 'Editor.view.ViewPortEditor,Editor.view.ViewPortSingle', 'string', 'the editor viewport is changeable, default is Editor.view.ViewPort, also available: Editor.view.ViewPortSingle'),
('runtimeOptions.editor.enable100pEditWarning', 1, 'editor', 'system', '1', '1', '', 'boolean', 'if true, then show a warning if editing a 100% match'),
('runtimeOptions.editor.enableQmSubSegments', 1, 'editor', 'mqm', '1', '1', '', 'boolean', 'enables (value = 1) / or disables (value = 0) the complete QM Sub Segment Feature'),
('runtimeOptions.editor.export.regexInternalTags', 1, 'editor', 'export', '"<div\\s*class=\\"([a-z]*)\\s+([gxA-Fa-f0-9]*)\\"\\s*.*?(?!</div>)<span[^>]*id=\\"([^-]*)-.*?(?!</div>).</div>"s', '"<div\\s*class=\\"([a-z]*)\\s+([gxA-Fa-f0-9]*)\\"\\s*.*?(?!</div>)<span[^>]*id=\\"([^-]*)-.*?(?!</div>).</div>"s', '', 'string', 'regex which matches any internal tag in the format translate5 uses inside the database; define including delimiters and modificators'),
('runtimeOptions.editor.export.wordBreakUpRegex', 1, 'editor', 'export', '"([^\\w-/])"u', '"([^\\w-/])"u', '', 'string', 'regex which defines non-word-characters; must include brackets () for the return of the delimiters of preg_split by PREG_SPLIT_DELIM_CAPTURE; define including delimiters and modificators'),
('runtimeOptions.editor.notification.saveXmlToFile', 1, 'editor', 'system', '1', '1', '', 'boolean', 'defines if the generated xml should be additionaly stored in the task directory'),
('runtimeOptions.editor.qmFlagXmlFileDir', 1, 'editor', 'mqm', 'modules/editor', 'modules/editor', '', 'string', 'path beneath APPLICATION_RUNDIR to the directory inside which the standard qmFlagXmlFile resides (must be relative from APPLICATION_RUNDIR without trailing slash)'),
('runtimeOptions.editor.qmFlagXmlFileName', 1, 'editor', 'mqm', 'QM_Subsegment_Issues.xml', 'QM_Subsegment_Issues.xml', '', 'string', 'path to the XML Definition of QM Issues. Used on import.'),
('runtimeOptions.editor.qmSeverity', 1, 'editor', 'mqm', '{"critical": "Critical","major": "Major","minor": "Minor"}', '{"critical": "Critical","major": "Major","minor": "Minor"}', '', 'map', 'Severity Levels are customizable, defined your own severities in the field qmSeverity'),
('runtimeOptions.editor.segment.recreateTermTags.regexInternalTags', 1, 'editor', 'termtagger', '"(<div\\s*class=\\"[a-z]*\\s+[gxA-Fa-f0-9]*\\"\\s*.*?(?!</div>)<span[^>]*id=\\"[^-]*-.*?(?!</div>).</div>)"s', '"(<div\\s*class=\\"[a-z]*\\s+[gxA-Fa-f0-9]*\\"\\s*.*?(?!</div>)<span[^>]*id=\\"[^-]*-.*?(?!</div>).</div>)"s', '', 'string', 'regex which matches any internal tag in the format translate5 uses inside the database; define including delimiters and modificators'),
('runtimeOptions.errorCollect', 1, 'app', 'logging', '0', '0', '', 'boolean', 'Wert mit 1 aktiviert grundsätzlich das errorCollecting im Errorhandler. D. h. Fehler werden nicht mehr vom ErrorController, sondern vom ErrorcollectController behandelt und im Fehlerfall wird nicht sofort eine Exception geworfen, sondern die Fehlerausgabe erfolgt erst für alle Fehler gesammelt am Ende jedes Controller-Dispatches. Fehlermails und Logging analog zum normalen ErrorController. Wert 0 ist die empfohlene Standardeinstellung, da bei sauberer Programmierung schon ein fehlender Array-Index (also ein php-notice) zu unerwarteten Folgeerscheinungen führt und daher nicht kalkulierbare Nachwirkungen auf Benutzer und Datenbank hat. Wert kann über die Zend_Registry an beliebiger Stelle im Prozess per Zend_Registry aktiviert werden. Damit diese Einstellung greifen kann, muss das Resource-Plugin ZfExtended_Controllers_Plugins_ErrorCollect in der application.ini aktiviert sein'),
('runtimeOptions.extJs.basepath.407', 1, 'app', 'system', '/ext-4.0.7', '/ext-4.0.7', '', 'string', 'Ext JS Base Verzeichnis'),
('runtimeOptions.extJs.cssFile', 1, 'editor', 'layout', '/resources/css/ext-all.css', '/resources/css/ext-all.css', '', 'string', 'Ext JS CSS File, wird automatisch um den extJsBasepath ergänzt; alternativ: ext-all.css durch ext-all-gray.css ersetzen'),
('runtimeOptions.fileSystemEncoding', 1, 'app', 'system', 'UTF-8', 'UTF-8', '', 'string', 'encoding von Datei- und Verzeichnisnamen im Filesystem (muss von iconv unterstützt werden)'),
('runtimeOptions.forkNoRegenerateId', 1, 'app', 'base', 'C1D11C25-45D2-11D0-B0E2-444553540000', 'C1D11C25-45D2-11D0-B0E2-444553540000', '', 'string', 'ID die einem Fork übergeben wird und verhindert, dass der Fork Zend_Session::regenerateId aufruft. Falls dieser Quellcode öffentlich wird: Diesen String bei jeder Installation individuell definieren, um Hacking vorzubeugen (beliebiger String gemäß [A-Za-z0-9])'),
('runtimeOptions.headerOptions.height', 1, 'editor', 'layout', '0', '0', '', 'integer', 'Nur mit ViewPortSingle: Definiert die Headerhöhe in Pixeln.'),
('runtimeOptions.headerOptions.pathToHeaderFile', 1, 'editor', 'layout', '', '', '', 'string', 'Nur mit ViewPortSingle: Diese Datei wird als Header eingebunden. Die Pfadangabe ist relativ zum globalen Public Verzeichnis.'),
('runtimeOptions.imageTag.backColor.B', 1, 'editor', 'imagetag', '163', '163', '', 'integer', 'Blau-Wert der Hintergrundfarbe'),
('runtimeOptions.imageTag.backColor.G', 1, 'editor', 'imagetag', '255', '255', '', 'integer', 'Grün-Wert der Hintergrundfarbe'),
('runtimeOptions.imageTag.backColor.R', 1, 'editor', 'imagetag', '57', '57', '', 'integer', 'Rot-Wert der Hintergrundfarbe'),
('runtimeOptions.imageTag.fontColor.B', 1, 'editor', 'imagetag', '0', '0', '', 'integer', 'Blau-Wert der Schriftfarbe'),
('runtimeOptions.imageTag.fontColor.G', 1, 'editor', 'imagetag', '0', '0', '', 'integer', 'Grün-Wert der Schriftfarbe'),
('runtimeOptions.imageTag.fontColor.R', 1, 'editor', 'imagetag', '0', '0', '', 'integer', 'Rot-Wert der Schriftfarbe'),
('runtimeOptions.imageTag.fontFilePath', 1, 'editor', 'imagetag', 'arial.ttf', 'arial.ttf', '', 'absolutepath', 'muss ein True Type Font sein'),
('runtimeOptions.imageTag.fontSize', 1, 'editor', 'imagetag', '9', '9', '', 'integer', ''),
('runtimeOptions.imageTag.height', 1, 'editor', 'imagetag', '14', '14', '', 'integer', ''),
('runtimeOptions.imageTag.horizStart', 1, 'editor', 'imagetag', '0', '0', '', 'integer', 'horizontalrer Startpunkt der Schrift von der linken unteren Ecke aus'),
('runtimeOptions.imageTag.paddingRight', 1, 'editor', 'imagetag', '1', '1', '', 'integer', ''),
('runtimeOptions.imageTag.vertStart', 1, 'editor', 'imagetag', '11', '11', '', 'integer', 'vertikaler Startpunkt der Schrift von der linken unteren Ecke aus'),
('runtimeOptions.imageTags.qmSubSegment.backColor.B', 1, 'editor', 'qmimagetag', '21', '21', '', 'integer', 'Blau-Wert der Hintergrundfarbe'),
('runtimeOptions.imageTags.qmSubSegment.backColor.G', 1, 'editor', 'qmimagetag', '130', '130', '', 'integer', 'Grün-Wert der Hintergrundfarbe'),
('runtimeOptions.imageTags.qmSubSegment.backColor.R', 1, 'editor', 'qmimagetag', '255', '255', '', 'integer', 'Rot-Wert der Hintergrundfarbe'),
('runtimeOptions.imageTags.qmSubSegment.horizStart', 1, 'editor', 'qmimagetag', '2', '2', '', 'integer', 'horizontalrer Startpunkt der Schrift von der linken unteren Ecke aus'),
('runtimeOptions.imageTags.qmSubSegment.paddingRight', 1, 'editor', 'qmimagetag', '3', '3', '', 'integer', ''),
('runtimeOptions.import.createArchivZip', 1, 'editor', 'import', '1', '1', '', 'boolean', 'gibt an ob eine Archivierung der importierten Daten als Zip Datei erfolgen soll'),
('runtimeOptions.import.csv.delimiter', 1, 'editor', 'csv', ',', ',', '', 'string', 'define csv delimiter char'),
('runtimeOptions.import.csv.enclosure', 1, 'editor', 'csv', '"', '"', '', 'string', 'define csv enclosure char'),
('runtimeOptions.import.csv.fields.mid', 1, 'editor', 'csv', 'mid', 'mid', '', 'string', 'define mid and source column-headers for csv-file-import, all other columns are used as (alternate) translation(s)'),
('runtimeOptions.import.csv.fields.source', 1, 'editor', 'csv', 'quelle', 'quelle', '', 'string', 'define mid and source column-headers for csv-file-import, all other columns are used as (alternate) translation(s)'),
('runtimeOptions.import.enableSourceEditing', 1, 'editor', 'import', '0', '0', '', 'boolean', 'enables source editing in general (can then be en/disabled per task)'),
('runtimeOptions.import.keepFilesOnError', 1, 'editor', 'import', '0', '0', '', 'boolean', 'keep also the task files after an exception while importing, if false the files will be deleted'),
('runtimeOptions.import.languageType', 1, 'editor', 'import', 'rfc5646', 'rfc5646', 'rfc5646,unix,lcid', 'string', 'Beim Import können die zu importierenden Sprachen in verschiedenen Formaten mitgeteilt werden'),
('runtimeOptions.import.proofReadDirectory', 1, 'editor', 'import', 'proofRead', 'proofRead', '', 'string', ''),
('runtimeOptions.import.referenceDirectory', 1, 'editor', 'import', 'referenceFiles', 'referenceFiles', '', 'string', 'Verzeichnisnamen unter welchem innerhalb des Import Ordners die Referenz Dateien gesucht werden soll'),
('runtimeOptions.import.relaisDirectory', 1, 'editor', 'import', 'relais', 'relais', '', 'string', 'Relaissprachen Steuerung: Befinden sich im ImportRoot zwei Verzeichnisse mit den folgenden Namen, so wird zu dem Projekt eine Relaissprache aus den Daten im relaisDirectory importiert. Die Inhalte in relais und proofRead müssen strukturell identisch sein'),
('runtimeOptions.import.reportOnNoRelaisFile', 1, 'editor', 'import', '1', '1', '', 'boolean', 'gibt an, ob bei fehlenden Relaisinformationen eine Fehlermeldung ins Log geschrieben werden soll'),
('runtimeOptions.import.taskWorkflow', 1, 'editor', 'import', 'editor_Workflow_Default', 'editor_Workflow_Default', '', 'string', ''),
('runtimeOptions.loginUrl', 1, 'editor', 'editor', '/login/logout', '/login/logout', '', 'string', 'http-orientierte URL auf die umgelenkt wird, wenn REST ein 401 Unauthorized wirft'),
('runtimeOptions.mail.generalBcc', 1, 'app', 'email', '[]', '[]', '', 'list', ''),
('runtimeOptions.messageBox.delayFactor', 1, 'editor', 'layout', '1.0', '1.0', '', 'string', 'Faktor um die Dauer der eingeblendeten Nachrichten zu beeinflussen (Dezimalzeichen = Punkt!)'),
('runtimeOptions.publicAdditions.css', 1, 'editor', 'layout', '["css/editorAdditions.css?v=1"]', '["css/editorAdditions.css?v=1"]', '', 'list', 'CSS Dateien welche zusätzlich eingebunden werden sollen. Pfad relativ zum Web-Root der Anwendung. Per Default wird das CSS zur Anzeige des Translate5 Logos eingebunden.'),
('runtimeOptions.segments.disabledFields', 1, 'editor', 'metadata', '["editableColumn“]', '["editableColumn“]', '', 'list', 'Column itemIds der Spalten die per Default ausgeblendet sein sollen. Die itemIds werden in der ui/segments/grid.js definiert, in der Regel Spaltenname + \'Column\''),
('runtimeOptions.segments.qualityFlags', 1, 'editor', 'metadata', '{"1": "Einwandfrei", "2": "Leichte Mängel", "3": "Muss überarbeitet werden"}', '{"1": "Einwandfrei", "2": "Leichte Mängel", "3": "Muss überarbeitet werden"}', '', 'map', 'Verfügbare Flags für QM im Bereich der Segment-Metadaten'),
('runtimeOptions.segments.showStatus', 1, 'editor', 'metadata', '1', '1', '', 'boolean', 'if 1 the status-column and the status area in the segment-details are shown, if 0 they are not'),
('runtimeOptions.segments.stateFlags', 1, 'editor', 'metadata', '{"1": "Status 1", "2": "Status 2", "3": "Status 3"}', '{"1": "Status 1", "2": "Status 2", "3": "Status 3"}', '', 'map', 'Verfügbare Flags für Status im Bereich der Segment-Metadaten'),
('runtimeOptions.sendMailLocally', 1, 'app', 'logging', '0', '0', '', 'boolean', 'Legt fest, ob alle E-Mails lokal verschickt werden sollen, dann wird bei allen E-Mails alles ab dem @ bis zum Ende der Adresse beim, Versenden der Mail als Empfängeradresse weggelassen. Aus new@marcmittag.de wird also new'),
('runtimeOptions.server.name', 1, 'app', 'system', 'www.translate5.net', 'www.translate5.net', '', 'string', 'Domainname under which de application is running'),
('runtimeOptions.server.pathToIMAGES', 1, 'app', 'system', '/images', '/images', '', 'string', 'http-orientierter Pfad zum image-Verzeichnis'),
('runtimeOptions.server.pathToJsDir', 1, 'app', 'system', '/js', '/js', '', 'string', 'http-orientierter Pfad zum js-Verzeichnis'),
('runtimeOptions.server.protocol', 1, 'app', 'system', 'http://', 'http://', 'http://,https://', 'string', 'Protokoll unter der die Anwendung erreichbar ist'),
('runtimeOptions.showErrorsInBrowser', 1, 'app', 'logging', '0', '0', '', 'boolean', 'Bei Wert 0 zeigt er für den Produktivbetrieb dem Anwender im Browser nur eine allgemeine Fehlermeldung und keinen Trace'),
('runtimeOptions.singleUserRestriction', 1, 'app', 'login', '0', '0', '', 'boolean', 'disable multiple sessions for one userGuid (needed for DEMO Platforms with public user credentials)'),
('runtimeOptions.tbx.defaultTermStatus', 1, 'editor', 'termtagger', 'admittedTerm', 'admittedTerm', '', 'string', 'Default-Wert für den Term-Status beim Import, falls kein Status in TBX angegeben'),
('runtimeOptions.termTagger.debug', 1, 'editor', 'termtagger', '0', '0', '', 'boolean', 'entweder false oder true als Wort auf jeden Fall umgeben von einfachen Anführungszeichen'),
('runtimeOptions.termTagger.dir', 1, 'editor', 'termtagger', 'modules/editor/ThirdParty/XliffTermTagger/application', 'modules/editor/ThirdParty/XliffTermTagger/application', '', 'absolutepath', ''),
('runtimeOptions.termTagger.fuzzy', 1, 'editor', 'termtagger', '1', '1', '', 'boolean', 'entweder false oder true als Wort auf jeden Fall umgeben von einfachen Anführungszeichen'),
('runtimeOptions.termTagger.fuzzyPercent', 1, 'editor', 'termtagger', '70', '70', '', 'integer', 'eine Zahl zwischen 0 und 100; auf jeden Fall umgeben von einfachen Anführungszeichen; Leerstring zum Ausschalten'),
('runtimeOptions.termTagger.javaExec', 1, 'editor', 'termtagger', 'java', 'java', '', 'string', 'absoluter Pfad zum TermTagger-Verzeichnis auf dem Filesystem ohne schließenden Slash'),
('runtimeOptions.termTagger.lowercase', 1, 'editor', 'termtagger', '1', '1', '', 'boolean', 'entweder false oder true als Wort auf jeden Fall umgeben von einfachen Anführungszeichen'),
('runtimeOptions.termTagger.maxWordLengthSearch', 1, 'editor', 'termtagger', '2', '2', '', 'integer', 'max. word count for fuzzy search'),
('runtimeOptions.termTagger.minFuzzyStartLength', 1, 'editor', 'termtagger', '2', '2', '', 'integer', 'min. number of chars at the beginning of a compared word in the text, which have to be identical to be matched in a fuzzy search'),
('runtimeOptions.termTagger.minFuzzyStringLength', 1, 'editor', 'termtagger', '5', '5', '', 'integer', 'min. char count for words in the text compared in fuzzy search'),
('runtimeOptions.termTagger.removeTaggingOnExport.diffExport', 1, 'editor', 'termtagger', '1', '1', '', 'boolean', 'removes termTagging on export, because Studio sometimes seems to destroy change-history otherwhise'),
('runtimeOptions.termTagger.removeTaggingOnExport.normalExport', 1, 'editor', 'termtagger', '1', '1', '', 'boolean', 'removes termTagging on export, because Studio sometimes seems to destroy change-history otherwhise'),
('runtimeOptions.termTagger.stemmed', 1, 'editor', 'termtagger', '1', '1', '', 'boolean', 'entweder false oder true als Wort auf jeden Fall umgeben von einfachen Anführungszeichen'),
('runtimeOptions.termTagger.targetStringMatch', 1, 'editor', 'termtagger', '["zh", "ja", "ko"]', '["zh", "ja", "ko"]', '', 'list', 'defines for which targetLanguages the termtagging should only be done by stringmatching'),
('runtimeOptions.translation.sourceCodeLocale', 1, 'app', 'system', 'de', 'de', '', 'string', 'should be the default-locale in translation-setup, if no target locale is set'),
('runtimeOptions.translation.sourceLocale', 1, 'app', 'system', 'ha', 'ha', '', 'string', 'setze auf Hausa als eine Sprache, die wohl nicht als Oberflächensprache vorkommen wird. So kann auch das deutsche mittels xliff-Datei überschrieben werden und die in die Quelldateien einprogrammierten Werte müssen nicht geändert werden'),
('runtimeOptions.workflow.default.anonymousColumns', 1, 'editor', 'workflow', '0', '0', '', 'boolean', 'If true the column labels are getting an anonymous column name.'),
('runtimeOptions.workflow.default.visibility', 1, 'editor', 'workflow', 'show', 'show', 'show,hide,disable', 'string', 'visiblity of non-editable targetcolumn(s): For "show" or "hide" the user can change the visibility of the columns in the usual way in the editor. If "disable" is selected, the user has no access at all to the non-editable columns.'),
('runtimeOptions.workflow.dummy.anonymousColumns', 1, 'editor', 'workflow', '1', '1', '', 'boolean', 'If true the column labels are getting an anonymous column name.'),
('runtimeOptions.workflow.dummy.visibility', 1, 'editor', 'workflow', 'show', 'show', 'show,hide,disable', 'string', 'visiblity of non-editable targetcolumn(s): For "show" or "hide" the user can change the visibility of the columns in the usual way in the editor. If "disable" is selected, the user has no access at all to the non-editable columns.'),
('runtimeOptions.workflow.ranking.anonymousColumns', 1, 'editor', 'workflow', '1', '1', '', 'boolean', 'If true the column labels are getting an anonymous column name.'),
('runtimeOptions.workflow.ranking.visibility', 1, 'editor', 'workflow', 'disable', 'disable', 'show,hide,disable', 'string', 'visiblity of non-editable targetcolumn(s): For "show" or "hide" the user can change the visibility of the columns in the usual way in the editor. If "disable" is selected, the user has no access at all to the non-editable columns.'),
('runtimeOptions.workflows.0', 1, 'editor', 'workflow', 'editor_Workflow_Default', 'editor_Workflow_Default', '', 'string', 'array with all available workflow classes for this installations'),
('runtimeOptions.workflows.1', 1, 'editor', 'workflow', 'editor_Workflow_Dummy', 'editor_Workflow_Dummy', '', 'string', 'array with all available workflow classes for this installations');
