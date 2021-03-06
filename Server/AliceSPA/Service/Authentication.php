<?php
namespace AliceSPA\Service;
use \AliceSPA\Helper\Config as configHelper;
use \AliceSPA\Helper\Database as dbHelper;
use \AliceSPA\Helper\Utilities as utils;
use \AliceSPA\Service\Database as db;
use \AliceSPA\Exception\APIException as APIException;
class Authentication
{
    private $isLoggedIn = false;
    private $userInfo = null;
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

    public function isLoggedIn(){
        return $this->isLoggedIn;
    }

    public function getUserInfo(){
        if(!$this->isLoggedIn()){
            return false;
        }
        return $this->userInfo;
    }

    public function loginByUnionField($nameMap,$password){
        $db = db::getInstance();
        $nameMap = $this->filterUnionField($nameMap);
        $where = [
            'AND' => [
                'AND' => $nameMap,
                'password' => $password
            ]
        ];
        $user = $db->get('aspa_account',['id'],$where);
        if(!$user){
            throw new APIException(1);
            return false;
        }

        $token = utils::generateToken($user['id']);
        $db->update('aspa_account',['web_token' => $token,'web_token_create_time'=>utils::datetimePHP2Mysql(time())],['id'=>$user['id']]);
        $user = $this->authenticateByWebToken($user['id'],$token);

        return $user;
    }

    public function logout($id){
        $userInfo = $this->getUserInfo();
        if($userInfo !== false){
            $db->update('aspa_account',['web_token'=>null,'web_token_create_time'=>null],['id'=>$userInfo['id']]);
        }
    }

    public function isExistByUnionField($nameMap){
        $db = db::getInstance();
        $nameMap = $this->filterUnionField($nameMap);
        $fieldNames = configHelper::getCoreConfig()['authenticateFieldNames'];
        foreach($nameMap as $key => $value){ // !PERFORMANCE
            $where = utils::arrayMap($fieldNames,$value);
            if($db->has('aspa_account',['OR' => $where])){
                return true;
            }
        }
        return false;
    }

    public function registerByUnionField($nameMap,$password){
        $db = db::getInstance();

        $nameMap = $this->filterUnionField($nameMap);

        if($this->isExistByUnionField($nameMap)){
            throw new APIException(2);
            return false;
        }
        $data = $nameMap;
        $data['password'] = $password;
        $id = $db->insert('aspa_account',$data);
        if(intval($id) < configHelper::getCoreConfig()['autoincrementBeginValue']){
            throw new APIException(2);
            return false;
        }

        return true;
    }

    private function filterUnionField($nameMap){

        $fieldNames = configHelper::getCoreConfig()['authenticateFieldNames'];
        $nameMap = array_filter($nameMap,
            function($value,$key)use($fieldNames){ // remove null or empty string
                if(empty($value) || !in_array($key,$fieldNames)){
                    return false;
                }
                return true;
            },ARRAY_FILTER_USE_BOTH);
        return $nameMap;
    }

    public function authenticateByWebToken($userId,$webToken){
        $db = db::getInstance();

        $user = $db->get('aspa_account',
            '*',
            ['AND' =>
                ['id'=>$userId,
                'web_token'=>$webToken
                ]
            ]);

        if(!$user){
            throw new APIException(1);
            return false;
        }
        $web_token_create_time = $user['web_token_create_time'];
        if(empty($web_token_create_time)){
            return false;
        }
        if(time() - utils::datetimeMysql2PHP($web_token_create_time) >
            configHelper::getCoreConfig()['webTokenValidTime']){
            return false;
        }
        unset($user['password']);
        unset($user['web_token_create_time']);
        $this->isLoggedIn = true;
        $this->userInfo = $user;
        return $this->userInfo;
    }

    public function checkRoles($routeRoles){

        if(empty($routeRoles)){//Every one can access.
            return true;
        }

        $roles = null;
        if($this->isLoggedIn()){
            $db = db::getInstance();
            $roles = $db->select('aspa_role','role_names',['user_id'=>$this->userInfo['id']]);
            if(!empty($roles)){
                $roles = $roles[0];
                $roles = json_decode($roles,true);
                if(!in_array('visitor', $roles))
                {
                    $roles[] = 'visitor';
                }
                if(!in_array('user', $roles)){
                    $roles[] = 'user';
                }
            }
            else{
                $roles = ['visitor','user'];
            }
        }
        else{
            $roles = ['visitor'];
        }

        if(in_array('admin', $roles)){//Admin can access every where.
            return true;
        }

        $r = count(array_intersect($routeRoles, $roles)) >= 1;//If user has any one of roles required;

        return $r;
    }
}

$container['auth'] = function(){
    return \AliceSPA\Service\Authentication::getInstance();
};
