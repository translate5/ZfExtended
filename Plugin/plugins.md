Plugin installation and activation
----------------------------------
To install a translate5-plugin, put it into the folder /application/modules/editor/Plugins

To activate a plugin, include its bootstrap class-name into the db-table Zf_configuration in the column "value" of the line with the name "runtimeOptions.plugins.active"

To enable most of the currently available core-plugins configure this:
["editor_Plugins_LockSegmentsBasedOnConfig_Bootstrap","editor_Plugins_NoMissingTargetTerminology_Bootstrap","editor_Plugins_SegmentStatistics_Bootstrap","editor_Plugins_TermTagger_Bootstrap","editor_Plugins_Transit_Bootstrap","editor_Plugins_ManualStatusCheck_Bootstrap"]


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

