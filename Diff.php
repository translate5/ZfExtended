<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of translate5
 
 Copyright (c) 2013 - 2015 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file agpl3-license.txt 
 included in the packaging of this file.  Please review the following information 
 to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3.0 requirements will be met:
 http://www.gnu.org/licenses/agpl.html

 There is a plugin exception available for use with this release of translate5 for
 open source applications that are distributed under a license other than AGPL:
 Please see Open Source License Exception for Development of Plugins for translate5
 http://www.translate5.net/plugin-exception.txt or as plugin-exception.txt in the root
 folder of translate5.
  
 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execptions
			 http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt

END LICENSE AND COPYRIGHT
*/

/* * #@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 *
 */

/**
 * 
 */
class ZfExtended_Diff {

    /**
     * 
     * @param array $old
     * @param array $new
     * @return array diff-array in simplediff-syntax - simplediff itself is not used any more
     * example for return:
     * array(117) {
     * [0]=>
     *string(11) "<g id="12">"
     *[1]=>
     *string(89) "<mrk mtype="x-term-admittedTerm" mid="term_193_es-ES_1">Tamiz de cepillos rotativos</mrk>"
     *[2]=>
     *string(12) "</g id="12">"
     *[3]=>
     *array(2) {
     *  ["d"]=>
     *  array(1) {
     *    [0]=>
     *    string(1) "
     *
     *  }
     *  ["i"]=>
     *array(0) {
     *}
     *}
     *  [4]=>
     *string(1) "
     *"
     *[5]=>
     *string(11) "<g id="12">"
     * [6]=>
     * array(2) {
     * ["d"]=>
     *  array(0) {
     *   }
     *   ["i"]=>
     *   array(6) {
     *    [0]=>
     *     string(66) "<mrk mtype="x-term-admittedTerm" mid="term_195_es-ES_1">casa</mrk>"
     *     [1]=>
     *     string(12) "</g id="12">"
     *     [2]=>
     *     string(89) "<mrk mtype="x-term-admittedTerm" mid="term_193_es-ES_1">Tamiz de cepillos Rotativos</mrk>"
     *     [3]=>
     *      string(11) "<g id="12">"
     *     [4]=>
     *      string(2) " "
     *     [5]=>
     *     string(1) " "
     *   }
     * }
     */
    public function process(array $old, array $new) {
        $diff = new Horde_Text_Diff('auto', array(array_values($old), array_values($new)));
        
        $return = array();
        $diffs = $diff->getDiff();
       
        foreach ($diffs as $edit) {
            switch (get_class($edit)) {
            case 'Horde_Text_Diff_Op_Copy':
                $return = array_merge($return, $edit->orig);
                break;

            case 'Horde_Text_Diff_Op_Add':
                $return[] = array('i' => $edit->final,'d'=>array());
                break;

            case 'Horde_Text_Diff_Op_Delete':
                $return[] = array('i' => array(),'d'=>$edit->orig);
                break;

            case 'Horde_Text_Diff_Op_Change':
                $return[] = array('i' => $edit->final,'d'=>$edit->orig);
                break;
            }
        }
        return $return;
    }
}