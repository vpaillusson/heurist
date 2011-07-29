<?php

	// Edit the Heurist database identification record in sysIdentification table
	// Ian Johnson 11 Jan 2010, rev. July 2011
	// Copyright (C) 2010 University of Sydney, Digital Innovation Unit
	// Coded with NuSphere PHP-Ed

    // TO DO: Replace this function with a form based on our Javascript libraries, remove Nusphere dependancy
    
	require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
	require_once(dirname(__FILE__)."/../../external/nusphere/config.inc.php");
	require_once(dirname(__FILE__)."/../../external/nusphere/db_utils.inc");
	require_once(dirname(__FILE__)."/../../external/nusphere/db_". $config['db'] . ".inc");

	if (! is_logged_in()) {
		header("Location: " . HEURIST_URL_BASE . "common/connect/login.php?db=".HEURIST_DBNAME);
		return;
	}
	if (! is_admin()) {
		 print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body><div class=wrap><div id=errorMsg><span>You do not have sufficient privileges to access this page</span><p><a href=".HEURIST_URL_BASE."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME." target='_top'>Log out</a></p></div></div></body></html>";
		return;
	}

	$config['encoding'] = 'utf-8';
	$config['rows_per_page'] = 20;
	$config['pager_items'] = 10;

	define('INP_MODE', 'mode');
	define('INP_START', 'start');


	define('ERR_INVALID_REQUEST', '<html><body>Invalid request.
		Click <a href="?mode=s">here</a> to return to main page.</body></html>');
	define('ERR_NO_KEY', '<html><body>Could not proceed. This form requires a key field that will uniquely identify records in the table</body></html>');

	define('MSG_UPDATED', "<h3>\ndone ...</h3><p>\n\nHeurist System Identification Record has been updated successfully.
		Click <a href=\"?mode=s&amp;start=%d\">here</a> to return to Heurist home page.</p>");

	define('MSG_INSERTED', 'Record has been added successfully.
		Click <a href="?mode=s&amp;start=-1">here</a> to return to main page.');
	define('MSG_DELETED', "Record has been deleted successfully.
		Click <a href=\"?mode=s&amp;start=%d\">here</a> to return to main page.");

	$table = 'sysIdentification';
	$scheme = '';
	$fielddef = array(

	// These fields are not editable by adminstrator, updated automatically if at all, FLD_INPUT => False
	'f0' => array(FLD_ID => true, FLD_VISIBLE => true, FLD_DISPLAY => 'sys_ID', FLD_DISPLAY_SZ => 7,
	FLD_INPUT => False, FLD_INPUT_TYPE => 'text',
	FLD_INPUT_SZ => 7, FLD_INPUT_MAXLEN => 5, FLD_INPUT_DFLT => '',
	FLD_INPUT_NOTEMPTY => true, FLD_INPUT_VALIDATION => 'Numeric',
	FLD_DATABASE => 'sys_ID'),
    
	'f1' => array(FLD_ID => false, FLD_VISIBLE => true, FLD_DISPLAY => 'Database Registration ID (0=unreg.)', FLD_DISPLAY_SZ => 7,
	FLD_INPUT => False, FLD_INPUT_TYPE => 'text',
	FLD_INPUT_SZ => 7, FLD_INPUT_MAXLEN => 10, FLD_INPUT_DFLT => '',
	FLD_INPUT_NOTEMPTY => true, FLD_INPUT_VALIDATION => 'Numeric',
	FLD_DATABASE => 'sys_dbRegisteredID'),
    
	'f2' => array(FLD_ID => false, FLD_VISIBLE => true, FLD_DISPLAY => 'Database Version', FLD_DISPLAY_SZ => 7,
	FLD_INPUT => False, FLD_INPUT_TYPE => 'text',
	FLD_INPUT_SZ => 7, FLD_INPUT_MAXLEN => 5, FLD_INPUT_DFLT => '',
	FLD_INPUT_NOTEMPTY => true, FLD_INPUT_VALIDATION => 'Numeric',
	FLD_DATABASE => 'sys_dbVersion'),
    
	'f3' => array(FLD_ID => false, FLD_VISIBLE => true, FLD_DISPLAY => 'Database Subversion', FLD_DISPLAY_SZ => 7,
	FLD_INPUT => False, FLD_INPUT_TYPE => 'text',
	FLD_INPUT_SZ => 7, FLD_INPUT_MAXLEN => 5, FLD_INPUT_DFLT => '',
	FLD_INPUT_NOTEMPTY => true, FLD_INPUT_VALIDATION => 'Numeric',
	FLD_DATABASE => 'sys_dbSubVersion'),
    
	'f4' => array(FLD_ID => false, FLD_VISIBLE => true, FLD_DISPLAY => 'Database Sub-SubVersion', FLD_DISPLAY_SZ => 7,
	FLD_INPUT => False, FLD_INPUT_TYPE => 'text',
	FLD_INPUT_SZ => 7, FLD_INPUT_MAXLEN => 5, FLD_INPUT_DFLT => '',
	FLD_INPUT_NOTEMPTY => true, FLD_INPUT_VALIDATION => 'Numeric',
	FLD_DATABASE => 'sys_dbSubSubVersion'),

	// These fields are editable by adminstrator, FLD_INPUT => True
    'f5' => array(FLD_ID => false, FLD_VISIBLE => true, FLD_DISPLAY => 'Display name (more informative than DB name at top of page)', FLD_DISPLAY_SZ => 50,
    FLD_INPUT => true, FLD_INPUT_TYPE => 'text',
    FLD_INPUT_SZ => 30, FLD_INPUT_MAXLEN => 193, FLD_INPUT_DFLT => '',
    FLD_INPUT_NOTEMPTY => true, FLD_INPUT_VALIDATION => '',
    FLD_DATABASE => 'sys_dbName'),
    
    'f6' => array(FLD_ID => false, FLD_VISIBLE => true, FLD_DISPLAY => 'Name/insitution of owner (or URL to owner information)', FLD_DISPLAY_SZ => 100,
    FLD_INPUT => true, FLD_INPUT_TYPE => 'text',
    FLD_INPUT_SZ => 50, FLD_INPUT_MAXLEN => 751, FLD_INPUT_DFLT => '',
    FLD_INPUT_NOTEMPTY => true, FLD_INPUT_VALIDATION => '',
    FLD_DATABASE => 'sys_dbOwner'),
    
    'f7' => array(FLD_ID => false, FLD_VISIBLE => true, FLD_DISPLAY => 'Database rights statement (or URL to rights statement)', FLD_DISPLAY_SZ => 100,
    FLD_INPUT => true, FLD_INPUT_TYPE => 'text',
    FLD_INPUT_SZ => 80, FLD_INPUT_MAXLEN => 1000, FLD_INPUT_DFLT => 'Creative Commons Share Alike',
    FLD_INPUT_NOTEMPTY => true, FLD_INPUT_VALIDATION => '',
    FLD_DATABASE => 'sys_dbRights'),
    
    'f8' => array(FLD_ID => false, FLD_VISIBLE => true, FLD_DISPLAY => 'Description of the database/content up to 1Kb (may reference a URL)', FLD_DISPLAY_SZ => 100,
    FLD_INPUT => true, FLD_INPUT_TYPE => 'text',
    FLD_INPUT_SZ => 100, FLD_INPUT_MAXLEN => 1000, FLD_INPUT_DFLT => 'Please enter a description here ...',
    FLD_INPUT_NOTEMPTY => true, FLD_INPUT_VALIDATION => '',
    FLD_DATABASE => 'sys_dbDescription'),
    
    'f9' => array(FLD_ID => false, FLD_VISIBLE => true, FLD_DISPLAY => 'ID of Group owning database (>0, default 1)', FLD_DISPLAY_SZ => 7,
    FLD_INPUT => true, FLD_INPUT_TYPE => 'text',
    FLD_INPUT_SZ => 3, FLD_INPUT_MAXLEN => 10, FLD_INPUT_DFLT => '1',
    FLD_INPUT_NOTEMPTY => true, FLD_INPUT_VALIDATION => 'Numeric',
    FLD_DATABASE => 'sys_OwnerGroupId'),
    
    'f10' => array(FLD_ID => false, FLD_VISIBLE => true, FLD_DISPLAY => 'Set 1 to restrict access to group owning database', FLD_DISPLAY_SZ => 7,
    FLD_INPUT => true, FLD_INPUT_TYPE => 'text',
    FLD_INPUT_SZ => 1, FLD_INPUT_MAXLEN => 1, FLD_INPUT_DFLT => '0',
    FLD_INPUT_NOTEMPTY => true, FLD_INPUT_VALIDATION => 'Numeric',
    FLD_DATABASE => 'sys_RestrictAccessToOwnerGroup'),
    
    'f11' => array(FLD_ID => false, FLD_VISIBLE => true, FLD_DISPLAY => 'Allow online registration as user (confirmed by SysAdmin) 0=no, 1=allow', FLD_DISPLAY_SZ => 7,
    FLD_INPUT => true, FLD_INPUT_TYPE => 'text',
    FLD_INPUT_SZ => 1, FLD_INPUT_MAXLEN => 1, FLD_INPUT_DFLT => '',
    FLD_INPUT_NOTEMPTY => true, FLD_INPUT_VALIDATION => 'Numeric',
    FLD_DATABASE => 'sys_AllowRegistration'),
    
    'f12' => array(FLD_ID => false, FLD_VISIBLE => true, FLD_DISPLAY => 'Default owner group # for new records', FLD_DISPLAY_SZ => 7,
    FLD_INPUT => true, FLD_INPUT_TYPE => 'text',
    FLD_INPUT_SZ => 3, FLD_INPUT_MAXLEN => 4, FLD_INPUT_DFLT => '0',
    FLD_INPUT_NOTEMPTY => true, FLD_INPUT_VALIDATION => 'Numeric',
    FLD_DATABASE => 'sys_NewRecOwnerGrpID'),
    
    'f13' => array(FLD_ID => false, FLD_VISIBLE => true, FLD_DISPLAY => 'Non-owner default new record visibility - viewable, hidden', FLD_DISPLAY_SZ => 100,
    FLD_INPUT => true, FLD_INPUT_TYPE => 'text',
    FLD_INPUT_SZ => 8, FLD_INPUT_MAXLEN => 25, FLD_INPUT_DFLT => '',
    FLD_INPUT_NOTEMPTY => true, FLD_INPUT_VALIDATION => 'viewable, hidden',
    FLD_DATABASE => 'sys_NewRecAccess'),

    'f14' => array(FLD_ID => false, FLD_VISIBLE => true, FLD_DISPLAY => 'Directory for uploaded files (full path, overrides default if non-blank)', FLD_DISPLAY_SZ => 100,
    FLD_INPUT => true, FLD_INPUT_TYPE => 'text',
    FLD_INPUT_SZ => 50, FLD_INPUT_MAXLEN => 385, FLD_INPUT_DFLT => '',
    FLD_INPUT_NOTEMPTY => false, FLD_INPUT_VALIDATION => '',
    FLD_DATABASE => 'sys_UploadDirectory'),     // THIS ONE NOT ALLOWING BLANK  ????? 
    
    'f15' => array(FLD_ID => false, FLD_VISIBLE => true, FLD_DISPLAY => 'Additional folders containing files for indexing (FieldHelper compatibility)', FLD_DISPLAY_SZ => 100,
    FLD_INPUT => true, FLD_INPUT_TYPE => 'text',
    FLD_INPUT_SZ => 100, FLD_INPUT_MAXLEN => 1000, FLD_INPUT_DFLT => '',
    FLD_INPUT_NOTEMPTY => false, FLD_INPUT_VALIDATION => '',
    FLD_DATABASE => 'sys_MediaFolders'),  // THIS ONE NOT ALLOWING BLANK  ?????

    'f16' => array(FLD_ID => false, FLD_VISIBLE => true, FLD_DISPLAY => 'Carry out nightly URL validation (can overload targets), 0=no, 1=yes', FLD_DISPLAY_SZ => 7,
    FLD_INPUT => true, FLD_INPUT_TYPE => 'text',
    FLD_INPUT_SZ => 2, FLD_INPUT_MAXLEN => 5, FLD_INPUT_DFLT => '1',
    FLD_INPUT_NOTEMPTY => true, FLD_INPUT_VALIDATION => 'Numeric',
    FLD_DATABASE => 'sys_URLCheckFlag'),
    
    'f17' => array(FLD_ID => false, FLD_VISIBLE => true, FLD_DISPLAY => 'IMap Server for incoming emails to be archived', FLD_DISPLAY_SZ => 100,
    FLD_INPUT => true, FLD_INPUT_TYPE => 'text',
    FLD_INPUT_SZ => 30, FLD_INPUT_MAXLEN => 301, FLD_INPUT_DFLT => '',
    FLD_INPUT_NOTEMPTY => False, FLD_INPUT_VALIDATION => '',
    FLD_DATABASE => 'sys_eMailImapServer'),      // THIS ONE NOT ALLOWING BLANK  ????? 
    
    'f18' => array(FLD_ID => false, FLD_VISIBLE => true, FLD_DISPLAY => 'Port for incoming mail', FLD_DISPLAY_SZ => 100,
    FLD_INPUT => true, FLD_INPUT_TYPE => 'text',
    FLD_INPUT_SZ => 6, FLD_INPUT_MAXLEN => 16, FLD_INPUT_DFLT => '',
    FLD_INPUT_NOTEMPTY => False, FLD_INPUT_VALIDATION => '',
    FLD_DATABASE => 'sys_eMailImapPort'),   // THIS ONE NOT ALLOWING BLANK  ????? 
    
    'f19' => array(FLD_ID => false, FLD_VISIBLE => true, FLD_DISPLAY => 'Protocol for incoming mail', FLD_DISPLAY_SZ => 100,
	FLD_INPUT => true, FLD_INPUT_TYPE => 'text',
	FLD_INPUT_SZ => 6, FLD_INPUT_MAXLEN => 16, FLD_INPUT_DFLT => '',
	FLD_INPUT_NOTEMPTY => False, FLD_INPUT_VALIDATION => '',
	FLD_DATABASE => 'sys_eMailImapProtocol'),   // THIS ONE NOT ALLOWING BLANK  ????? 
    
	'f20' => array(FLD_ID => false, FLD_VISIBLE => true, FLD_DISPLAY => 'IMap Username for incoming mail', FLD_DISPLAY_SZ => 100,
	FLD_INPUT => true, FLD_INPUT_TYPE => 'text',
	FLD_INPUT_SZ => 10, FLD_INPUT_MAXLEN => 151, FLD_INPUT_DFLT => '',
	FLD_INPUT_NOTEMPTY => False, FLD_INPUT_VALIDATION => '',
	FLD_DATABASE => 'sys_eMailImapUsername'),
    
	'f21' => array(FLD_ID => false, FLD_VISIBLE => true, FLD_DISPLAY => 'IMap Password for incoming mail', FLD_DISPLAY_SZ => 100,
	FLD_INPUT => true, FLD_INPUT_TYPE => 'text',
	FLD_INPUT_SZ => 10, FLD_INPUT_MAXLEN => 61, FLD_INPUT_DFLT => '',
	FLD_INPUT_NOTEMPTY => False, FLD_INPUT_VALIDATION => '',
	FLD_DATABASE => 'sys_eMailImapPassword'),  // THIS ONE NOT ALLOWING BLANK  ????? 
    
	'f22' => array(FLD_ID => false, FLD_VISIBLE => true, FLD_DISPLAY => 'Database for access control tables (blank = sysUGrps table in this database)', FLD_DISPLAY_SZ => 100,
	FLD_INPUT => true, FLD_INPUT_TYPE => 'text',
	FLD_INPUT_SZ => 20, FLD_INPUT_MAXLEN => 193, FLD_INPUT_DFLT => '',
	FLD_INPUT_NOTEMPTY => False, FLD_INPUT_VALIDATION => '',
	FLD_DATABASE => 'sys_UGrpsDatabase'),
    
    // Not displayed, not yet implemented
	'f23' => array(FLD_ID => false, FLD_VISIBLE => true, FLD_DISPLAY => 'Database with which defintions are to be kept in sync (not yet impl.)', FLD_DISPLAY_SZ => 100,
	FLD_INPUT => false, FLD_INPUT_TYPE => 'text',
	FLD_INPUT_SZ => 2, FLD_INPUT_MAXLEN => 193, FLD_INPUT_DFLT => '',
	FLD_INPUT_NOTEMPTY => False, FLD_INPUT_VALIDATION => '',
	FLD_DATABASE => 'sys_SyncDefsWithDb')  // THIS ONE NOT ALLOWING BLANK ?????
                                         
	);

    $f13_values = array('0' => 'viewable', '1' => 'hidden'); 
    
	$show_data = false;
	$show_input = false;
	$show_message = false;
	$message = NULL;
	$start = 0;
	$fld_indices_notempty = NULL;
	$fld_indices_Email = NULL;
	$fld_indices_Alpha = NULL;
	$fld_indices_AlphaNum = NULL;
	$fld_indices_Numeric = NULL;
	$fld_indices_Float = NULL;
	$fld_indices_Date = NULL;
	$fld_indices_Time = NULL;

	if ($_SERVER['REQUEST_METHOD'] != "POST") {
		$mode = isset($_GET[INP_MODE]) ? $_GET[INP_MODE] : 's';
		if (($mode != 's') && ($mode != 'i') && ($mode != 'u')) {
			dbu_handle_error(ERR_INVALID_REQUEST);
		}
	} else if (isset($_POST[INP_MODE])) {
		$mode = $_POST[INP_MODE];
		if (($mode != 'i2') && ($mode != 'u2')) {
			dbu_handle_error(ERR_INVALID_REQUEST);
		}
	} else if (isset($_GET[INP_MODE])) {
		$mode = $_GET[INP_MODE];
		if (($mode != 's') && ($mode != 'i') && ($mode != 'u') && ($mode != 'd')) {
			dbu_handle_error(ERR_INVALID_REQUEST);
		}
	} else {
		dbu_handle_error(ERR_INVALID_REQUEST);
	}

	$keys = dbu_get_keys($fielddef);
	if (!$keys) {
		dbu_handle_error(ERR_NO_KEY);
	}

	$idx = 0;

	foreach($fielddef as $fkey=>$fld) {
		if ($fld[FLD_INPUT]) {
			if ($fld[FLD_INPUT_NOTEMPTY]) {
				if (!empty($fld_indices_notempty)) $fld_indices_notempty .= ', ';
				$fld_indices_notempty .= $idx;
			}
			if (!empty($fld[FLD_INPUT_VALIDATION])) {
				$name = "fld_indices_" . $fld[FLD_INPUT_VALIDATION];
				if (isset(${$name})) ${$name} .= ', ';
				${$name} .= $idx;
			}
		}
		$idx++;
	}

	$dbconn = dbu_factory($config['db']);
	/** @var dbconn */
	$dbconn->db_extension_installed();
	$dbconn->db_connect($config['dbhost'], $config['dblogin'], $config['dbpass'], $config['dbname'], $config['dbport']);

	switch ($mode) {
    case 's':
        $pager=array();
        $start = (isset($_GET[INP_START]) && is_numeric($_GET[INP_START])) ? (int)$_GET[INP_START] : 0;
        $rows = dbu_handle_select($fielddef, $scheme, $table, $dbconn, $keys, $start, $config['rows_per_page'], $config['pager_items'], $pager);
        if (!$rows && $dbconn->db_lasterror())
            dbu_handle_error($dbconn->db_lasterror());
        $show_data = true;
        break;
		case 'u':
		$row = dbu_fetch_by_key($fielddef, $scheme, $table, $dbconn, $_POST, $keys);
		$nextmode = 'u2';
		$show_input = true;
		break;
		case 'u2':
		$rslt = dbu_handle_update($fielddef, $scheme, $table, $dbconn, $_POST, $keys);
		if ($rslt) {
			$show_message = true;
			$message = sprintf(MSG_UPDATED, $start);
            header('Location: ' .HEURIST_BASE_URL. '?db='.HEURIST_DBNAME);
		} else {
			dbu_handle_error($dbconn->db_lasterror());
		}
		$dbconn->db_close();
		$nextmode = 's';
		break;
		/*
        case 'd':
		$rslt = dbu_handle_delete($fielddef, $scheme, $table, $dbconn, $_POST, $keys);
		if ($rslt) {
			$show_message = true;
			$message = sprintf(MSG_DELETED, $start);
		} else {
			dbu_handle_error($dbconn->db_lasterror());
		}
		$dbconn->db_close();
		$nextmode = 's';
		break;
        */
	}

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html><head>
		<title>Heurist Database identification/setting record (sysIdentification table)</title>
		<meta http-equiv="Content-Type" content="text/html; charset=<?php echo $config['encoding'];?>" />
		<style type="text/css">
			body                 { font-family: Tahoma,sans-serif,Verdana; font-size: 9pt;}
			table.datatable      { background: #fcfcfc; }
			table.datatable * td { padding: 0px 8px 0px 8px; margin: 0 8px 0 8px; }
			tr.sublight          { background: #ededed; }
			/*     table.datatable * tr { white-space: nowrap; } */
			table.datatable * th { background: #ffffcc; text-align: center; }
		</style>
		<script  type="text/javascript">
			<!--
			function doslice(arg, idx) {
				var ret = Array();
				for (var i = idx; i < arg.length; i++) {
					ret.push(arg[i]);
				}
				return ret;
			}

			function Check(theForm, what, regexp, indices) {
				for (var i = 0; i < indices.length; i++) {
					var el = theForm.elements[indices[i]];
					if (el.value == "") continue;
					var avalue = el.value;
					if (!regexp.test(avalue)) {
						alert("Field is not a valid " + what);
						el.focus();
						return false;
					}
				}
				return true;
			}

			function CheckEmail(theForm) {
				var regexp = /^[0-9a-z\.\-_]+@[0-9a-z\-\_]+\..+$/i;
				return Check(theForm, "email", regexp, doslice(arguments, 1));
			}

			function CheckAlpha(theForm) {
				var regexp = /^[a-z]*$/i;
				return Check(theForm, "alpha value", regexp, doslice(arguments, 1));
			}

			function CheckAlphaNum(theForm) {
				var regexp = /^[a-z0-9]*$/i;
				return Check(theForm, "alphanumeric value", regexp, doslice(arguments, 1));
			}

			function CheckNumeric(theForm) {
				for (var i = 1; i < arguments.length; i++) {
					var el = theForm.elements[arguments[i] - 1];
					if (el.value == "") continue;
					var avalue = parseInt(el.value);
					if (isNaN(avalue)) {
						alert("Field is not a valid integer number");
						el.focus();
						return false;
					}
				}
				return true;
			}

			function CheckFloat(theForm) {
				for (var i = 1; i < arguments.length; i++) {
					var el = theForm.elements[arguments[i]];
					if (el.value == "") continue;
					var avalue = parseFloat(el.value);
					if (isNaN(avalue)) {
						alert("Field is not a valid floating point number");
						el.focus();
						return false;
					}
				}
				return true;
			}

			function CheckDate(theForm) {
				for (var i = 1; i < arguments.length; i++) {
					var el = theForm.elements[arguments[i]];
					if (el.value == "") continue;
					var avalue = el.value;
					if (isNaN(Date.parse(avalue))) {
						alert("Field is not a valid date");
						el.focus();
						return false;
					}
				}
				return true;
			}

			function CheckTime(theForm) {
				var regexp = /^[0-9]+:[0-9]+:[0-9]+/i;
				if (!Check(theForm, "time", regexp,  doslice(arguments, 1)))
				return false;
				for (var i = 1; i < arguments.length; i++) {
					var el = theForm.elements[arguments[i]];
					if (el.value == "") continue;
					var avalue = el.value;
					if (isNaN(Date.parse("1/1/1970 " + avalue))) {
						alert("Field is not a valid time");
						el.focus();
						return false;
					}
				}
				return true;
			}

			function CheckRequiredFields(theForm) {
				for (var i = 1; i < arguments.length; i++) {
					var el = theForm.elements[arguments[i]-1]; //note: -1 added here by Ian based on forum post
					if (el.value=="") {
						alert("This field may not be empty");
						el.focus();
						return false;
					}
				}
				return true;
			}

			function CheckForm(theForm) {
				return (
				// removed to get this form to save - it complains about non-required fields being empty
                // CheckRequiredFields(theForm<?php echo isset($fld_indices_notempty) ? ", " . $fld_indices_notempty : "" ?>) &&
				CheckEmail(theForm<?php echo isset($fld_indices_Email) ? ", " . $fld_indices_Email : "" ?>) &&
				CheckAlpha(theForm<?php echo isset($fld_indices_Alpha) ? ", " . $fld_indices_Alpha : "" ?>) &&
				CheckAlphaNum(theForm<?php echo isset($fld_indices_AlphaNum) ? ", " . $fld_indices_AlphaNum : "" ?>) &&
                // removed to get this form to save - it complains about non-numeric fields not being numeric
                // CheckNumeric(theForm<?php echo isset($fld_indices_Numeric) ? ", " . $fld_indices_Numeric : "" ?>) &&
				CheckFloat(theForm<?php echo isset($fld_indices_Float) ? ", " . $fld_indices_Float : "" ?>) &&
				CheckDate(theForm<?php echo isset($fld_indices_Date) ? ", " . $fld_indices_Date : "" ?>) &&
				CheckTime(theForm<?php echo isset($fld_indices_Time) ? ", " . $fld_indices_Time: "" ?>)
				)
			}

			function onFormSubmit(){
				document.forms.ActionForm.action='?mode=u';
				document.forms.ActionForm.submit();
			}
			//-->
		</script>
	</head>


    <body>
		<?php
			if ($show_message) {
			?>
			<table cellpadding="1" cellspacing="0" border="0" bgcolor="#ababab"><tr><td>
						<table cellpadding="0" cellspacing="1" border="0" bgcolor="#ffffff"><tr><td>
							<?php echo $message?>
						</table>
				</td></tr>
			</table>

			<?php
				} else if ($show_input) {
                    $temp=HEURIST_DBNAME;
			?>
			<form name="InputForm" method="post" enctype="multipart-form-data"
				onsubmit="return CheckForm(this)"
				action="">
				<table border="0">
                	<?php  // INPUT
				    echo "<tr><td><h3>Database properties:&nbsp;&nbsp;&nbsp;<i> $temp </i></h3></td></tr>";
                		foreach($fielddef as $fkey=>$fld) {
							if ($fld[FLD_INPUT]) {
								echo "<tr><td>$fld[FLD_DISPLAY]</td>";
								$val = htmlentities($row[$fkey], ENT_QUOTES, $config['encoding']);
								switch ($fld[FLD_INPUT_TYPE]) {
									case "textarea":
									echo "<td><textarea name=\"$fkey\" cols=\"$fld[FLD_INPUT_SZ]\" rows=\"15\">$val</textarea></td></tr>";
									break;
									case "hidden":
									echo "<td><input name=\"$fkey\" type=\"$fld[FLD_INPUT_TYPE]\" value=\"$val\" /></td></tr>";
									break;
									case "select":
									echo "<td>". WriteCombo(${$fkey . '_values'}, $fkey, "") ."</td></tr>";
									break;
									default:
									echo "<td><input name=\"$fkey\" type=\"$fld[FLD_INPUT_TYPE]\" size=\"$fld[FLD_INPUT_SZ]\" maxlength=\"$fld[FLD_INPUT_MAXLEN]\" value=\"$val\" /></td></tr>";
								}
							}
						}
					?>
					<tr>
						<td>&nbsp;</td>
						<td><input type="submit" value="Save" /></td>
					</tr>
				</table>
				<input type="hidden" name="mode" value="<?php echo $nextmode;?>" />
				<?php // KEY
					if(isset($_POST[RKEY])) {
						$key = $_POST[RKEY];
						if (get_magic_quotes_gpc())
						$key = stripslashes($key);
						echo "<input type='hidden' name='".RKEY."' value='".htmlentities($key, ENT_QUOTES, $config['encoding'])."' />";
					}
				?>
			</form>
			<?php } else if ($show_data) { ?>
			<form name="ActionForm" method="post" action="">
				<table cellpadding="1" cellspacing="0" border="0" bgcolor="#ababab"><tr><td>
							<table cellpadding="0" cellspacing="1" border="0" class="datatable">
								<tr><th style="width: 25px;"></th>
									<?php  // DATA HEADER
										foreach ($fielddef as $fkey=>$fld) {
											if ($fld[FLD_DISPLAY]) {
												$wd = isset($fld[FLD_DISPLAY_SZ]) ? " style=\"width: $fld[FLD_DISPLAY_SZ]ex\"" : "";
												echo "<th$wd>" . htmlentities($fld[FLD_DISPLAY], ENT_QUOTES, $config['encoding']) . "</th>";
											}
										}
									?>
								</tr>
								<?php  // DATA
									$checked = ' checked="checked"';
									$i = 0;
									foreach($rows as $row) {
										$bk = $i++ % 2 ? "" : ' class="sublight"';
										echo "<tr$bk><td><input type='radio'$checked name='".RKEY."' value='".htmlentities($row[RKEY], ENT_QUOTES, $config['encoding'])."' /></td>";
										foreach ($fielddef as $fkey=>$fld) {
											if ($fld[FLD_VISIBLE]) {
												$value =  htmlentities($row[$fkey], ENT_QUOTES, $config['encoding']);
												if (!isset($value))
												$value = "&nbsp;";
												echo "<td>$value</td>";
											}
										}
										echo "</tr>\n";
										$checked = '';
									}
								?>
							</table>
				</td></tr></table><br />
				<?php // PAGER
					if (isset($pager[PG_PAGES])) {
						if (isset($pager[PG_PAGE_PREV])) {
							echo "<a href=\"?mode=s&amp;start=$pager[PG_PAGE_PREV]\">Prev</a>&nbsp;";
						} else {
							echo "Prev&nbsp;";
						}
						foreach($pager[PG_PAGES] as $pg => $st) {
							if ($st != $start) {
								echo "<a href=\"?mode=s&amp;start=$st\">$pg</a>&nbsp;";
							} else {
								echo "<b>$pg</b>&nbsp;";
							}
						}
						if (isset($pager[PG_PAGE_NEXT])) {
							echo "<a href=\"?mode=s&amp;start=$pager[PG_PAGE_NEXT]\">Next</a>&nbsp;";
						} else {
							echo "Next&nbsp;";
						}
						echo "<br />";
					}
				?>
				<br />
				<table cellpadding="1" cellspacing="0" border="0" bgcolor="#ababab"><tr><td>
							<table cellpadding="1" cellspacing="0" border="0" bgcolor="#fcfcfc"><tr><td>
							<input type="button" value="Edit" onclick="onFormSubmit()" />&nbsp;
							</td></tr>
							</table>
					</td></tr>
				</table>
			</form>
			<?php
				if(count($rows)==1){
					print "<script>onFormSubmit();</script>";
				}
			}
			?>

<hr><br>&nbsp;<b>Suggested workflow for new databases:</b>

<?php include("includeNewDatabaseWorkflow.html");  ?>
	</body>
</html>
