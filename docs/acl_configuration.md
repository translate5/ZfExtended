# ACL Configuration Mechanism

Each module can have a aclConfig.ini which contains the role and resource definitions for this module.

The defined roles and resources can then be used in the rule definitions, which are stored in the DB. Normally only the rules differ between different installations, so a custom aclConfig.ini is normally not needed.

Howeever, the aclConfig.ini can be overwritten by a aclConfig.ini in the inioverwrites directory. 

Example:

  application/modules/editor/configs/aclConfig.ini
  iniOverwrites/tlauria/editorAclConfig.ini

The second file does completly overwrite the first one!
This is needed rarly, but can be the case if new functionality which needs custom acl comes in by factory overwriting.


## ACL Role and Resource definitions

The above mentioned aclConfig.ini contains the following sections:

	[roles]
	0 = noRights
	1 = basic
	5 = editor
	6 = pm

	[resources]
	0 = frontend
	1 = loadAllTasks

### Roles section
The [roles] section defines the available roles for users. A user can have multiple roles, each *rights* respectively *rules* of the different roles are added up, so that the user does have all rights of all given roles.

Every logged in user gets the roles **noRight** and **basic** per default!

### Resource section
Resources are all parts of the application which have to been secured by ACLs. Per default every controller is available as resource. All other resources have to be defined in the [resource] section of the aclConfig.ini. 

This is for example the *frontend* resource which holds all frontend rights in Translate5.

## ACL Rule Definition in the DB

In the table Zf_acl_rules are defined the rules for this application instance.

Therefore the following policy is used: 

  All rules which are defined are valid!
  All not defined rules are therefore not valid!

The table contains the affected *module*, the affected *role*, the *resource* for which the access has to be allowed and optionally the affected *controllerAction* or the keyword *all* if the access to the whole resource should be granted.

Examples:

Module  | role      | resource      | action
:---    | :---      | :---          | :---
editor  | editor    | editor_task   | index
editor  | editor    | editor_task   | put
editor  | editor    | frontend      | editorEditTask
editor  | editor    | frontend      | editorFinishTask
editor  | noRights  | error         | all
editor  | pm        | adminUserFrontendController | all

One whole row is unique.







;############ Dokumentation Einbindung von aclConfig.ini-Dateien ############
;- Die aclConfig.ini-Datei ist Modulbezogen und liegt im Pfad /application/modules/MODULNAME/configs/aclConfig.ini.
;  Sie ist für das aktuelle Modul - und nur für dieses - zuständig. aclConfig-Dateien
;  anderer Module werden nicht geladen.
;- Im Ordner /application/iniOverwrites/aclConfigAGENCY/ können
;  sowohl die aclConfig.inis der verschiedenen Module überschrieben werden
;- Dazu gibt es folgende Namenskonventionen:
;  -- defaultAclConfig.ini überschreibt die aclConfig.ini des default-Moduls
;  -- editorAclConfig.ini überschreibt die aclConfig.ini eines Moduls mit dem Namen Editor, etc.
;- Zum Überschreiben kann einfach die ursprüngliche aclConfig.ini kopiert, angepasst und ggf.
;  alle Definitionen, die nicht überschrieben werden rausgelöscht werden (dann
;  gelten die original definierten Werte)
; Achtung: Im Unterschied zur application.ini findet kein Erben der Werte der
; ursprünglichen aclConfig statt - alle gewünschten Werte müssen daher in der
; agency-spezifischen ini-Datei aufgeführt werden



; Definiere Regeln und Privilegien
; Erläuterung:
; - Alle Zugriffsoptionen, die hier nicht definiert sind, sind verboten
; - Alle Zugriffsoptionen, die hier definiert sind, sind erlaubt
;
; - In der ersten Ebene des auf ini-Basis erzeugten Acl-Objekts steht die Rolle
; - In der zweiten Ebene (nach dem ersten Punkt) steht die Resource (= der Controller)
; - Dann kommt entweder das Gleichzeichen und danach das Privileg "all" (alle Actions
;   des Controllers können ausgeführt werden, inkl. geerbter Actions) oder
;   noch ein Punkt und ein Integer in aufsteigender Reihenfolge für die gleiche
;   Kombination von vorgestellter Rolle plus Resource. Hieraus wird im Acl-Objekt
;   ein Objektbaum von Privilegien, der bei Bedarf in ein Array konvertiert werden kann