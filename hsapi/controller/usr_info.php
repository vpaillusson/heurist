<?php

    /**
    * Application interface. See hSystemMgr in hapi.js
    *    user/groups information/credentials
    *    saved searches
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2019 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

    require_once (dirname(__FILE__).'/../System.php');
    require_once (dirname(__FILE__).'/../dbaccess/db_users.php');
    require_once (dirname(__FILE__).'/../dbaccess/db_svs.php');
    require_once (dirname(__FILE__).'/../utilities/utils_file.php');

    $response = array(); //"status"=>"fatal", "message"=>"OBLOM");
    $res = false;

    $action = @$_REQUEST['a']; //$system->getError();
     
    $system = new System();
        
    if($action=='verify_credentials'){ //just check only if logged in (db connection not required)
        
        $res = $system->verify_credentials(@$_REQUEST['db']);
        
        if( $res>0 ){ //if logged id verify that session info (especially groups) is up to date
            //if exists file with userid it means need to reload system info
            $reload_user_from_db = (@$_SESSION[$system->dbname_full()]['need_refresh']==1);
            $const_toinit = true;
            if(!$reload_user_from_db){
                $const_toinit = false;
                $system->initPathConstants(@$_REQUEST['db']);  
                $fname = HEURIST_FILESTORE_DIR.$res;
                $reload_user_from_db = file_exists($fname);
            }
                
            if($reload_user_from_db){
                $system->init(@$_REQUEST['db'], false, $const_toinit); //session and constant are defined already
                $res = $system->getCurrentUserAndSysInfo();
            }else{
                $res = true;
            }
        }
    
    }else if($action=='usr_log'){
        
        $system->initPathConstants(@$_REQUEST['db']);
        $system->user_LogActivity(@$_REQUEST['activity'], @$_REQUEST['suplementary']);
        $res = true;
        
        if(@$_REQUEST['activity']=='impEmails'){
            $msg = 'Click on "Harvest EMail" in menu. DATABASE: '.@$_REQUEST['db'];
            $rv = sendEmail(HEURIST_MAIL_TO_ADMIN, $msg, $msg, null);
        }
        
        
    } else if (false && $action == "save_prefs"){ //save preferences into session (without db)

        if($system->verify_credentials(@$_REQUEST['db'])>0){
            user_setPreferences($system, $_REQUEST);
            $res = true;
        }

    } else if ($action == "logout"){ //save preferences into session
    
        if($system->set_dbname_full(@$_REQUEST['db'])){
            
                $system->initPathConstants(@$_REQUEST['db']);
                $system->user_LogActivity('Logout');

                if($system->logout()){
                    $res = true;
                }
        }
        
    }else if( !$system->init( @$_REQUEST['db'] ) ){ 
        
//        error_log('FAILD INIT SYSTEM');        
        
    }else{
        
        $mysqli = $system->get_mysqli();

        //allowed actions for guest
        $quest_allowed = array('login','reset_password','svs_savetree','svs_gettree','usr_save','svs_get');
        
        if ($action=="sysinfo") { //it call once on hapi.init on client side - so it always need to reload sysinfo

            $res = $system->getCurrentUserAndSysInfo();
            
        }else if ($action == "save_prefs"){
           
            if($system->verify_credentials(@$_REQUEST['db'])>0){
                user_setPreferences($system, $_REQUEST);
                $res = true;
            }

        }else if ($action=="sysimages") { //get list of system images
        
              $lib_path = @$_REQUEST['folders'];
              if(!is_array($lib_path) || count($lib_path)<1){
                  $lib_path = array('admin/setup/iconLibrary/64px/');
              }
        
              $res = folderContent($lib_path, array('png'));
              
        }else if ($action=="folders") { //get list of system images

              $folders = $system->getArrayOfSystemFolders();
               
              $op = @$_REQUEST['operation'];
              if(!$op || $op=='list'){
                
                $res = folderTree(@$_REQUEST['root_dir'], array('systemFolders'=>$folders,'format'=>'fancy') ); //see utils_file
                $res = $res['children'];
                //$res = folderTreeToFancyTree($res, 0, $folders);
              }else{
                  
                  $res = false;
                  
                  $dir_name = @$_REQUEST['name'];
                  
                  $folder_name = HEURIST_FILESTORE_DIR.$dir_name;
                  
                  if($folders[strtolower($dir_name)]){
                      $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Cannot modify system folder');
                  }else
                  if($op=='rename'){
                      
                      $new_name = @$_REQUEST['newname'];
                      if($folders[strtolower($new_name)]){
                          $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Name "'.$new_name.'" is reserved for system folder');
                      }else if(file_exists(HEURIST_FILESTORE_DIR.$new_name)){
                          $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Folder with name "'.$new_name.'" already exists');
                      }else if(!file_exists($folder_name)){
                          $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Folder with name "'.$dir_name.'" does not exist');
                      }else{
                          $res = rename($folder_name, HEURIST_FILESTORE_DIR.$new_name);    
                          if(!$res){
                              $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Can not rename folder "'
                                    .$dir_name.'" to name "'.$new_name.'"');
                          }
                      }
     
                  }else if($op=='delete'){
                      //if (is_dir($dir))
                      
                      if(!file_exists($folder_name)){
                          $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Folder with name "'.$dir_name.'" does not exist');                          }else if (count(scandir($folder_name))>2){
                          $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Non empty folder "'.$dir_name.'" can not be removed');                      }else{
                          $res = folderDelete2($folder_name, true);        
                          if(!$res){
                              $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Folder "'.$dir_name.'" can not be removed');
                          }
                      }
                      
                  }else if($op=='create'){
                      if(file_exists($folder_name)){
                          $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Folder with such name already exists');
                      }else{
                         $res = folderCreate($folder_name, true);
                         if(!$res){
                              $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Folder "'.$dir_name.'" can not be created');
                         }
                      }
                  }
              }
        }else        
        if ( $system->get_user_id()<1 &&  !in_array($action,$quest_allowed)) {

            $response = $system->addError(HEURIST_REQUEST_DENIED);

        }else{

            $res = false;

            if ($action=="login") {

                //check request
                $username = @$_REQUEST['username'];
                $password = @$_REQUEST['password'];
                $session_type = @$_REQUEST['session_type'];

                if($system->login($username, $password, $session_type)){
                    $res = $system->getCurrentUserAndSysInfo( true ); //including reccount and dashboard entries
                    
                    checkDatabaseFunctions($mysqli);
                }

            } else if ($action=="reset_password") {

                $system->user_LogActivity('ResetPassword');
                
                if(user_ResetPassword($system, @$_REQUEST['username'])){
                    $res = true;
                }

            } else  if ($action=="action_password") { //special passwords for some admin actions - defined in configIni.php
            
                    $action = @$_REQUEST['action'];
                    $password = @$_REQUEST['password'];
                    if($action && $password){
                        $varname = 'passwordFor'.$action;
                        $res = (@$$varname==$password)?'ok':'wrong';
                    }

            } else if ($action=="sys_info_count") { 
                
                $res = $system->getTotalRecordsAndDashboard();
                        
            } else if ($action=="usr_save") {

                $res = user_Update($system, $_REQUEST);

            } else if ($action=="usr_get" && is_numeric(@$_REQUEST['UGrpID'])) {

                $ugrID = $_REQUEST['UGrpID'];

                if($system->has_access($ugrID)){  //allowed for itself only
                    $res = user_getById($system->get_mysqli(), $ugrID);
                    if(is_array($res)){
                        $res['ugr_Password'] = '';
                    }
                }else{
                    $system->addError(HEURIST_REQUEST_DENIED);
                }

            } else if ($action=="usr_names" && @$_REQUEST['UGrpID']) {
                
                $res = user_getNamesByIds($system, $_REQUEST['UGrpID']);
                
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

                if(@$_REQUEST['svsIDs']){
                    $res = svsGetByIds($system, $_REQUEST['svsIDs'], @$_REQUEST['UGrpID']);
                }else{
                    $res = svsGetByUser($system, @$_REQUEST['UGrpID']);
                }

            } else if ($action=="svs_savetree" ) { //save saved searches tree status

                $res = svsSaveTreeData($system, @$_REQUEST['data']);

            } else if ($action=="svs_gettree" ) { //save saved searches tree status

                $res = svsGetTreeData($system, @$_REQUEST['UGrpID']);

                
            }else if($action == 'get_url_content_type'){
                
                $url = @$_REQUEST['url'];
                
                $res = recognizeMimeTypeFromURL($mysqli, $url);
                
            } else {

                $system->addError(HEURIST_INVALID_REQUEST);
            }


        }
        
    }
    
    if(is_bool($res) && !$res){
        $response = $system->getError();
    }else{
        $response = array("status"=>HEURIST_OK, "data"=> $res);
    }
        
header('Content-type: application/json;charset=UTF-8');
print json_encode($response);
?>