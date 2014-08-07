ZfExtended Configuration Mechanism
==================================
A ZfExtended Application have several places to store configuration parameters. 

This are (all following paths relative to APPLICATION_PATH):

    /config/application.ini
    /config/installation.ini
    /modules/MODULE/configs/module.ini
    In Database: PHP Entity Class DbConfig
    
Normally only the settings of the actual module are loaded. The settings of other modules can be imported if needed.
    
Contents of /config/application.ini
-----------------------------------
This file contains the system application settings for the whole application, normally not needed to be changed by the client.

This are:
* php.ini settings
* includePaths
* All Zend related application settings:
    * bootstrap and autoloader configuration
    * resource plugin inclusion
    * resource plugin configuration
* Special ZfExtended related features:
    * extVersionMapping
    * factoryOverwrites
* And also application related settings not storable in the DB.

**The contents of the file are overwritten by update / deploy process.**

Contents of /config/installation.ini
------------------------------------
This file is installation specific and will be loaded after application.ini and overwrites therefore the settings made in application.ini.

This are in general: DB credentials and settings, Log Mail Receiver and installation specific paths.

installation.ini would not be overwritten by update / deploy process.

The default content is: 

    resources.db.params.host = "localhost"
    resources.db.params.username = "dbUsername"
    resources.db.params.password = "topsecret"
    resources.db.params.dbname = "dbName"

    resources.mail.defaultFrom.name = Service MittagQI
    resources.mail.defaultFrom.email = thomas@mittagqi.com


Contents of /modules/MODULE/configs/module.ini
----------------------------------------------
This file contains the same contens as application.ini, only that they are concentrated to the needs of the module containing this file. 

The contents can also be overwritten / extended by installation.ini.

**The contents of the file are overwritten by update / deploy process.**

    
Contents of Database Config: PHP DbConfig
-----------------------------------------
All other settings, the so called runTimeOptions are stored in the DB table "Zf_configuration".

The contents are loaded by the Resource Plugin DbConfig and stored also in the main config object in the registry.

On update / deploy the changed values remains in DB. How the user is informed about the changements is influenced by the SQL command providing the change.

Changement of Configuration by the client
-----------------------------------------
Normally only a few ini settings has to be changed for an installation, these are kept as described earlier in the installation.ini. This are normally the DB credentials and the Log Email Receiver / Sender adress.

All other settings can be changed in the configuration table. **Actually this has to be done manually, since we dont have a GUI therefore yet!**

Special Configuration Parameters
================================

Factory Overwrites (set in INI)
-------------------------------
ZfExtended is providing a factory mechanism for object instanciation where the desired class can be overwritten and dynamically be replaced by the factory method. The overwrite mechanism is also applicable for controllerHelpers, viewHelpers und layoutHelpers.

The configuration is done in the application.ini.

    factoryOverwrites.models.ORIGINAL_CLASSNAME = NEW_CLASSNAME
    factoryOverwrites.helper.ORIGINAL_HELPERNAME = NEW_HELPERNAME

The following preconditions has to be fullfilled for a working overwrite mechnism:
* NEW_CLASSNAME must be loadable by the Autoloader
* actually only working for models and utility classes, controllerHelper, viewHelper und layoutHelper
* controllers can not be overwritten since they represent the application interface. If needed this functionality should be moved to an own module, or a dedicated overwriteable class or controllerHelper.
* Controller plugins must not be overwritten by factory since they can be changed in the configuration of the app:


    resources.frontController.plugins.addgeneraltitle = "ZfExtended_Controllers_Plugins_AddGeneralTitle"
    ; can be replaced in the installation.ini with
    resources.frontController.plugins.addgeneraltitle = "controllers_Plugins_AddGeneralTitle"


Logging Flags (set in DB)
-------------------------
All internal used exceptions have a flag "isLogging" which defines if logging is en- or disabled for this type of exceptions. The content of this flag can be configured by the following settings.

You can specify directly an exception class:

    runtimeOptions.logging.myException = true
    
Or you can prepend optional module.controller.action names e.g.:

    runtimeOptions.logging.default.delete.index.myExecption = true
    
    
Since the current module can be overwritten, it can happen that you have to use another controller as the controller was originally defined in:
For example a delete controller in the default module of a client specific Translate5 adaption uses the editor module (with BaseIndex::setModule), we have to use this mod name here instead "default":

    runtimeOptions.logging.editor.delete.index.ZfExtended_BadMethodCallException = true


ExtJS Configuration (set in INI and DB)
--------------------------------
The settings below are used by ZfExtended_Controller_Helper_ExtJs and configure the ExtJS Version to be used by the desired Module / Controller / Action Combination.

In the DB the client can set the path to different ExtJS Versions needed by the application:
    
    runtimeOptions.extJs.basepath.407 = WEBPATH/TO/EXTJS 407/
    runtimeOptions.extJs.basepath.411 = WEBPATH/TO/EXTJS 411/

The INI index after basepath must contain three numbers.

The application determines by the follwing INI settings which ExtJS Version (411 or 407) is needed by a specific module, controller or action:

    extVersionMapping.moduleName = basePathIndex
    extVersionMapping.moduleName.ControllerName = basePathIndex
    extVersionMapping.moduleName.ControllerName.ActionName = basePathIndex    

* All modul, controller and action names have to be notated in lowercase letters.
* on every level a default value can be defined with DEFAULT where the keyword DEFAULT is completly in uppercase. 



    
**Examples:**

    extVersionMapping.default.index = 340        
In module *default* the index controller and all his actions use ExtJS version 340, which is defined in *runtimeOptions.extJs.basepath.340*.

    extVersionMapping.modulx.foo = 411
    modulx.DEFAULT = 340
In modulx the foo controller uses ExtJS Version 411, all other controllers use 340.

    modulx.foo.index = 411     ;=> same for actions
    modulx.foo.DEFAULT = 340   
Same for actions, in controller foo of modulx the indexActions uses 411 all others 340.
**A combination of modulx.foo = 411 und modulx.foo.index = 340 is not possible.**

Is a module controller action combination not listed, or if there are no mappings defined, the ExtJS basepath with the highest version number (as defined in the INI key) is used.


DB Config Types
---------------
In the DB configuration table different types of a configuration value can be used.
This are:
1. string
2. integer
3. boolean
4. list 
5. map
6. absolutepath

*string*, *integer* and *boolean* are self explaining.

A *list* is an array value and therefore stored as JSON array string and is evaluated in PHP to an array with numeric indizes starting at index 0.

A *map* is an ordered list with named keys, it is stored as JSON object string, and is evaluated in PHP to an assoc array with named keys. The order of the items is preserved.

A *absolutepath* is basicly a string which is prepended in PHP with the APPLICATION_PATH.