<?php

namespace myPHPnotes;
/**
*   Controlling cPanel
*   PHP module for the cPanel JSON API
*   @author Adnan Hussain Turki <adnan@myphpnotes.tk>
*   @copyright Copyright (c) 2017 myPHPnotes
*   
*/
class cPanel
{
    private $host;
    private $port;
    private $username;
    private $password;
    private $log;
    private $cFile;
    private $curlfile;
    private $emailArray;
    private $cpsess;
    private $homepage;
    private $exPage; 

    function __construct($username, $password, $host, $port = 2083, $log = false)
    {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->log = $log;
        $this->cFile = "cookies/cookie_".rand(99999, 9999999).".txt";
        $this->signIn();
    }
    // Makes an HTTP request
    private function Request($url,$params=array()){
        if($this->log){
            $curl_log = fopen($this->curlfile, 'a+');
        }
        if(!file_exists($this->cFile)){
            try{
                fopen($this->cFile, "w");
            }catch(Exception $ex){
                if(!file_exists($this->cFile)){
                    echo $ex.'Cookie file missing.'; exit;
                }
            }
        }else if(!is_writable($this->cFile)){
            echo 'Cookie file not writable.'; exit;
        }
        $ch = curl_init();
        $curlOpts = array(
            CURLOPT_URL             => $url,
            CURLOPT_USERAGENT       => 'Mozilla/5.0 (Windows NT 6.3; WOW64; rv:29.0) Gecko/20100101 Firefox/29.0',
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST  => false,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_COOKIEJAR       => realpath($this->cFile),
            CURLOPT_COOKIEFILE      => realpath($this->cFile),
            CURLOPT_FOLLOWLOCATION  => true,
            CURLOPT_HTTPHEADER      => array(
                "Host: ".$this->host,
                "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
                "Accept-Language: en-US,en;q=0.5",
                "Accept-Encoding: gzip, deflate",
                "Connection: keep-alive",
                "Content-Type: application/x-www-form-urlencoded")
        );
        if(!empty($params)){
            $curlOpts[CURLOPT_POST] = true;
            $curlOpts[CURLOPT_POSTFIELDS] = $params;
        }
        if($this->log){
            $curlOpts[CURLOPT_STDERR] = $curl_log;
            $curlOpts[CURLOPT_FAILONERROR] = false;
            $curlOpts[CURLOPT_VERBOSE] = true;
        }
        curl_setopt_array($ch,$curlOpts);
        $answer = curl_exec($ch);
        if (curl_error($ch)) {
            echo curl_error($ch); exit;
        }
        curl_close($ch);
        if($this->log){
            fclose($curl_log);
        }
        return (@gzdecode($answer)) ? gzdecode($answer) : $answer;
    }
    // Function to start a session at cPanel
    private function signIn() {
        $url = 'https://'.$this->host.":".$this->port."/login/?login_only=1";
        $url .= "&user=".$this->username."&pass=".urlencode($this->password);
        $reply = $this->Request($url);
        $reply = json_decode($reply, true);
        if(isset($reply['status']) && $reply['status'] == 1){
            $this->cpsess = $reply['security_token'];
            $this->homepage = 'https://'.$this->host.":".$this->port.$reply['redirect'];
            $this->exPage = 'https://'.$this->host.":".$this->port. "/{$this->cpsess}/execute/";
        }
        else {
            throw new Exception("Cannot connect to your cPanel server : Invalid Credentials", 1);            
        }
    }
    public function execute($api, $module, $function, array $parameters)
    {
        switch ($api) {
            case 'api2':
                return $this->api2($module, $function, $parameters);
                break;
            case 'uapi':
                return $this->uapi($module, $function, $parameters);
                break;
            default:
                throw new Exception("Invalid API type : api2 and uapi are accepted", 1);                
                break;
        }
    }
    // UAPI Handler
    public function uapi($module, $function,  array $parameters = [])
    {
        if (count($parameters) < 1) {
            $parameters = "";
        } else {
            $parameters = (http_build_query($parameters));
        }

        return  json_decode($this->Request($this->exPage . $module . "/" . $function . "?" . $parameters));

    }
    // API 2 Handler
    public function api2($module, $function, array $parameters = [])
    {
        if (count($parameters) < 1) {
            $parameters = "";
        } else {
            $parameters = (http_build_query($parameters));
        }
        $url = "https://".$this->host.":".$this->port.$this->cpsess."/json-api/cpanel".
        "?cpanel_jsonapi_version=2".
        "&cpanel_jsonapi_func={$function}".
        "&cpanel_jsonapi_module={$module}&". $parameters;
        return json_decode($this->Request($url,$parameters));
    }

}
