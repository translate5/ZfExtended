Plugin installation and activation
----------------------------------
To install a translate5-plugin, put it into the folder /application/modules/editor/Plugins

To activate a plugin, include its bootstrap class-name into the db-table Zf_configuration in the column "value" of the line with the name "runtimeOptions.plugins.active"

To install plugin-specific sql, run the translate5 updater.

To enable most of the currently available core-plugins configure this:
["editor_Plugins_LockSegmentsBasedOnConfig_Bootstrap","editor_Plugins_NoMissingTargetTerminology_Bootstrap","editor_Plugins_SegmentStatistics_Bootstrap","editor_Plugins_TermTagger_Bootstrap","editor_Plugins_Transit_Bootstrap","editor_Plugins_ManualStatusCheck_Bootstrap"]

Plugin deacitvation and deinstallation
--------------------------------------
Ensure, that no running process which involve the plugin exist. Otherwise existing entries in Zf_worker could lead to problems.


Plugin List
===========

LockSegmentsBasedOnConfig (Import Plugin)
-----------------------------------------

Sets the segment editable flag out of configurable other data, normally segment meta data.
Plugin Bootstrap: editor_Plugins_LockSegmentsBasedOnConfig_Bootstrap

NoMissingTargetTerminology (Import Plugin)
------------------------------------------

Sets a Segment Meta Flag if there all Target Terminology exists, compared to the source Terminology.
Plugin Bootstrap: editor_Plugins_NoMissingTargetTerminology_Bootstrap

SegmentStatistics (Import Plugin)
---------------------------------

Counts several Segment Statistics, currently directly after Import.
use only one of the available Bootstraps!
Plugin Bootstrap: editor_Plugins_SegmentStatistics_Bootstrap
This Bootstrap generates a statistic for all segments.

Plugin Bootstrap: editor_Plugins_SegmentStatistics_BootstrapEditableOnly
This Bootstrap generates a statistic for editable segments only. 

TermTagger (Import / Post Segment Edit Plugin)
----------------------------------------------

Enables TermTagging after Import and retagging after Segment edit. 
Communicates therefore with the TermTagger.
Plugin Bootstrap: editor_Plugins_TermTagger_Bootstrap

Transit (Import / FileParser Plugin)
------------------------------------

Enhances the default import process, so that it can deal with the special file structure of Transit files.
Plugin Bootstrap: editor_Plugins_Transit_Bootstrap

ManualStatusCheck (On Workflow Status Change Plugin)
----------------------------------------------------

Checks if a status is set in all segments, if not then the task can not be finished.
Plugin Bootstrap: editor_Plugins_ManualStatusCheck_Bootstrap


Plugin order and plugin dependency
----------------------------------

In the future there should be a Plugin Manager which is able to handle the order
of plugins, which bind to the same event.

Currently this is handled by setting the event-priority of the plugin when binding
to an event.

Currently this is relevant for the following plugins:
editor_Plugins_NoMissingTargetTerminology_Bootstrap         -100 (afterImport)
editor_Plugins_LockSegmentsBasedOnConfig_Bootstrap          -9000 (afterImport)
editor_Plugins_SegmentStatistics_BootstrapEditableOnly      -10000 (afterImport)
editor_Plugins_SegmentStatistics_BootstrapEditableOnly      -11000 (clean up worker, afterImport)
editor_Plugins_SegmentStatistics_Bootstrap                  -10000 (afterImport)

editor_Plugins_SegmentStatistics_Bootstrap                  -10000 (afterExport)
editor_Plugins_SegmentStatistics_BootstrapEditableOnly      -10000 (afterExport)
editor_Plugins_SegmentStatistics_BootstrapEditableOnly      -11000 (clean up worker, afterExport)

Write your own plugin
---------------------

1. Plugins must inherit the class ZfExtended_Plugin_Abstract

2. Plugin naming conventions must follow Zend Framework 1 conventions

3. Plugins must reside in a folder following the convention 
    /application/modules/*MODULENAME*/Plugins/*PLUGINNAME*

4. Plugin-specific sql can consist of one or more sql-files. In order for translate5 updater to install them, they must be put into the folder "/application/modules/*MODULENAME*/Plugins/*PLUGINNAME*/database". Every sql-file must be accompanied by a deinstall-file with the name "deinstall_NAME_OF_THE_INSTALL_SQL_FIlE.SQL", otherwise the plugin will not be installed.

5. Plugins must bind to existing events inside translate5. If you are missing a specific event, contact translate5 core team. In most of the cases it should be easy to add the event. To find existing events, grep in the translate5 php-source for "->trigger('" and have a look, if that trigger derives from ZfExtended_EventManager. As example look for this trigger: "$eventManager->trigger('afterImport', $this, array('task' => $this->task));"

6. As an example look for the plugins in the folder /application/modules/editor/Plugins, with the exception of the TermTagger and the Transit-Plugin.