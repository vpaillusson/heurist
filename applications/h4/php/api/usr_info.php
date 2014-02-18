<?php
/**
* Application interface. See hSystemMgr in hapi.js
*     
*       user/groups information/credentials
*       saved searches
* 
*/
require_once (dirname(__FILE__).'/../System.php');
require_once (dirname(__FILE__).'/../common/db_users.php');
require_once (dirname(__FILE__).'/../common/db_svs.php');

$response = array(); //"status"=>"fatal", "message"=>"OBLOM");

$system = new System();
if( ! $system->init(@$_REQUEST['db']) ){

    //get error and response
    $response = $system->getError();

}else{

    $mysqli = $system->get_mysqli();

    $action = @$_REQUEST['a']; //$system->getError();

    //no enough permission for guest
    if ( $system->get_user_id()<1 && 
            !( $action=='login' || $action=='reset_password' || $action=="svs_get" || $action=="usr_save" || $action=="usr_get" || $action=="sysinfo" ) ) {

         $response = $system->addError(HEURIST_REQUEST_DENIED, "Operation denied. Not enough rights");

    }else{

        $res = false;

        if ($action=="login") {

            //check request
            $username = @$_REQUEST['username'];
            $password = @$_REQUEST['password'];
            $session_type = @$_REQUEST['session_type'];

            if($system->login($username, $password, $session_type)){
                $res = $system->getCurrentUserAndSysInfo();
            }

        } else if ($action=="reset_password") {    

            if(user_ResetPassword($system, @$_REQUEST['username'])){
                $res = true;
            }
            
        } else if ($action=="logout") {

            if($system->logout()){
                $res = true;
            }

        } else if ($action=="sysinfo") {

            $res = $system->getCurrentUserAndSysInfo();

        } else if ($action == "save_prefs"){ //save preferences into session

            user_setPreferences($system->dbname_full(), $_REQUEST);
            $res = true;

        } else if ($action=="usr_save") {
            
            $res = user_Update($system, $_REQUEST);
            
        } else if ($action=="usr_get" && is_numeric(@$_REQUEST['UGrpID'])) {
            
//error_log("KUKU ".$_REQUEST['UGrpID']);            
            if($system->is_admin2($_REQUEST['UGrpID'])){
                $res = user_getById($system->get_mysqli(), $_REQUEST['UGrpID']); 
                if(is_array($res)){
                    $res['ugr_Password'] = '';   
                }
            }else{
                $system->addError(HEURIST_REQUEST_DENIED);    
            }
            
        } else if ($action=="groups") {

            $ugr_ID = @$_REQUEST['UGrpID']?$_REQUEST['UGrpID']:$system->get_user_id();

            $res = user_getWorkgroups($system->get_mysqli(), $ugr_ID, true);

        } else if ($action=="members" && @$_REQUEST['UGrpID']) {

            $res = user_getWorkgroupMemebers($system->get_mysqli(), @$_REQUEST['UGrpID']);

        } else if ($action=="svs_save"){

           $res = svsSave($system, $_REQUEST);

        } else if ($action=="svs_delete" && @$_REQUEST['ids']) {

            $res = svsDelete($system, $_REQUEST['ids'], @$_REQUEST['UGrpID']);

        } else if ($action=="svs_get" ) {

            $res = svsGetByUser($system, @$_REQUEST['UGrpID']);

        } else {

            $system->addError(HEURIST_INVALID_REQUEST);
        }


        if(is_bool($res) && !$res){
            $response = $system->getError();
        }else{
            $response = array("status"=>HEURIST_OK, "data"=> $res);
        }

    }
}

header('Content-type: text/javascript');
print json_encode($response);
?>
