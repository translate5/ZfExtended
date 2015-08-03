Plugin Documentation
====================

See http://confluence.translate5.net/display/TPLO/


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