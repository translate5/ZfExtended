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

class ZfExtended_Models_Entity_ExcelExport extends PHPExcel {
    
    /**
     * @var PHPExcel
     */
    private $PHPExcel = false;
    
    
    public function __construct() {
        $this->PHPExcel  = ZfExtended_Factory::get('PHPExcel');
    }
    
    public function simpleArrayToExcel ($data) {
        $tempSheet = $this->PHPExcel->setActiveSheetIndex(0);
        
        $rowCount = 1;
        foreach ($data as $row)
        {
            $colCount = 0;
            foreach ($row as $key => $value) {
                if ($rowCount == 1) {
                    $tempSheet->setCellValueByColumnAndRow($colCount, 1, $key);
                }
                $tempSheet->setCellValueByColumnAndRow($colCount, $rowCount+1, $value);
                $colCount ++;
            }
            $rowCount++;
        }
        
        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $this->PHPExcel->setActiveSheetIndex(0);
        
        $this->sendDownload();
        
    }
    
    public function excelDemo() {
        // Set document properties
        $this->PHPExcel->getProperties()->setCreator("Maarten Balliauw")
                              ->setLastModifiedBy("Maarten Balliauw")
                              ->setTitle("Office 2007 XLSX Test Document")
                              ->setSubject("Office 2007 XLSX Test Document")
                              ->setDescription("Test document for Office 2007 XLSX, generated using PHP classes.")
                              ->setKeywords("office 2007 openxml php")
                              ->setCategory("Test result file");
        
        
        // Add some data
        $this->PHPExcel->setActiveSheetIndex(0)
             ->setCellValue('A1', 'Hello')
             ->setCellValue('B2', 'world!')
             ->setCellValue('C1', 'Hello')
             ->setCellValue('D2', 'world!');
        
        // Miscellaneous glyphs, UTF-8
        $this->PHPExcel->setActiveSheetIndex(0)
             ->setCellValue('A4', 'Miscellaneous glyphs')
             ->setCellValue('A5', 'éàèùâêîôûëïüÿäöüç');
        
        // Rename worksheet
        $this->PHPExcel->getActiveSheet()->setTitle('Simple');
        
        
        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $this->PHPExcel->setActiveSheetIndex(0);
        
        $this->sendDownload();
    }
    
    private function sendDownload () {
        // Redirect output to a client’s web browser (Excel)
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="demo.xls"');
        header('Cache-Control: max-age=0');
        // If you're serving to IE 9, then the following may be needed
        header('Cache-Control: max-age=1');
        
        // If you're serving to IE over SSL, then the following may be needed
        header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
        header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header ('Pragma: public'); // HTTP/1.0
        
        $objWriter = PHPExcel_IOFactory::createWriter($this->PHPExcel, 'Excel2007');
        $objWriter->save('php://output');
        exit;
        
    }
}
