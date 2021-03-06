<?php
namespace AliceSPA\Service;
use \AliceSPA\Helper\Config as configHelper;

class APIProtocol
{
    var $data = [
        'status' => 'SUCCESS',
        'errors' => [],
        'data' => null,
        'APIException' => null,
        'sessionID' => null
    ];

    var $isEnabled = true;
    var $isFlushed = false;
    private static $_instance;

    private function __construct(){
    }

    public function __clone(){
    }

    public static function getInstance(){
        if(!(self::$_instance instanceof self)){
            self::$_instance = new self;
        }
        return self::$_instance;
    }
    function flush($res){
        if($this->isFlushed){
            return $res;
        }
        $this->isFlushed = true;
        return $res->withJson($this->getResponse());
    }
    function setSuccess(){
        $this->data['status'] = 'SUCCESS';
    }

    function setFailure(){
        $this->data['status'] = 'FAILURE';
    }

    function isSuccess(){
        return $this->data['status'] === 'SUCCESS';
    }

    function setData($data){
        $this->data['data'] = $data;
    }

    function getData(){
        return $this->data['data'];
    }

    function getResponse(){
        return $this->data;
    }

    function setEnabled(){
        $this->isEnabled = true;
    }

    function setDisabled(){
        $this->isEnabled = false;
    }

    function isEnabled(){
        return $this->isEnabled;
    }
    function setSessionId($id){
        $this->data['sessionID'] = $id;
    }
    function pushError($err){
        $this->data['errors'][] = $err;
        $this->setFailure();
    }

    function setAPIException($e){
        if(configHelper::getCoreConfig()['showAPIExceptoin']){
            $edata = [];
            $edata['code'] = $e->getCode();
            $edata['message'] = $e->getMessage();
            $edata['file'] = $e->getFile();
            $edata['line'] = $e->getLine();
            $edata['trace'] = $e->getTrace();
            $this->data['APIException'] = $edata;
        }
    }
}

$container['apip'] = function(){
    return \AliceSPA\Service\APIProtocol::getInstance();
};