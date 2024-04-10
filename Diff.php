<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library

 Copyright (c) 2013 - 2021 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU LESSER GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file lgpl3-license.txt
 included in the packaging of this file.  Please review the following information
 to ensure the GNU LESSER GENERAL PUBLIC LICENSE version 3.0 requirements will be met:
https://www.gnu.org/licenses/lgpl-3.0.txt

 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU LESSER GENERAL PUBLIC LICENSE version 3
             https://www.gnu.org/licenses/lgpl-3.0.txt

END LICENSE AND COPYRIGHT
*/

/* * #@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 *
 */

class ZfExtended_Diff
{
    /**
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
    public function process(array $old, array $new)
    {
        $diff = new Horde_Text_Diff('auto', [array_values($old), array_values($new)]);

        $return = [];
        $diffs = $diff->getDiff();

        foreach ($diffs as $edit) {
            switch (get_class($edit)) {
                case 'Horde_Text_Diff_Op_Copy':
                    $return = array_merge($return, $edit->orig);

                    break;

                case 'Horde_Text_Diff_Op_Add':
                    $return[] = [
                        'i' => $edit->final,
                        'd' => [],
                    ];

                    break;

                case 'Horde_Text_Diff_Op_Delete':
                    $return[] = [
                        'i' => [],
                        'd' => $edit->orig,
                    ];

                    break;

                case 'Horde_Text_Diff_Op_Change':
                    $return[] = [
                        'i' => $edit->final,
                        'd' => $edit->orig,
                    ];

                    break;
            }
        }

        return $return;
    }
}
