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