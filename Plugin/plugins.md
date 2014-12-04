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
Plugin Bootstrap: editor_Plugins_SegmentStatistics_Bootstrap

TermTagger (Import / Post Segment Edit Plugin)
----------------------------------------------

Enables TermTagging after Import and retagging after Segment edit. 
Communicates therefore with the TermTagger.
Plugin Bootstrap: editor_Plugins_TermTagger_Bootstrap

Transit (Import / FileParser Plugin)
------------------------------------

Enhances the default import process, so that it can deal with the special file structure of Transit files.
Plugin Bootstrap: editor_Plugins_Transit_Bootstrap

ManualStatusCheck (Post Segment Edit Plugin)
--------------------------------------------

Checks if a status is set in the segment, if not the segment can not be saved.
Plugin Bootstrap: editor_Plugins_ManualStatusCheck_Bootstrap


Plugin order and plugin dependency
----------------------------------

In the future there should be a Plugin Manager which is able to handle the order
of plugins, which bind to the same event.

Currently this is handled by setting the event-priority of the plugin when binding
to an event.

Currently this is relevant for the following plugins:

editor_Plugins_SegmentStatistics_Bootstrap                  1 (default priority of eventManager)
editor_Plugins_MissingTargetTerminology_Bootstrap           -100
editor_Plugins_LockSegmentsBasedOnConfig_Bootstrap          -10000


To enable all Plugins configure this:
["editor_Plugins_LockSegmentsBasedOnConfig_Bootstrap","editor_Plugins_NoMissingTargetTerminology_Bootstrap","editor_Plugins_SegmentStatistics_Bootstrap","editor_Plugins_TermTagger_Bootstrap","editor_Plugins_Transit_Bootstrap","editor_Plugins_ManualStatusCheck_Bootstrap"]
