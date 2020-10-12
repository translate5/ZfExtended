<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
 Copyright (c) 2013 - 2017 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

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

class  ZfExtended_HttpClient extends Zend_Http_Client {
    /**
     * Prepare the request body (for POST and PUT requests)
     *
     * @return string
     * @throws Zend_Http_Client_Exception
     */
    protected function _prepareBody()
    {
        // According to RFC2616, a TRACE request should not have a body.
        if ($this->method == self::TRACE) {
            return '';
        }
        
        if (isset($this->raw_post_data) && is_resource($this->raw_post_data)) {
            return $this->raw_post_data;
        }
        // If mbstring overloads substr and strlen functions, we have to
        // override it's internal encoding
        if (function_exists('mb_internal_encoding') &&
            ((int) ini_get('mbstring.func_overload')) & 2) {
                
                $mbIntEnc = mb_internal_encoding();
                mb_internal_encoding('ASCII');
            }
            
            // If we have raw_post_data set, just use it as the body.
            if (isset($this->raw_post_data)) {
                $this->setHeaders(self::CONTENT_LENGTH, strlen($this->raw_post_data));
                if (isset($mbIntEnc)) {
                    mb_internal_encoding($mbIntEnc);
                }
                
                return $this->raw_post_data;
            }
            
            $body = '';
            
            // If we have files to upload, force enctype to multipart/form-data
            if (count ($this->files) > 0) {
                $this->setEncType(self::ENC_FORMDATA);
            }
            
            // If we have POST parameters or files, encode and add them to the body
            if (count($this->paramsPost) > 0 || count($this->files) > 0) {
                switch($this->enctype) {
                    case self::ENC_FORMDATA:
                        // Encode body as multipart/form-data
                        $boundary = '---ZENDHTTPCLIENT-' . md5(microtime());
                        $this->setHeaders(self::CONTENT_TYPE, self::ENC_FORMDATA . "; boundary={$boundary}");
                        
                        // Encode all files and POST vars in the order they were given
                        foreach ($this->body_field_order as $fieldName=>$fieldType) {
                            switch ($fieldType) {
                                case self::VTYPE_FILE:
                                    foreach ($this->files as $file) {
                                        if ($file['formname']===$fieldName) {
                                            $fhead = array(self::CONTENT_TYPE => $file['ctype']);
                                            $body .= self::encodeFormData($boundary, $file['formname'], $file['data'], $file['filename'], $fhead);
                                        }
                                    }
                                    break;
                                case self::VTYPE_SCALAR:
                                    if (isset($this->paramsPost[$fieldName])) {
                                        if (is_array($this->paramsPost[$fieldName])) {
                                            $flattened = self::_flattenParametersArray($this->paramsPost[$fieldName], $fieldName);
                                            foreach ($flattened as $pp) {
                                                $body .= self::encodeFormData($boundary, $pp[0], $pp[1]);
                                            }
                                        } else {
                                            $body .= self::encodeFormData($boundary, $fieldName, $this->paramsPost[$fieldName]);
                                        }
                                    }
                                    break;
                            }
                        }
                        
                        $body .= "--{$boundary}--\r\n";
                        break;
                        
                    case self::ENC_URLENCODED:
                        // Encode body as application/x-www-form-urlencoded
                        $this->setHeaders(self::CONTENT_TYPE, self::ENC_URLENCODED);
                        $body = http_build_query($this->paramsPost, '', '&');
                        $body = preg_replace('/\%5B\d+\%5D/', '', $body);
                        break;
                        
                    default:
                        if (isset($mbIntEnc)) {
                            mb_internal_encoding($mbIntEnc);
                        }
                        
                        /** @see Zend_Http_Client_Exception */
                        require_once 'Zend/Http/Client/Exception.php';
                        throw new Zend_Http_Client_Exception("Cannot handle content type '{$this->enctype}' automatically." .
                        " Please use Zend_Http_Client::setRawData to send this kind of content.");
                        break;
                }
            }
            
            // Set the Content-Length if we have a body or if request is POST/PUT
            if ($body || $this->method == self::POST || $this->method == self::PUT) {
                $this->setHeaders(self::CONTENT_LENGTH, strlen($body));
            }
            
            if (isset($mbIntEnc)) {
                mb_internal_encoding($mbIntEnc);
            }
            
            return $body;
    }
    
}
