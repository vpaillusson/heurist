<?php
/**
* importCSV.php: UI for delimeted data import
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2014 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
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


/**
* import_session:  import_table, reccount, 
*                  columns:[col1,col2,col3, ...],    names of columns in file header 
*                  uniqcnt: [cnt1, cnt2, .... ],     count of uniq values per column  
*                  mapping:[rt1.dt1, rt2.dt2, rt3.dt3],   mapping of value fields to rectype.detailtype  
*                  indexes:[]   names of column =>rectype : columns in importtable that contains record_ID
*/

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__)."/../../common/php/getRecordInfoLibrary.php");
require_once('importCSV_lib.php');


/*    if(isForAdminOnly("to modify database structure")){
        return;
    }*/

$mysqli = mysqli_connection_overwrite(DATABASE);    

if(intval(@$_REQUEST["recid"])>0 && @$_REQUEST["table"] ){
    get_import_value($_REQUEST["recid"], $_REQUEST["table"]);
    exit();
}else if( @$_REQUEST["clearsession"] ){
    
    clear_import_session($_REQUEST["clearsession"]);
    exit();
}
?>
<html>
    <head>
        <title>Import Records from CSV (comma-separated values)</title>

        <script type="text/javascript" src="../../external/jquery/jquery-ui-1.10.2/jquery-1.9.1.js"></script>
        <script type="text/javascript" src="../../external/jquery/jquery-ui-1.10.2/ui/jquery-ui.js"></script>
        <link rel="stylesheet" href="../../applications/h4/ext/jquery-ui-1.10.2/themes/base/jquery-ui.css">
<!--        
        <script type="text/javascript" src="../../external/jquery/jquery.js"></script>
        <script type="text/javascript" src="../../external/jquery/jquery-ui-1.8.13.custom.min.js "></script>
        <link rel="stylesheet" href="../../external/jquery/jquery-ui-1.8.css">
-->        
        
        <link rel=stylesheet href="../../common/css/global.css" media="all">
        
        <script src="../../applications/h4/js/utils.js"></script>
<style type="text/css">
.tbmain th, .subh
{
    border-left: 1px solid gray;
    border-bottom: 1px solid gray;
}
.subh
{
    border-top: 1px solid gray;
}
.tbmain td
{
    border-left: 1px solid gray;
}
.tbmain
{
    border-top: 1px solid gray;
    border-right: 1px solid gray;
    border-bottom: 1px solid gray;
    font-size: 0.7em;
}
div.loading {
    width:90%;
    height:100%;
    background-image: url(../../common/images/loading-animation-white.gif);
    background-repeat: no-repeat;
    background-position:center center;
    padding:20px;
    text-align:center;
    font-weight: bold;
}
div.input-line{
    height:3em;
}
div.analized, div.analized2{
    background-color:#DDD;
    border:black solid 1px;
    display:inline-block;
    padding:5px;
    min-width:250px;
}
div.header{
    display:inline-block;
    font-weight: bold;
}
.truncate {
  max-width: 150px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.required { 
    font-weight: bold;
    color:red;
}
.help{
    font-size:0.9em;
}
</style>
    </head>

<body class="popup">
<script type="text/javascript">

var currentDb = "<?=HEURIST_DBNAME?>"; 
//
//
//
function showProgressMsg(msg){
    $("#div-progress").html(msg);
    $("#div-progress").show();
}
//
// clear all upload tables and empty session table
//
function doClearSession(session_id){
   
   var msg;
   if(session_id=='all') {
       msg = "Clear all saved sessions?";
   }else{
       msg = "Clear saved session?";
   }
    
   if( confirm(msg) ) {
       
                 $.ajax({
                         url: top.HEURIST.basePath+'import/delimited/importCSV.php',
                         type: "POST",
                         data: {clearsession: session_id, db:currentDb},
                         dataType: "json",
                         cache: false,
                         error: function(jqXHR, textStatus, errorThrown ) {
                              alert('Error connecting server. '+textStatus);
                         },
                         success: function( response, textStatus, jqXHR ){
                             if(response=="ok"){
                                 alert("Clear session operation completed");
                                 if(session_id=='all'){
                                    $("#import_id").empty();
                                 }else{
                                    window.close();
                                     //setTimeout(function(){window.close();}, 2000);
                                 }
                             }else{
                                alert(response);
                             }
                         }
                     });
   }
}
//
// submit form for second attempt of file upload
//
function doUpload2(){
    showProgressMsg('Please wait');
    $(document.forms[0]).hide();
    document.forms[0].submit();
}
</script>
<?php
//USER_ID:=get_user_id()
$imp_session = null;

$sa_mode = @$_REQUEST['sa_mode'];
$step = intval(@$_REQUEST["step"]);
if(!$step) $step=0;

echo '<div id="div-progress" style="display:none" class="loading">&nbsp;</div>';
ob_flush();flush();

//load session
if(intval(@$_REQUEST["import_id"])>0){
   echo '<script>showProgressMsg("Please wait, session loading in progress")</script>';
   ob_flush();flush();
   $imp_session = get_import_session($mysqli, $_REQUEST["import_id"]);
}

//print "SESSION 0    :".print_r($imp_session, true);
//DEBUG print "STEP  :".$step;

//first step - load file into import table
if($step==1 && $imp_session==null){ 
        echo '<script>showProgressMsg("Please wait, file is processing on server")</script>';
        ob_flush();flush();
    

        if(@$_REQUEST["filename"]){ //after postprocessing - additional step load from temp file
            $imp_session = postmode_file_load_to_db($_REQUEST["filename"], $_REQUEST["original"], (@$_REQUEST["preprocess"]=="1"));    
        }else{
            $imp_session = postmode_file_selection();    
        }
?>
<script type="text/javascript">
$( function(){ $("#div-progress").hide(); });

//
// reload 
//
function doReload(){
    $("#step").val(0);
    doUpload2();
}
</script>
<?php
        if(is_array($imp_session) && @$imp_session['errors']){ //preprocessing

            $col_count = $imp_session['col_count'];
            $errors = $imp_session['errors'];
            
            print "<h4>ERROR. Wrong field count in parsed data. Expected field count:".$col_count." </h4><hr width='100%' />";
            
            print "<table><tr><th>Line#</th><th>Field count</th><th>Raw data</th></tr>";
            
            foreach($errors as $err){
                print "<tr><td>".$err['no']."</td><td>".$err['cnt']."</td><td>".htmlspecialchars($err['line'])."</td></tr>";
            }
?>            
            </table>
            <div class="actionButtons">
                    <input type="button" value="Cancel" 
                        onClick="{window.location.href='importCSV.php?db=<?=HEURIST_DBNAME?>'}" 
                        style="margin-right: 5px;">
            </div>
</body>
</html>    
<?php            
            exit();
        
        }else if(is_array($imp_session) && @$imp_session['warning']){ //preprocessing
?>
        <h4><?=$imp_session['warning']?></h4>
        <hr width="100%" />
        
        <form action="importCSV.php" method="post" enctype="multipart/form-data" name="upload_form">
                <input type="hidden" name="db" value="<?=HEURIST_DBNAME?>">
                <input type="hidden" id="step" name="step" value="1">
                <input type="hidden" name="filename" value="<?=$imp_session['filename']?>">
                <input type="hidden" name="original" value="<?=$imp_session['original']?>">
            
                <input type="hidden" name="csv_delimiter" value="<?=$_REQUEST['csv_delimiter']?>">
                <input type="hidden" name="csv_linebreak" value="<?=$_REQUEST['csv_linebreak']?>">
                <input type="hidden" name="csv_enclosure" value="<?=$_REQUEST['csv_enclosure']?>">
                <input type="hidden" name="csv_dateformat" value="<?=$_REQUEST['csv_dateformat']?>">
                
<?php
$fields = @$imp_session['fields'];                  
if($fields){
    $k=0;
?>    
    <div class="help">Please check the list of columns read and select ones that contain dates (to be interpreted as a <b>date</b> field) or extended text strings (to be interpreted as a <b>memo</b> field)<br><br></div>
    <table><tr><td>Unspecified</td><td>Date</td><td>Memo</td><td>&nbsp;</td></tr>
<?php        
    foreach($fields as $field){
?>      
    <tr>
    <td align="center"><input type="checkbox" id="u_field_<?=$k?>" checked="checked" onchange="{if(this.checked){document.getElementById('m_field_<?=$k?>').checked = false; document.getElementById('d_field_<?=$k?>').checked = false;}}" /></td>
    <td align="center"><input type="checkbox" id="d_field_<?=$k?>" name="datefield[]" value="<?=$k?>" onchange="{document.getElementById('u_field_<?=$k?>').checked = !this.checked; if(this.checked) document.getElementById('m_field_<?=$k?>').checked = false;}" /></td>
    <td align="center"><input type="checkbox" id="m_field_<?=$k?>" name="memofield[]" value="<?=$k?>" onchange="{document.getElementById('u_field_<?=$k?>').checked = !this.checked; if(this.checked) document.getElementById('d_field_<?=$k?>').checked = false;}" /></td>
    <td><label><?=$field?></td>        
    </tr>  
<?php        
/*        print '&nbsp;&nbsp;&nbsp;&nbsp;<input type="checkbox" id="d_field_'.$k.'" name="datefield[]" value="'.$k.'" ';
        print 'onchange="{if(this.checked) document.getElementById(\'m_field_'.$k.'\').checked = false; }"  />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
        print '<input type="checkbox" id="m_field_'.$k.'" name="memofield[]" value="'.$k.'" ';
        print 'onchange="{if(this.checked) document.getElementById(\'d_field_'.$k.'\').checked = false; }"  />&nbsp;&nbsp;';
        print '<label for="field_'.$k.'" >'.$field.'</label><br />';        */
        $k++;
    }
    print '</table>';
}else{
        print '<input type="hidden" name="preprocess" value="1">';
}
?>
                <div class="actionButtons">
                    <input type="button" value="Cancel" onClick="doReload();" style="margin-right: 5px;">
                    <input type="button" value="Continue" style="font-weight: bold;" onclick="doUpload2()">
                </div>
        </form>            
</body>
</html>
<?php 
            exit();           
        }
}

//session is loaded - render second step page
if(is_array($imp_session)){ 

        //NOT USED ANYMORE
        if(false && @$_REQUEST["mvm"]){
print '<script type="text/javascript">$( function(){ $("#div-progress").hide(); });</script>';            
            if($_REQUEST["mvm"]==1){
?>
        <h4>INDEX KEYS GENERATED FROM MULTIVALUE FIELD</h4>
        <hr width="100%" />
  
        <form action="importCSV.php" method="post" enctype="multipart/form-data" name="upload_form">
                <input type="hidden" name="mvm" value="2">
<?php
    foreach ($_REQUEST as $name=>$value){
        if($name!='mvm')
            print '<input type="hidden" name="'.$name.'" value="'.$value.'">';
    }
?>
                <div class="actionButtons">
                    <input type="button" value="Cancel" onClick="{window.close(null);}" style="margin-right: 5px;">
                    <input type="button" value="Continue" style="font-weight: bold;" onclick="doUpload2()">
                </div>
        </form>  
        <table width="100%">
<?php

                $res = matchingMultivalues($mysqli, $imp_session, $_REQUEST);
                //print results of matching
                //$records = $res[1]; // imp_id => ids
                $pairs = $res[0];   // values => ids
           
                $sError = '';
                $sInsert = '';
                $sFound = '';
           
                foreach($pairs as $value =>$id){
                    
                    $value = explode("|",$value);
                    array_shift($value); //remove first element
                    $value = implode(";&nbsp;&nbsp;",$value);
                    
                    if(!is_numeric($id)){
                        //error
                        $sError = $sError.'<tr><td>'.$value.'</td><td align="center">'.$id.'</td></tr>';
                    }else if($id<0){
                        //insert
                        $sInsert = $sInsert.'<tr><td colspan="2">'.$value.'</td></tr>';
                    }else{
                        //found
                        $sFound = $sFound.'<tr><td>'.$value.'</td><td align="center">'.$id.'</td></tr>';
                    } 
                }
                
                if($sError!=''){
                    print '<tr><th align="left">Disambiguation</th><th>Instances</th></tr>'.$sError;
                }
                if($sInsert!=''){
                    print '<tr><th colspan="2" align="left">New records to be inserted</th></tr>'.$sInsert;
                }
                if($sFound!=''){
                    print '<tr><th colspan="2" align="left">Records found</th></tr><tr><th>Value</th><th>ID</th></tr>'.$sFound;
                }
                print '</table>';

            }else if($_REQUEST["mvm"]==2){

                $imp_session = assignMultivalues($mysqli, $imp_session, $_REQUEST);
                if(is_array($imp_session)){
                    print '<input type="button" value="Done! Close me" onClick="{window.close(111);}" style="margin-right: 5px;">';
                }else{
                    print "Error: ".$imp_session;
                }
            }
?> 
           
</body>
</html>   
<?php            
            exit();
        }
       

?>
        <script src="../../common/php/loadCommonInfo.php?db=<?=HEURIST_DBNAME?>"></script>
        <script src="../../common/js/utilsUI.js"></script>
        <script src="importCSV.js"></script>
        <script>
        var currentId = 1;
        var recCount = <?=$imp_session['reccount']?$imp_session['reccount']:0?>;
        var currentTable = "<?=$imp_session['import_table']?>";
        var currentSessionName = "<?=$imp_session['import_name']?>";
        var form_vals = <?=($step>1)?json_encode($_REQUEST):"{}"?>;
        </script>
<?php    

    $mode_import_result = "";
    if($step>1){
        $res = null;

        if($sa_mode==0){ //matching
            
            if($step==2){  //find  - NOT USED ANYMORE  - we trying to assign IDs at once
            
                echo '<script>showProgressMsg("Please wait, matching in progress")</script>';
                ob_flush();flush();
            
                $res = matchingSearch($mysqli, $imp_session, $_REQUEST);
                
//error_log(print_r($res, true));
                
            }else if($step==3){  //assign ids
                
                echo '<script>showProgressMsg("Please wait, assign of records ids")</script>';
                ob_flush();flush();
            
                $res = assignMultivalues($mysqli, $imp_session, $_REQUEST);
                //NOT USED ANYMORE $res = matchingAssign($mysqli, $imp_session, $_REQUEST);
                
                if(is_array($res) && count(@$res['validation']['disambiguation'])>0){
                    print '<script>form_vals["error_message"] = "There is disambiguation for your matching criteria. Please resolve it before proceed further";</script>';
                }else{
                    print '<script>form_vals["auto_switch_to_import"] = "1";</script>';
                    //$sa_mode = 1; //ART switch to import  autimatically???
                }
            }
            
        }else{//importing
        
            if($step==2){  //verification
            
                echo '<script>showProgressMsg("Please wait, mapping validation in progress")</script>';
                ob_flush();flush();
            
                $res = validateImport($mysqli, $imp_session, $_REQUEST);
                
//error_log(print_r($res, true));
                
            }else if($step==3){  //create records - load to import data to database
                $mode_import_result = ' style="display:none"';
?>
<div id="main_import_result">
        <h4>IMPORT DATA RESULTS</h4>
        <hr width="100%" />            
        <div id="div-progress2"></div>
        <div>
<?php
                ob_flush();flush();
            
                $res = doImport($mysqli, $imp_session, $_REQUEST);
?>   
        </div>
        <br /><br /><input type="button" value="Back" onClick="{showRecords('mapping');}">            
</div><!-- main_import_result -->  
<?php                
            }
        
        }
        if(is_array($res)) {
            $imp_session = $res;
        }else if($res && !is_array($res)){
            print '<script>form_vals["error_message"] = "'.$res.'";</script>';
            //echo "<p style='color:red'>ERROR: ".$res."</p>";        
        }
    }
    
    $len = count($imp_session['columns']);
  
/* DEBUG  
echo "<div>imp session: ".print_r($imp_session)."<br>";
echo "REUQEST: ".print_r($_REQUEST)."</div>";
*/
?>
<form action="importCSV.php" method="post" enctype="multipart/form-data" name="import_form" onsubmit="return verifySubmit()">
        <input type="hidden" name="db" value="<?=HEURIST_DBNAME?>">
        <input type="hidden" name="step" id="input_step" value="2">
        <input type="hidden" id="mvm" name="mvm" value="0">
        <input type="hidden" id="multifield" name="multifield" value="">
        <input type="hidden" id="import_id" name="import_id" value="<?=$imp_session["import_id"]?>">
        <input type="hidden" id="csv_enclosure" name="csv_enclosure" value="<?=@$imp_session["csv_enclosure"]?>">

<div id="main_mapping"<?=$mode_import_result?>>

        <h4>IMPORT DATA Step 2 <font color="red">(Work in Progress)</font></h4>
        <hr width="100%" />
        
      
        
        <div class="help">
If the spreadsheet data is complex, this function will allow you to progressively import columns which identify subsidiary entities (record types) such as place, organisation, collection, series, artist etc. The first step is to match key fields and create new records from unmatched rows. This will create a new column ending in ID. This can be used as the key field to import additional columns. Once all subsidiary entities have been matched and imported, you can import the primary entity type representing by the table.
<br/><br/>
Please visit <a target="_blank" href="http://HeuristNetwork.org/archive/importing-data">HeuristNetwork.org/archive/importing-data</a> for a detailed explanation and examples of record import.        
<br/><br/><br/>
        </div>


    <div class="input-line">
        <div class="header"><label for="sa_rectype" style="color:red">Select record type</label></div>
        <select name="sa_rectype" id="sa_rectype" class="text ui-widget-content ui-corner-all" style="min-width:290px"></select>
    </div>
    
<?php
if(@$imp_session['load_warnings']){
    print "<div style='padding:20px;'><span style='color:red;font-weight:bold'>There are warnings on load data </span>".$imp_session['load_warnings'][0].
        "&nbsp;&nbsp;<a href='#' onclick='showRecords(\"load_warnings\")'>show&gt;</a></div>";
}
?>      

<input type="hidden" value="<?=$sa_mode?>" name="sa_mode" id="sa_mode"/> 

<div id="tabs_actions">
    <ul>
        <li><a href="#matching">Search / match</a></li>
        <li><a href="#import">Insert / update records</a></li>
    </ul>    
    <div id="matching">
        <span class="help">Select one or more of the input columns to match input rows with records in the database. Matched rows will be identified by record ID. Unmatched rows will be marked for creation of new records</span>
        <span class="help">Select import columns and matching to database fields for the selected record type<br/><br/></span>
        
<!--        
        <span class="help">Select key fields and find record ID to unique identification of import record. Heurist database is not affected<br/><br/></span>
-->
        <div id="div_idfield" style="padding-left:30px;display:none;">

                <div id="div_idfield_new">
                    <label for="new_idfield">New column to hold record identifiers</label>
                    <input id="new_idfield" name="new_idfield" type="text" size="20" /><br />                        
                    <span class="help">(you may wish to change this to identify a specific use of the entity represented by the key columns selected)</span>
                    
                </div>
                <div id="div_idfield_exist" style="display: none;">
                    <span>You have already used <span id="span1">Person ID as a field name&nbsp;</span> to hold the IDs of <span id="span2">Person</span> records (which may later be used as a Pointer Field in Update mode).<br /></span>
                    <input type="radio" id="rb_idfield0" name="idfield0" checked="checked" 
                            onchange="{/*$('#rb_idfield1_div').hide();*/$('#rb_idfield0_div').show();$('#idfield').val('')}"/>
                    <label for="rb_idfield0">I wish to use a distinct field name</label>
                        <div id="rb_idfield0_div" style="padding-left: 30px;">
                            <label for="new_idfield2">Enter new field name</label>
                            <input id="new_idfield2" type="text" size="20" onchange="{$('#new_idfield').val(event.target.value)}"/>
                        </div>
                    <div>
                    <input type="radio" id="rb_idfield1" name="idfield0" 
                            onchange="{onExistingIDfieldSelect()}" />
                    <label for="rb_idfield1">I wish to redo the matching, re-using <span id="span3">this field name</span></label>
                        <div id="rb_idfield1_div" style="display: none; padding-left: 30px;">
                            <label for="idfield">Select column</label>
                            <select id="idfield" name="idfield">
                                <option value="" disabled="disabled">select...</option>
                 <?php
                //list of all ID fields
                for ($i = 0; $i < $len; $i++) { 
                    $rectype = @$imp_session['indexes']["field_".$i];
                    $isIndex = ($rectype!=null);
                    if($isIndex){
                        print '<option class="idfield2_'.$rectype.'" value="field_'.$i.'">'.$imp_session['columns'][$i].'</option>';
                    }            
                }
                ?>                       
                            </select>
                        </div>
                      </div>    
                </div>

        </div>
    
        <!-- matching table-->
        <div>
        <br/>
        
        <table class="tbmain" style="width:100%" cellspacing="0" cellpadding="2">
        <thead><tr>
            <th>Key<br/>field</th><th>Unique<br/>values</th><th>Column</th><th>Mapping</th>
            <th width="300px" style="text-align: left;padding-left: 6px;">
                <a href="#" onclick="getValues(0);return false;"><img src="../../common/images/calendar-ll-arrow.gif" /></a>
                <a href="#" onclick="getValues(-1);return false;"><img src="../../common/images/calendar-l-arrow.gif" /></a>
                Values in row <span id="currrow_0"></span>
                <a href="#" onclick="getValues(1);return false;"><img src="../../common/images/calendar-r-arrow.gif" /></a>
                <a href="#" onclick="getValues(recCount);return false;"><img src="../../common/images/calendar-rr-arrow.gif" /></a>
            </th></tr>
        </thead>
        <?php
        //
        // render matching table 
        //
        
        
        for ($i = 0; $i < $len; $i++) {     
            print '<td align="right">&nbsp;<span style="display:none;"><input type="checkbox" id="cbsa_keyfield_'.$i.'" value="field_'.$i
                    .'" onchange="{showHideSelect2('.$i.');}" '
                    .(in_array($i, $imp_session['multivals'])?'multivalue="yes"':'')
                    .' column="'.$imp_session['columns'][$i].'"'
                    .'/></span></td>';
            print '<td align="center">'.$imp_session['uniqcnt'][$i].'</td><td class="truncate">'.$imp_session['columns'][$i].'</td>';
            
            print '<td style="width:306px;">&nbsp;<span style="display:none;">'
                       .'<select name="sa_keyfield_'.$i.'" id="sa_keyfield_'.$i.'" style="max-width:260px" onchange="{onFtSelect2('.$i.');}">'
                       . '</select></span></td>';    
            print '<td id="impval_'.$i.'" style="text-align: left;padding-left: 16px;"> </td></tr>';
              
        }

        ?>
        </table>
        </div>
    
    </div>
<!-- ************************************************************************************ -->    
    <div id="import">
        <span class="help">Choose the ID column identifying the records to be inserted or updated from the dropdown. Choose the data fields to be updated from the table.<br/><br/></span>
           
        <div style="padding-left:30px;">
            <div style="padding-left:30px;">
            <input type="radio" <?=@$_REQUEST['sa_upd']>0?"":"checked"?> name="sa_upd" id="sa_upd0" value="0" class="text" 
                    onchange="{onUpdateModeSet()}"/>&nbsp;
            <label for="sa_upd0">Retain existing values and append distinct new data as repeat values (existing values are not duplicated)</label><br/>
            
            <input type="radio" <?=@$_REQUEST['sa_upd']==1?"checked":""?> name="sa_upd" id="sa_upd1" value="1" class="text" 
                    onchange="{onUpdateModeSet()}"/>&nbsp;
            <label for="sa_upd1">Add new data only if field is empty (new data ignored for non-empty fields)</label><br/>
            
            <input type="radio" <?=@$_REQUEST['sa_upd']==2?"checked":""?> name="sa_upd" id="sa_upd2" value="2" class="text" 
                    onchange="{onUpdateModeSet()}" />&nbsp;
            <label for="sa_upd2">Add and replace all existing value(s) for the record with new data</label>
            </div>
            <div style="padding-left:60px;font-size:0.9em;vertical-align: top;display:<?=(@$_REQUEST['sa_upd']==2?"block":"none")?>" id="divImport2">
            <input type="radio" <?=@$_REQUEST['sa_upd2']>0?"":"checked"?> name="sa_upd2" id="sa_upd20" value="0" class="text" />&nbsp;
            <label for="sa_upd20" style="font-size:0.9em;">Retain existing if no new data supplied for record</label><br/>
            
            <input type="radio" <?=@$_REQUEST['sa_upd2']==1?"checked":""?> name="sa_upd2" id="sa_upd21" value="1" class="text" />&nbsp;
            <label for="sa_upd21" style="font-size:0.9em;">Delete existing if no new data supplied for record</label>
            </div>
        </div>    
        <br/>
        <div>
            <label for="recid_field" style="color:red">Choose column identifying the records to be updated</label>
            <select id="recid_field" name="recid_field" onchange="{onRecIDselect2()}">
                <option value="">select...</option>
        <?php   
        //created ID fields     
        $hasc = false;
        for ($i = 0; $i < $len; $i++) {     
                $rectype = @$imp_session['indexes']["field_".$i];
                $isIndex = ($rectype!=null);
                if($isIndex){
                    $hasc = true;
                    print '<option class="idfield_'.$rectype.'" value="field_'.$i.'">'.$imp_session['columns'][$i].'</option>';
                }            
        }
        if($hasc)
            print '<option id="idfield_separator" value="0" disabled="disabled" >-------</option>';
        //all other fields
        for ($i = 0; $i < $len; $i++) {     
                $rectype = @$imp_session['indexes']["field_".$i];
                $isIndex = ($rectype!=null);
                if(!$isIndex){
                    print '<option value="field_'.$i.'">'.$imp_session['columns'][$i].'</option>';
                }            
        }
        ?>
            </select>
        </div>
        <br/>
        
        <table class="tbmain" style="width:100%" cellspacing="0" cellpadding="2">
        <thead><tr>
            <th>Import<br/>value</th><th>Unique<br/>values</th><th style="max-width:100px;">Column</th><th width="310">Mapping</th>
            <th width="30%" style="text-align: left;padding-left: 16px;">
                <a href="#" onclick="getValues(0);return false;"><img src="../../common/images/calendar-ll-arrow.gif" /></a>
                <a href="#" onclick="getValues(-1);return false;"><img src="../../common/images/calendar-l-arrow.gif" /></a>
                Values in row <span id="currrow_1"></span>
                <a href="#" onclick="getValues(1);return false;"><img src="../../common/images/calendar-r-arrow.gif" /></a>
                <a href="#" onclick="getValues(recCount);return false;"><img src="../../common/images/calendar-rr-arrow.gif" /></a>
            </th></tr>
        </thead>
        <?php
        //
        // render mapping table with list of columns and datatype selectors
        //
        $sIndexes = "";
        $sRemain = "";
        $sProcessed = "";
        $recStruc = getAllRectypeStructures(true);
        $idx_dt_name = $recStruc['typedefs']['dtFieldNamesToIndex']['rst_DisplayName'];
        //$idx_rt_name = $recStruc['commonNamesToIndex']['rty_Name'];

        for ($i = 0; $i < $len; $i++) {     
            
            $isProcessed = @$imp_session["mapping"]["field_".$i];
            $isIndex = false;
            
            if($isProcessed){
                $checkbox = '<td align="center"><input type="checkbox" disabled="disabled" /></td>'; //processed
            }else{
                $rectype = @$imp_session['indexes']["field_".$i];
                $isIndex = ($rectype!=null);
                $checkbox = '<td align="right">&nbsp;<span style="display:none;"><input type="checkbox" id="cbsa_dt_'.$i.'" onchange="{showHideSelect('.$i.');}"/></span></td>';
            }
            
            $s = '<tr>'.$checkbox.'<td align="center">'.$imp_session['uniqcnt'][$i].'</td><td class="truncate">'.$imp_session['columns'][$i].'</td>';

            if($isProcessed){
                
                $rt_dt = explode(".",$isProcessed);
                $recTypeName = $recStruc['names'][$rt_dt[0]];//['commonFields'][ $idx_rt_name ];
                $dt_Name = intval($rt_dt[1])>0?$recStruc['typedefs'][$rt_dt[0]]['dtFields'][$rt_dt[1]][$idx_dt_name]:$rt_dt[1];

                $s = $s.'<td>'.$recTypeName.' '.$dt_Name.'  ('.$rt_dt[0].'.'.$rt_dt[1].')</td>';
            }else{ ;
                $s = $s.'<td style="width:306px;">&nbsp;<span style="display:none;">'
                       .'<select name="sa_dt_'.$i.'" id="sa_dt_'.$i.'" style="max-width:260px" onchange="{onFtSelect('.$i.');}"'
                       . ($isIndex?'class="indexes"':'').'></select></span></td>';
            }
            $s = $s.'<td id="impval'.$i.'" style="text-align: left;padding-left: 16px;"> </td></tr>';

            if($isProcessed){
                $sProcessed = $sProcessed.$s;    
            }else{
                if($isIndex){
                    $sIndexes=$sIndexes.$s;    
                }else {
                    $sRemain=$sRemain.$s;    
                }
            }
        }//for
        if($sIndexes){
            print '<tr><td class="subh" colspan="6"><b>Record IDs</b></td></tr>'.$sIndexes;
        }
        if($sRemain){
            print '<tr><td class="subh" colspan="6"><b>Remaining Data</b></td></tr>'.$sRemain;
        }
        if($sProcessed){
            print '<tr><td class="subh" colspan="6"><b>Already imported</b></td></tr>'.$sProcessed;
        }
            
        ?>
        </table>  
               
    </div>
</div>    
<br />
<b>Records in buffer: <?=$imp_session['reccount']?>&nbsp;&nbsp;Fields:&nbsp;<?=$len?></b>
<div class="help" style="float:right;width:240px;text-align:right;">Unprocessed data is retained in buffer on exit</div><br />

                <table style="vertical-align:middle;width:100%">
                    <tr>
                    <td width="30%">  
                        <span class="matching">
                            <span id="btnStartMatch" style="display: none">
                            <!--
                                <input type="submit" value="Start search/match" style="font-weight: bold;">
                            -->    
                                <input type="button" value="Start search / match / Assign IDs" 
                                    onclick="doMatching()" style="font-weight: bold;">
                                
                            </span>
                        </span>
                        <span class="importing">
                            <input type="button" value="<< Previous" style="font-weight: bold; width:20em"
                                title="Go to Matching step" onclick='$( "#tabs_actions" ).tabs( "option", "active", 0 );' >
                        </span>
                    </td>
<?php
//
// render validation result box
//

    $validationRes = @$imp_session['validation'];
    if($validationRes){
        $cnt_error   = intval(@$validationRes['count_error']);
        $show_err    = ($cnt_error>0)?"<a href='#' onclick='showRecords(\"error\")'>show</a>" :"&nbsp;";

        $cnt_disamb   = count(@$validationRes['disambiguation']);
        $show_disamb    = ($cnt_disamb>0)?"<a href='#' onclick='showRecords(\"disamb\")'>show</a>" :"&nbsp;";
        
        $cnt_update  = intval(@$validationRes['count_update']);
        $show_update = ($cnt_update>0)?"<a href='#' onclick='showRecords(\"update\")'>show</a>" :"&nbsp;";

        $cnt_insert  = intval(@$validationRes['count_insert']);
        $show_insert = ($cnt_insert>0)?"<a href='#' onclick='showRecords(\"insert\")'>show</a>" :"&nbsp;";
        
        $cnt_insert_nonexist_id  = intval(@$validationRes['count_insert_nonexist_id']);
        
?>                    
                    <td><div class="analized2">
                        <table style="display: inline-block; border:none" border="0">
                            <tr><td>Records matched:</td><td><?=$cnt_update?></td><td><?=$show_update?></td></tr>
                            <tr><td>New records to create:</td><td><?=$cnt_insert?></td><td><?=$show_insert?></td></tr>
<?php        if($sa_mode==0){ ?>                            
                            <tr><td><font<?=($cnt_disamb>0?" color='red'":'')?>>Rows with ambiguous match:</font></td><td><?=$cnt_disamb?></td><td><?=$show_disamb?></td></tr>
<?php        } else { ?>                            
                            <tr><td>Rows with field errors:</td><td><?=$cnt_error?></td><td><?=$show_err?></td></tr>
<?php        }        ?>                            
                        </table>
                        <div style="float:right;vertical-align:middle;padding-top:10px">
<?php if($sa_mode==1){  ?>                     
                            <span class="importing"><input type="button" value="Create records" onclick="doDatabaseUpdate(<?=$cnt_insert_nonexist_id?>, <?=$cnt_error?>)" style="font-weight: bold;"></span>
<?php }
      if($cnt_disamb>0){
?>            
                            <span class="matching"><input type="button" value="Resolve ambiguous matches" onclick="showRecords('disamb')" style="font-weight: bold;">
<?php }else{ ?>
                            <span class="matching"><input type="button" value="Next >>" title="Go to Import step" onclick='$( "#tabs_actions" ).tabs( "option", "active", 1 );' style="font-weight: bold;"></span>
<?php
      }
?>            
                        </div>
                    </div></td>
<?php
    }else{
        print '<td></td>';
    }
?>                  
                    <td style="width:30%">
                        <span class="importing">
                            <input  id="btnStartImport" type="submit" 
                                value="Prepare insert/update >>" style="disabled:disabled;font-weight: bold; width:20em">
                        </span>

                    <!-- 
                        <input type="button" value="Close" onClick="window.close();" style="margin-right: 5px;">
                        <input type="button" value="Clear" onclick="doClearSession(<?=$_REQUEST["import_id"]?>)">
                        <div class="help">Unprocessed data is <br>retained in buffer</div>
                    -->    
                    </td>
                </tr>
                </table>
</div>
<?php
//Warnings that SQL command LOAD DATA generates
if(@$imp_session['load_warnings']){
?>
    <div id="main_load_warnings" style="display:none;">
            <h4>WARNINGS ON LOAD DATA</h4>
            <hr width="100%" />
<?php    
            renderWarnings( $imp_session );
            print "</div>";
}


if($validationRes){
    if($cnt_error>0){    
?>
    <div id="main_error" style="display:none;">
            <h4>RECORDS WITH FIELD ERRORS</h4>
            <hr width="100%" />
            <div>
            </div><br />
<?php
//<span class="help">&nbsp;(* indicates fields with invalid values)</span>
//                <font color="red"><@$validationRes['err_message']</font>

            renderRecords( 'error', $imp_session );
            print "</div>";
    }
    if($cnt_insert>0){    
?>
    <div id="main_insert" style="display:none;">
            <h4>RECORDS TO BE CREATED</h4>
            <hr width="100%" />
<?php
            renderRecords( 'insert', $imp_session );
            print "</div>";
    }
    if($cnt_update>0){    
?>
    <div id="main_update" style="display:none;">
            <h4>RECORDS TO BE UPDATED</h4>
            <hr width="100%" />
<?php
            renderRecords( 'update', $imp_session );
            print "</div>";
    }
    if($cnt_disamb>0){    
?>
    <div id="main_disamb" style="display:none;">
            <h4>DISAMBIGUATION</h4>
            <hr width="100%" />
<?php
            renderDisambiguation( 'disamb', $imp_session );
            print "</div>";
    }
}

print '</form>';    

// PAGE STEP 1 ================================================================================
}else{ 
    if($imp_session!=null){
        echo "<p style='color:red'>ERROR: ".$imp_session."</p>";        
    }
?>
<script type="text/javascript">
$( function(){ $("#div-progress").hide(); });
//
// submit form on new file upload
//
function doUpload(){
    showProgressMsg('Please wait, file is uploading (time required will depend on your network connection speed)');
    $(document.forms[0]).hide();
    document.forms[0].submit();
}
//
// submit form on session select
//
function doSelectSession(){
    showProgressMsg('Loading selected session');
    $(document.forms[0]).hide();
    document.forms[0].submit();
}
</script>
        <h4>UPLOAD DATAFILE OR SELECT SAVED SESSION Step 1 <font color="red">Work in Progress</font></h4>
        <hr width="100%" />
        <form action="importCSV.php" method="post" enctype="multipart/form-data" name="upload_form">
                <input type="hidden" name="db" value="<?=HEURIST_DBNAME?>">
                <input type="hidden" name="step" value="1">
            
                <div class="input-line">
                     <label>Select existing session:</label>
                     <select name="import_id" id="import_id" onchange="doSelectSession()"><?=get_list_import_sessions()?></select>
                     <input type="button" value="Clear All Sessions" style="font-weight: bold;float:right;" 
                                    onclick="doClearSession('all')">
                </div>
                <div class="input-line">
                    <label>Or upload new CSV file:</label><input type="file" size="50" name="import_file">
                </div>
                <div class="input-line">
                    Field separator: <select name="csv_delimiter"><option value="," selected>comma</option><option value="tab">tab</option></select>&nbsp;&nbsp;&nbsp;
                    <!-- Multi-value separator: <select name="val_separator"><option selected value="|">|</option><option value=",">,</option><option value=":">:</option><option value=";">;</option></select>&nbsp;&nbsp;&nbsp; -->
                    Line separator: <select name="csv_linebreak">
                                        <option selected value="auto">Auto detect</option>
                                        <option value="\r\n">Windows</option>
                                        <option value="\n">Unix</option>
                                        <option value="\r">Mac</option>
                                    </select>
                    <!-- <input name="csv_linebreak" value="\n"> -->&nbsp;&nbsp;&nbsp;
                    Quote: <select name="csv_enclosure"><option selected value='2'>"</option><option value="1">'</option></select>
                    Date format: <select name="csv_dateformat"><option selected value='1'>dd/mm/yyyy</option><option value="2">mm/dd/yyyy</option></select><br/><br/>
                </div>
                <div class="help">
<!--If you have generated the text file on a Windows system, you might have to use LINE SEPARATOR '\r\n'                
<br /><br />-->                 
Interpret characters preceded by the escape character "\" as escape sequences. For example, "\t", "\n", and "\\" signify tab, newline, and backslash, respectively
<br /><br />
To avoid ambiguity, occurrences of the QUOTE character within a field value can be doubled and are interpreted as a single instance of the character. For example, if QUOTE '"' is specified, quotation marks are handled as shown here:
"The ""BIG"" boss"  -> The "BIG" boss
                </div>
                <div class="actionButtons">
                    <input type="button" value="Cancel" onClick="window.close();" style="margin-right: 5px;">
                    <input type="button" value="Continue" style="font-weight: bold;" onclick="doUpload()">
                </div>
        </form>    
<?php
}
?>        
</body>
</html>
<?php

//
// get file from _REQUEST and call import to db if everything is OK
//
function postmode_file_selection() {

/*****DEBUG****/// error_log("postmode_file_selection");

    // there are two ways into the file selection mode;
    // either the user has just arrived at the import page,
    // or they've selected a file *and might progress to file-parsing mode*
    $error = '';
    if (@$_FILES['import_file']) {
        if ($_FILES['import_file']['size'] == 0) {
            $error = 'no file was uploaded';
        } else {
            switch ($_FILES['import_file']['error']) {
                case UPLOAD_ERR_OK:
                break;
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                $error = "The uploaded file was too large.  Please consider importing it in several stages.";
                break;
                case UPLOAD_ERR_PARTIAL:
                $error = "The uploaded file was only partially uploaded.";
                break;
                case UPLOAD_ERR_NO_FILE:
                $error = "No file was uploaded.";
                break;
                case UPLOAD_ERR_NO_TMP_DIR:
                $error = "Missing a temporary folder.";
                break;
                case UPLOAD_ERR_CANT_WRITE:
                $error = "Failed to write file to disk";
                break;
                default:
                $error = "Unknown file error";
            }
        }

        if (!$error) {    // move on to the next stage!
            $error = postmode_file_load_to_db($_FILES['import_file']['tmp_name'], $_FILES['import_file']['name'], true);    
        }
    }

    return $error;
}

//
// read header (1st line)
// create temporary table import_datetime
// load file into table
// add record to import_log
//
function postmode_file_load_to_db($filename, $original, $is_preprocess) {

    global $mysqli;

    //$val_separator = $_REQUEST["val_separator"];
    $csv_delimiter = $_REQUEST["csv_delimiter"];
    $csv_linebreak = $_REQUEST["csv_linebreak"];
    $csv_enclosure = ($_REQUEST["csv_enclosure"]==1)?"'":'"';
    
    if($csv_delimiter=="tab") {
        $csv_delimiter = "\t";
    }
            
    if($csv_linebreak=="auto"){
        ini_set('auto_detect_line_endings', true);
        $lb = null;
    }else{
        $lb = str_replace("\\n", "\n", $csv_linebreak);
        $lb = str_replace("\\r", "\r", $lb);
        $lb = str_replace("\\t", "\t", $lb);
    }
    
    $handle = @fopen($filename, "r");
    if (!$handle) {
            if (! file_exists($filename)) return 'file does not exist';
            else if (! is_readable($filename)) return 'file is not readable';
            else return 'file could not be read';
    }
    
//error_log(">>>>".$lb);    
    // read header
    if($csv_linebreak=="auto"){ 
        $line = fgets($handle, 1000000);
    }else{
        $line = stream_get_line($handle, 1000000, $lb);
    }
    fclose($handle);
    if(!$line){
        return "empty header line";
    }

    //get fields
    $fields = str_getcsv ( $line, $csv_delimiter, $csv_enclosure );// $escape = "\\"
    $len = count($fields);

    if($len>200){
        return "too many columns ".$len;
    }
    
    if($is_preprocess){
        $temp_file = tempnam(sys_get_temp_dir(), $filename);
        if (move_uploaded_file($filename, $temp_file)) {
            if($len==1){
                return array("warning"=>"You appear to have only one value per line. This probably indicates that you have selected the wrong separator type. Continue?",
                     "filename"=>$temp_file, "original"=>$original );
            }else{
                return array("warning"=>"Please verify the list of columns",
                     "filename"=>$temp_file, "original"=>$original, "fields"=>$fields );
            }
        }else {
            return "Failed to keep the uploaded file '$temp_file'.";
        }
    }
    
    //array( "filename"=>$temp_name, "errors"=>$errors, "memos"=>$memos, "multivals"=>$multivals )
    $preproc = preprocess_uploaded_file($filename);

//DEBUG print print_r($preproc, true);
    
    if(count($preproc['errors'])>0){
        return array("errors"=>$preproc['errors']);
    }
    
    $filename = $preproc['filename'];
    
    $import_table = "import".date("YmdHis");
    
//error_log(">>>> ".print_r($fields, true)."  ".$len);
    
    //create temporary table import_datetime
    $query = "CREATE TABLE `".$import_table."` (`imp_ID` int(10) unsigned NOT NULL AUTO_INCREMENT, ";
    $columns = "";
    $counts = "";
    $mapping = array();
    for ($i = 0; $i < $len; $i++) {     
        $query = $query."`field_".$i."` ".(in_array($i, $preproc['memos'])?" text, ":" varchar(300), " ) ; 
        $columns = $columns."field_".$i.",";
        $counts = $counts."count(distinct field_".$i."),";
        //array_push($mapping,0);
    }
    $query = $query." PRIMARY KEY (`imp_ID`)) ENGINE=InnoDB  DEFAULT CHARSET=utf8;";

    $columns = substr($columns,0,-1);
    $counts = $counts." count(*) ";
    
    
    if (!$mysqli->query($query)) {
        return "can not create table: " . $mysqli->error;
    }    
    
    if($csv_enclosure=="'") $csv_enclosure = "\\".$csv_enclosure;
    
    //load file into table
    $query = "LOAD DATA LOCAL INFILE '".$filename."' INTO TABLE ".$import_table
        ." CHARACTER SET UTF8"
        ." FIELDS TERMINATED BY '".$csv_delimiter."' "
        ." OPTIONALLY ENCLOSED BY '".$csv_enclosure."' "
        ." LINES TERMINATED BY '\n'"  //.$csv_linebreak."' "   //".$csv_linebreak."
        //." IGNORE 1 LINES 
        ." (".$columns.")";
    
//DEBUG error_log(">>>".$query);
        
    if (!$mysqli->query($query)) {
        return "can not import data: " . $mysqli->error;
    } 
    
    $warnings = array();
    if ($info = $mysqli->info) {
        if ($mysqli->warning_count) { 
           array_push($warnings, $info);
           $e = $mysqli->get_warnings(); 
           do { 
               array_push($warnings, $e->message); //$e->errno.": ".
           } while ($e->next()); 
        }         
        /*if(strpos("$info", "Warnings: 0")===false){
            $mysqli->query("SHOW WARNINGS");
        }*/
    }
    if(!$is_preprocess && file_exists($filename)){
        unlink($filename);
    }   
    
    //calculate uniq values    
    $query = "select ".$counts." from ".$import_table;
    $res = $mysqli->query($query);
    if (!$res) {
        return "can not count unique values: " . $mysqli->error;
    }    
    
    $uniqcnt = $res->fetch_row();
    $reccount = array_pop ( $uniqcnt );
   
//error_log("uniq>>>> ".print_r($uniqcnt, true)."  ".$len);
    //add record to import_log
    $session = array("reccount"=>$reccount,
                     "import_table"=>$import_table,
                     "import_name"=>($original."  ".date("Y-m-d H:i:s")),
                     "columns"=>$fields,   //names of columns in file header 
                     "memos"=>$preproc['memos'], 
                     "multivals"=>$preproc['multivals'],
                     "csv_enclosure"=>$_REQUEST['csv_enclosure'],
                     "uniqcnt"=>$uniqcnt,   //count of uniq values per column  
                     "mapping"=>$mapping,   //mapping of value fields to rectype.detailtype  
                     "indexes"=>array() );  //names of columns in importtable that contains record_ID
    
    $session = saveSession($mysqli, $session);
    if(count($warnings)>0){
        $session['load_warnings'] = $warnings;
    }
    return $session;
}

/*
Read file

Add error to log if wrong field count
Identify memo fields by presence of \r within text and flag
Identify repeating value fields and flag - will not be used as key fields
Remove any spaces at start/end of fields (including potential memos) & any redundant spaces in field that is not multi-line 
Convert dates to standardised format.

Write processed file

return 3 arrays: 
    errors
    memos
    multivalues
*/
function preprocess_uploaded_file($filename){
    
    $errors = array();
    $memos = array();  //multiline fields
    $multivals = array();
    $datefields = @$_REQUEST["datefield"];
    $memofields = @$_REQUEST["memofield"];
    if(!$datefields) $datefields = array();
    if(!$memofields) $memofields = array();
    
    $csv_delimiter = $_REQUEST["csv_delimiter"];
    $csv_linebreak = $_REQUEST["csv_linebreak"];
    $csv_enclosure = ($_REQUEST["csv_enclosure"]==1)?"'":'"';
    $csv_dateformat = $_REQUEST["csv_dateformat"];
    
    if($csv_delimiter=="tab") {
        $csv_delimiter = "\t";
    }
            
    if($csv_linebreak=="auto"){
        ini_set('auto_detect_line_endings', true);
        $lb = null;
    }else{
        $lb = str_replace("\\n", "\n", $csv_linebreak);
        $lb = str_replace("\\r", "\r", $lb);
        $lb = str_replace("\\t", "\t", $lb);
    }
    
    $handle = @fopen($filename, "r");
    if (!$handle) {
            if (! file_exists($filename)) return 'file does not exist';
            else if (! is_readable($filename)) return 'file is not readable';
            else return 'file could not be read';
    }

    $len = 0;
    $header = null;
    
    $temp_name = tempnam(sys_get_temp_dir(), $filename);
    if (!is_writable($temp_name)) {
        return "can not save preprocessed file $temp_name";
    }
    if (!$handle_wr = fopen($temp_name, 'w')) {
         return "Cannot open file ($temp_name)";
    }
    
    
    $line_no = 0;
    while (!feof($handle)) {
        
        if($csv_linebreak=="auto" || $lb==null){ 
            $line = fgets($handle, 1000000);
        }else{
            $line = stream_get_line($handle, 1000000, $lb);
        }

        
        $fields = str_getcsv ( $line, $csv_delimiter, $csv_enclosure );// $escape = "\\"
        
//DEBUG  print $line_no."   ".print_r($fields, true)."<br>";
//DEBUG ob_flush();flush();

        if($len==0){
            $header = $fields;
            $len = count($fields);
        }else{
            $line_no++;
            
            if(trim($line)=="") continue;
            
            if($len!=count($fields)){
                 // Add error to log if wrong field count
                 array_push($errors, array("cnt"=>count($fields), "no"=>$line_no, "line"=>substr($line,0,2000)));
                 if(count($errors)>100) break;
            }else{
                $k=0;
                $newfields = array();
                foreach($fields as $field){

                    //Identify repeating value fields and flag - will not be used as key fields                    
                    if( !in_array($k, $multivals) && strpos($field, '|')!==false ){
                        array_push($multivals, $k);
                    }
                    if( !in_array($k, $memos) && (in_array($k, $memofields) || strpos($field, '\\r')!==false) ){
                        array_push($memos, $k);
                    }
                    
                    //Remove any spaces at start/end of fields (including potential memos) & any redundant spaces in field that is not multi-line 
                    if(in_array($k, $memos)){
                        $field = trim($field);
                    }else{
                        $field = trim(preg_replace('/([\s])\1+/', ' ', $field));    
                    }

                    //Convert dates to standardised format.                    
                    if(in_array($k, $datefields) && $field!=""){
                           if(is_numeric($field) && abs($field)<99999){ //year????
                               
                           }else{
                                if($csv_dateformat==1){
                                    $field = str_replace("/","-",$field);
                                }
                                $field = strtotime($field);
                                $field = date('Y-m-d H:i:s', $field);
                           }
                    }
                    
                    //Doubling up as an escape for quote marks
                    $field = addslashes($field);
                    $field = $csv_enclosure.$field.$csv_enclosure;
                    array_push($newfields, $field);
                    $k++;
                }
                
                $line = implode($csv_delimiter, $newfields)."\n";
                if (fwrite($handle_wr, $line) === FALSE) {
                    return "Cannot write to file ($temp_name)";
                }
            }
        }
        
    }
    fclose($handle);
    fclose($handle_wr);
    
    unlink($filename);
    
//DEBUG print "Saved to file: ".$temp_name."<br>";
    
    return array( "filename"=>$temp_name, "col_count"=>$len, "errors"=>$errors, "memos"=>$memos, "multivals"=>$multivals );
}

?>
