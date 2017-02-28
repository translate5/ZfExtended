<?php
/*
 START LICENSE AND COPYRIGHT

This file is part of ZfExtended library

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

require_once('fpdf181/fpdf.php');
require_once('FPDI-1.6.1/fpdi.php');

class ZfExtended_Models_Entity_PdfExport extends FPDF{

    /**
     * @var $m_pdfExport FPDI
     * */
    private $m_pdfExport;

    public function __construct(){
        // initiate FPDI
        $this->m_pdfExport= new FPDI();
    }
    
    
    public function output(){
        $this->m_pdfExport->Output();
    }
    
    // Colored table
    public function createTable(){
        $header = array('Country', 'Capital', 'Area (sq km)', 'Pop. (thousands)');
        
        // Colors, line width and bold font
        $this->SetFillColor(255,0,0);
        $this->SetTextColor(255);
        $this->SetDrawColor(128,0,0);
        $this->SetLineWidth(.3);
        $this->SetFont('','B');
        // Header
        $w = array(40, 35, 40, 45);
        for($i=0;$i<count($header);$i++)
            $this->Cell($w[$i],7,$header[$i],1,0,'C',true);
            $this->Ln();
            // Color and font restoration
            $this->SetFillColor(224,235,255);
            $this->SetTextColor(0);
            $this->SetFont('');
            // Data
            $fill = false;
            $this->Cell($w[0],6,'Makedonija','LR',0,'L',$fill);
            $this->Cell($w[1],6,'Skopje','LR',0,'L',$fill);
            $this->Cell($w[2],6,1001010101,'LR',0,'R',$fill);
            $this->Cell($w[3],6,2100000,'LR',0,'R',$fill);
            $this->Ln();
            $fill = !$fill;
            // Closing line
            $this->Cell(array_sum($w),0,'','T');
    }
    
    public function generateFile(){
        // initiate FPDI
        $pdf = new FPDI();
        // add a page
        $pdf->AddPage();
        // set the source file
        $pdf->setSourceFile('vendor_export_de.pdf');
        // import page 1
        $tplIdx = $pdf->importPage(1);
        // use the imported page and place it at position 10,10 with a width of 100 mm
        $pdf->useTemplate($tplIdx, 10, 10, 100);
        
        // now write some text above the imported page
        $pdf->SetFont('Helvetica');
        $pdf->SetTextColor(255, 0, 0);
        $pdf->SetXY(30, 30);
        $pdf->Write(0, 'This is just a simple text updated');
        
        $pdf->Output('F',"vendor_export_de_output.pdf");
    }
}