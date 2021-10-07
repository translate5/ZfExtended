<?php



class  ZfExtended_Zendoverwrites_Controller_Action_Helper_Redirector extends Zend_Controller_Action_Helper_Redirector{

    /**
     * Set redirect in response object
     *
     * @return void
     */
    protected function _redirect($url)
    {
        if ($this->getUseAbsoluteUri() && !preg_match('#^(https?|ftp)://#', $url)) {
            $host  = (isset($_SERVER['HTTP_HOST'])?$_SERVER['HTTP_HOST']:'');
            $proto = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=="off") ? 'https' : 'http';
            $port  = (isset($_SERVER['SERVER_PORT'])?$_SERVER['SERVER_PORT']:80);
            $uri   = $proto . '://' . $host;
            if ((('http' == $proto) && (80 != $port)) || (('https' == $proto) && (443 != $port))) {
                // do not append if HTTP_HOST already contains port
                if (strrchr($host, ':') === false) {
                    $uri .= ':' . $port;
                }
            }
            $url = $uri . '/' . ltrim($url, '/');
        }

        $url  = $this->addQueryStringAsHash($url);
        $this->_redirectUrl = $url;
        $this->getResponse()->setRedirect($url, $this->getCode());
    }


    protected function addQueryStringAsHash(string $url){
        if(isset($_SERVER['REDIRECT_QUERY_STRING'])){
            return $url.'#'.$_SERVER['REDIRECT_QUERY_STRING'];
        }
        return $url;
    }

}
