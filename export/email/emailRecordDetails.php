<?php

	/**
 * emailRecordDetailsphp
 *
 * Accept POST from
 *
 * 2011/06/07
 * @author: Artem Osmakov
 *
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 *
 * @todo - concer any field type "file" as attachment - currently only #221
 * @todo - remove files from database and folder after sending
 **/

	define('SAVE_URI', 'disabled');

	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
	require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');

	require_once(dirname(__FILE__).'/../../external/php/geekMail-1.0.php');

	//send an email
	$geekMail = new geekMail();
	$geekMail->setMailType('html');
	$geekMail->to('prime.heurist@gmail.com');

  $ids = "";

	if($_POST["rectype"] == RT_BUG_REPORT){

	$bug_title = $_POST["type:".DT_BUG_REPORT_NAME];

	$geekMail->from("bugs@heuristscholar.org", "Bug reporter"); //'noreply@heuristscholar.org', 'Bug Report');
	$geekMail->subject('Bug Report: '.$bug_title[0]);

	$key_abs = "type:".DT_BUG_REPORT_ABSTRACT;
	$ext_desc = $_POST[$key_abs];
	if(!is_array($ext_desc)) {
		$ext_desc = array();
		if($_POST[$key_abs]!="" && $_POST[$key_abs]!=null){
			array_push($ext_desc, $_POST[$key_abs]);
		}
	}
  	//add current system information into message
	array_push($ext_desc, "Browser information: ".$_SERVER['HTTP_USER_AGENT']);
  	//add current heurist information into message
  	//add current heurist information into message
	array_push($ext_desc, "Heurist codebase: ".HEURIST_BASE_URL);
	array_push($ext_desc, "Heurist version: ".HEURIST_DBVERSION);
	array_push($ext_desc, "Heurist database: ". DATABASE);
	array_push($ext_desc, "Heurist user: ".get_user_name());

	$_POST[$key_abs] = $ext_desc;

  }else{

	$geekMail->from("bugs@heuristscholar.org", "Record sender"); //'noreply@heuristscholar.org', 'Bug Report');
	$geekMail->subject('Record from '.DATABASE);

  }

  // ATTACHMENTS - find file fieldtype in POST
  $key_file = "type:".DT_BUG_REPORT_FILE;
  foreach ($_POST as $key => $value)
  {
    if (is_array($value) && $key == $key_file ) {
      foreach ($value as $subvalue) {
      	  if($subvalue){
	  	  		if($ids==""){
	  				$ids = "(";
				}else{
	  				$ids = $ids.",";
				}
	  			$ids = $ids.$subvalue;
		  }
      }
    }
  }
  if($ids!=""){
  	  	mysql_connection_db_overwrite(DATABASE);

		$query = "select ulf_ID, ulf_OrigFileName, ulf_Added, ulf_MimeExt, ulf_FileSizeKB from recUploadedFiles where ulf_ID in ".$ids.")";

		//DEBUG error_log(">>>> ".$query);

		$files_arr = array();

		$res = mysql_query($query);
  		while ($row = mysql_fetch_row($res)) {
			//DEBUG error_log(">>>> ".HEURIST_UPLOAD_PATH.$row[0]);
			$geekMail->attach(HEURIST_UPLOAD_PATH.$row[0]);
			array_push($files_arr, $row);
		}

		$_POST[$key_file] = $files_arr;

		//@todo delete from database and remove files (after send an email)

  }

	//files already on server side in database - we don't need to analize
	/*if ($_FILES) {
	foreach ($_FILES as $eltName => $upload) {
		// check that $elt_name is a sane element name
		if (! preg_match('/^type:\\d+$/', $eltName)  ||  ! $_FILES[$eltName]  ||  count($_FILES[$eltName]) == 0) continue;

		if (! $upload["size"]) continue;
		foreach ($upload["size"] as $eltID => $size) {
			if ($size <= 0) continue;

	error_log(">>>>>>".$tmp_name);

			$geekMail->attach($tmp_name);

			if (!$_POST[$eltName]) $_POST[$eltName] = $upload;
		}
	}
	}*/

  /*
  $message = '';
  foreach ($_POST as $key => $value)
  {
    if (is_array($value)) {
      $message .= "$key:\n";
      foreach ($value as $subkey => $subvalue)      {
     	$message .= "\t$subkey: $subvalue\n";
      }
    }
    else
    {
      $message .= "$key: $value\n";
    }
  }
  */

  // Converts all record and type codes into Concept
  $arr = array();
	if($_POST["rectype"] == RT_BUG_REPORT){
  		//bug reporting already codes in global
  		$arr = $_POST;
  }else{

  		foreach ($_POST as $key => $value)
		{
			$pos = strpos($key, "type:");
			//DEBUG error_log(">>>> ".(is_numeric($pos) && $pos == 0)."    ".$pos);

		    if (is_numeric($pos) && $pos == 0)
		    {
    			//@todo we have to convert the content of fields as well -
			    // since it may contain terms and references to other rectypes !!!1
    				$typeid = substr($key, 5); //, $top-5);
				//DEBUG error_log(">>>> ".strpos($key, "type:")."  dettype=".$typeid);
					$newkey = getDetailTypeConceptID($typeid);
					if($newkey){
			    		$arr["type:".$newkey] = $value;
					}else{
						print '({"error":"Can\'t find the global concept for fieldtype #"'.$typeid.'"})';
						exit();
					}
			}else{
			    	$arr[$key] = $value;
			}
		}//for

		//DEBUG error_log(">>>> rectype=".$_POST["rectype"]);
		//DEBUG error_log(">>>>".getRecTypeConceptID($_POST["rectype"]));

		$newrectype = getRecTypeConceptID($_POST["rectype"]);
		if($newrectype){
  			$arr["rectype"] = $newrectype;
		}else{
			print '({"error":"Can\'t find the global concept for rectype #"'.$_POST["rectype"].'"})';
			exit();
		}

  }

  // converts _POST array into string
  //$message = json_format($_POST);
  $message =  json_encode($arr);

	// DEBUG error_log(">>>> ".$message);
 /**/
	$geekMail->message($message);

	if (!$geekMail->send())
	{
		$errors = $geekMail->getDebugger();
  		print_r($errors);
	}else{
		print '({"result":"ok"})';
	}

	//print '({"result":"ok"})';
?>
