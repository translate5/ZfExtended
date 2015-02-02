<?php
 /*
   START LICENSE AND COPYRIGHT

  This file is part of the ZfExtended library and build on Zend Framework

  Copyright (c) 2013 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

  Contact:  http://www.MittagQI.com/  /  service (ÄTT) MittagQI.com

  This file is multi-licensed under the EPL, LGPL and GPL licenses. 

  It may be used under the terms of the Eclipse Public License - v 1.0
  as published by the Eclipse Foundation and appearing in the file eclipse-license.txt 
  included in the packaging of this file.  Please review the following information 
  to ensure the Eclipse Public License - v 1.0 requirements will be met:
  http://www.eclipse.org/legal/epl-v10.html.

  Also it may be used under the terms of the GNU LESSER GENERAL PUBLIC LICENSE version 3
  or the GNU GENERAL PUBLIC LICENSE version 3 as published by the 
  Free Software Foundation, Inc. and appearing in the files lgpl-license.txt and gpl3-license.txt
  included in the packaging of this file.  Please review the following information 
  to ensure the GNU LESSER GENERAL PUBLIC LICENSE version 3 requirements or the
  GNU GENERAL PUBLIC LICENSE version 3 requirements will be met:
  http://www.gnu.org/licenses/lgpl.html   /  http://www.gnu.org/licenses/gpl.html
  
  @copyright  Marc Mittag, MittagQI - Quality Informatics
  @author     MittagQI - Quality Informatics
  @license    Multi-licensed under Eclipse Public License - v 1.0 http://www.eclipse.org/legal/epl-v10.html, GNU LESSER GENERAL PUBLIC LICENSE version 3 http://www.gnu.org/licenses/lgpl.html and GNU GENERAL PUBLIC LICENSE version 3 http://www.gnu.org/licenses/gpl.html

  END LICENSE AND COPYRIGHT 
 */

require_once('ZfExtended/ThirdParty/PHPExcel/PHPExcel.php');

class ZfExtended_Models_Entity_ExcelExport {
    
    /**
     * @var PHPExcel
     */
    private $PHPExcel = false;
    
    /**
     * Container to hold document properties like filename etc. (properties->name->value)
     * @var stdClass
     */
    private $properties = false;
    
    /**
     * Container to hold lablenames (lablenames->label->translation)
     * @var stdClass
     */
    private $labels = false;
    
    /**
     * Container to hold callback functions for manipulating field-content (callbacks->label->$closureFunction)
     * @var stdClass
     */
    private $callbacks = array();
    
    
    
    public function __construct() {
        $this->PHPExcel  = ZfExtended_Factory::get('PHPExcel');
        
        $this->properties = new stdClass();
        $this->properties->filename = 'ERP-Export';
        
        $this->labels = new stdClass();
        $this->callbacks = new stdClass();
    }
    
    
    public function simpleArrayToExcel ($data) {
        $tempSheet = $this->PHPExcel->setActiveSheetIndex(0);
        
        $rowCount = 1;
        foreach ($data as $row)
        {
            $colCount = 0;
            foreach ($row as $key => $value) {
                if ($rowCount == 1) {
                    $tempSheet->setCellValueByColumnAndRow($colCount, 1, $this->getLabel($key));
                }
                $tempSheet->setCellValueByColumnAndRow($colCount, $rowCount+1, $this->getCallback($key, $value));
                $colCount ++;
            }
            $rowCount++;
        }
        
        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $this->PHPExcel->setActiveSheetIndex(0);
        
        $this->sendDownload();
        
    }
    
    
    /**
     * Set some properties used to generate the excel
     * @param string $name
     * @param mixed $value
     */
    public function setProperty(string $name, $value) {
        $this->properties->$name = $value;
    }
    
    /**
     * Get property with name $name used to generate the excel
     * @param string $name
     * @return mixed
     */
    public function getProperty(string $name) {
        if (property_exists($this->properties, $name)) {
            return $this->properties->$name;
        }
        return false;
    }
    
    
    /**
     * Set label used in the excelsheet to transform key of data[key] into a speaking label (or used for translation)
     * @param string $name
     * @param string $label
     */
    public function setLabel(string $name, $label) {
        $this->labels->$name = $label;
    }
    
    /**
     * Get label of $name
     * @param string $name
     * @return string
     */
    public function getLabel(string $name) {
        if (property_exists($this->labels, $name)) {
            return $this->labels->$name;
        }
        return $name;
    }
    
    
    /**
     * Set callback-function used in the excelsheet to manipulate the $value for a certain $key of data[$key] = $value
     * @param string $name
     * @param $function as closure function variable
     */
    public function setCallback(string $name, $function) {
        $this->callbacks->$name = $function;
    }
    
    /**
     * Get manipulated value of field $name
     * @param string $name
     * @param mixed $value
     * @return mixed
     */
    public function getCallback(string $name, $value) {
        if (property_exists($this->callbacks, $name)) {
            return call_user_func($this->callbacks->$name, $value);
        }
        return $value;
    }
    
    
    /**
     * Redirect output to a client's web browser (Excel)
     */
    private function sendDownload () {
        $fileName = $this->getProperty('filename').date('-Y-d-m');
        
        // XLS Excel5 output
        //$objWriter = PHPExcel_IOFactory::createWriter($this->PHPExcel, 'Excel5');
        //header('Content-Type: application/vnd.ms-excel');
        //header('Content-Disposition: attachment;filename="'.$fileName.'.xls"');
        
        // XLSX Excel2007 output
        $objWriter = PHPExcel_IOFactory::createWriter($this->PHPExcel, 'Excel2007');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.$fileName.'.xlsx"');
        
        header('Cache-Control: max-age=0');
        // If you're serving to IE 9, then the following may be needed
        header('Cache-Control: max-age=1');
        
        // If you're serving to IE over SSL, then the following may be needed
        header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
        header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header ('Pragma: public'); // HTTP/1.0
        
        $objWriter->save('php://output');
        exit;
    }
}
