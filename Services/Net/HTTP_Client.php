<?php

namespace MauticPlugin\MauticCorebosBundle\Services;

global $coreBOS_Basedir;
require_once $coreBOS_Basedir.'/Net/curl_http_client.php';

class cbHTTP_Client extends Curl_HTTP_Client
{
    public $_serviceurl = '';

    public function __construct($url)
    {
        if (!function_exists('curl_exec')) {
            exit('cbHTTP_Client: Curl extension not enabled!');
        }
        parent::__construct();
        $this->_serviceurl = $url;
        $useragent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)';
        $this->set_user_agent($useragent);

        // Escape SSL certificate hostname verification
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 0);
    }

    public function doPost($postdata = false, $decodeResponseJSON = false, $timeout = 20)
    {
        if (false === $postdata) {
            $postdata = [];
        }
        $resdata = $this->send_post_data($this->_serviceurl, $postdata, null, $timeout);
        if ($resdata && $decodeResponseJSON) {
            $resdata = json_decode($resdata, true);
        }

        return $resdata;
    }

    public function doGet($getdata = false, $decodeResponseJSON = false, $timeout = 20)
    {
        if (false === $getdata) {
            $getdata = [];
        }
        $queryString = '';
        foreach ($getdata as $key => $value) {
            $queryString .= '&'.urlencode($key).'='.urlencode($value);
        }
        $resdata = $this->fetch_url("$this->_serviceurl?$queryString", null, $timeout);
        if ($resdata && $decodeResponseJSON) {
            $resdata = json_decode($resdata, true);
        }

        return $resdata;
    }
}
