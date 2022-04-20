<?php
//@TODO wrap to class - use DbsTerms class

/**
* verifyValue.php - library of functions to verify values - pointers and terms to conform to
* the constraints in detail and record type definitions
* Used in listDatabaseErrors.php, importCSV_lib.php
* @todo saveRecordDetail and importRectype
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.2
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

//reset - reset all cache 
//getAllowedTerms
//isValidTerm
//isValidTermLabel
//isValidTermCode
//isValidPointer

class VerifyValue {

     /**
     * Construct won't be called inside this class and is uncallable from
     * the outside. This prevents instantiating this class.
     * This is by purpose, because we want a static class.
     */
    private function __construct() {}    
    private static $system = null;
    private static $mysqli = null;
    private static $initialized = false;

    private static $dtyIDDefs = array();  //list of allowed terms for particular detail type ID
    private static $dtyIDDefs_labels = array();  //terms by basefield with hierarchy
    private static $dtyIDDefs_labels_plain = array(); //terms  by basefield without hierarchy
    private static $dtyIDDefs_codes = array();
    private static $terms = null;
    private static $dbs_terms = null;

    private static $detailtypes = array();
    
    
    private static function initialize()
    {
        if (self::$initialized)
            return;

        global $system;
        self::$system = $system;
        self::$mysqli = $system->get_mysqli();    
        
        self::$initialized = true;
    }

    //
    // clear all global variables
    // it is required in case database switch
    //
    public static function reset($new_system=null){
        self::$dtyIDDefs = array();  //list of allowed terms for particular detail type ID
        self::$dtyIDDefs_labels = array();
        self::$dtyIDDefs_labels_plain = array();
        self::$dtyIDDefs_codes = array();
        
        self::$dtyIDDefs_codes = array();
        
        self::$detailtypes = array();
        
        if($new_system!=null){
            self::$system = $new_system;
            self::$mysqli = $new_system->get_mysqli();   
            self::$initialized = true; 
        }
    }
    
    /**
    * Loads terms defintions
    * 
    */
    public static function getTerms(){
        if(self::$terms == null){ //load definitions
            self::$terms = dbs_GetTerms(self::$system); 
            self::$dbs_terms = new DbsTerms(self::$system, self::$terms);
        }  
        return self::$dbs_terms;
    }

    /**
    * get all terms ids allowed for given field type
    * 
    * @param mixed $defs - array of all terms
    * @param mixed $defs_nonsel - array of disabled(header) terms
    * @param mixed $dtyID - detail type i
    */
    public static function getAllowedTerms($defs, $defs_nonsel, $dtyID){

        self::initialize();

        $allowed_terms = null;

        if($dtyID==null || !@self::$dtyIDDefs[$dtyID]){ //detail type ID is not defined or terms are already found

            self::$system->defineConstant('DT_RELATION_TYPE');

            if ( $dtyID == DT_RELATION_TYPE) {
                $parent_id = 'relation';
            }else if(is_array($defs) && count($defs)==1){
                $parent_id = $defs[0]; //vocabulary
            }else{
                $parent_id = $defs;
            }
            if($parent_id==null || $parent_id==''){
                $allowed_terms = 'all';
            }else{
                if(self::$terms == null){ //load definitions
                    self::$terms = dbs_GetTerms(self::$system); 
                    self::$dbs_terms = new DbsTerms(self::$system, self::$terms);
                }  
                $allowed_terms = self::$dbs_terms->treeData($parent_id, 3);
            }

            self::$dtyIDDefs[$dtyID] = $allowed_terms;

            /*
            if ( $dtyID == DT_RELATION_TYPE) {
            //get all root terms (vocabs)
            $allowed_terms = getTermListAll(self::$mysqli, 'relation'); //from db_structure
            self::$dtyIDDefs[$dtyID] = $allowed_terms;

            } else {

            $terms = getTermsFromFormat($defs); //db_structure

            if (($cntTrm = count($terms)) > 0) {

            if ($cntTrm == 1) {  //vocabulary
            $vocabId = $terms[0];
            $terms = getTermOffspringList(self::$mysqli, $vocabId); //db_structure
            array_push($terms, $vocabId);

            }else{
            $nonTerms = getTermsFromFormat($defs_nonsel); //from db_structure
            if (count($nonTerms) > 0) {
            $terms = array_diff($terms, $nonTerms);
            }
            }
            if (count($terms)<1) {
            $allowed_terms = "all";
            }else{
            $allowed_terms = $terms;
            }

            if($dtyID!=null){ //keep for future use
            self::$dtyIDDefs[$dtyID] = $allowed_terms;
            }

            }
            }
            */        
        }else{
            //take from cache
            $allowed_terms = self::$dtyIDDefs[$dtyID];
        }
        return $allowed_terms;
    }

    //
    // return term id with given label in given vocabulary 
    //
    public static function hasVocabGivenLabel($vocab_id, $label){

        if(self::$terms == null){
            self::$terms = dbs_GetTerms(self::$system); 
            self::$dbs_terms = new DbsTerms(self::$system, self::$terms);
        }  

        return self::$dbs_terms->getTermByLabel($vocab_id, $label);
    }


/**
* Verifies that term ID value is valid for given detail id
*
* @param mixed $defs    - json or list of allowed terms (or vocabulary term id)
* @param mixed $defs_nonsel - list of terms that are not selectable
* @param mixed $trmID - term id
* @param mixed $dtyID - detail type id
*/
public static function isValidTerm($trmID, $dtyID, $terms_ids=null, $terms_nonsel=null){

    if(!$terms_ids){
        
        $dtype = getDetailType(self::$system, self::$detailtypes, $dtyID); //from dbs_structure
        if ($dtype) {
            $idx = self::$detailtypes['fieldNamesToIndex']['dty_JsonTermIDTree'];
            $terms_ids = @$dtype[$idx];
            $idx = self::$detailtypes['fieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs']; //not used
            $terms_nonsel = @$dtype[$idx];
        }
    }
    
    $allowed_terms = self::getAllowedTerms($terms_ids, $terms_nonsel, $dtyID);
    
    return $allowed_terms && ($allowed_terms === "all" || in_array($trmID, $allowed_terms));
}

/**
* Returns term ID if label is valid and false if invalid
* Label can be dot separated hierarchical label Parent.Child
*
* used in import csv
* 
* @param mixed $defs - array of all terms for dty
* @param mixed $defs_nonsel
* @param mixed $label
* @param mixed $dtyID
*/
public static function isValidTermLabel($defs, $defs_nonsel, $label, $dtyID, $isStripAccents=false){

    if($dtyID==null || !@self::$dtyIDDefs_labels[$dtyID]){
        
        //label may have fullstop in its own name - so we always search with and without hierarchy
        $withHierarchy = true;//(strpos($label,'.')>0); 
    
        self::initialize();
        if(self::$terms==null){
            self::$terms = dbs_GetTerms(self::$system);   
            self::$dbs_terms = new DbsTerms(self::$system, self::$terms);
        }
        $allowed_terms = self::getAllowedTerms($defs, $defs_nonsel, $dtyID);
        
        $allowed_labels = array();
        $allowed_labels_plain = array();
        
        $idx_label = self::$terms['fieldNamesToIndex']['trm_Label'];
    
        //get all labels    
        $domain = @self::$terms['termsByDomainLookup']['relation'][$allowed_terms[0]]?'relation':'enum';
        $list = self::$terms['termsByDomainLookup'][$domain];
        foreach($allowed_terms as $term_id){
           /*if($withHierarchy){
           }else{
           }*/ 
           $allowed_labels[$term_id] = getTermFullLabel(self::$terms, $list[$term_id], $domain, false);//returns term with parent
           $allowed_labels_plain[$term_id] = $list[$term_id][$idx_label];    
           //remove last point
           $allowed_labels[$term_id] = trim($allowed_labels[$term_id],'.');
        }
    
        if($isStripAccents && is_array($allowed_labels)){
            array_walk($allowed_labels, 'trim_lower_accent2');
            
            array_walk($allowed_labels_plain, 'trim_lower_accent2');
        }
        
        //keep for future use
        if($dtyID!=null){
            self::$dtyIDDefs_labels[$dtyID] = $allowed_labels;
            self::$dtyIDDefs_labels_plain[$dtyID] = $allowed_labels_plain;
        }
        
    }else{
        $allowed_labels = self::$dtyIDDefs_labels[$dtyID];
        $allowed_labels_plain = self::$dtyIDDefs_labels_plain[$dtyID];
    }
    
    //check if given label among allowed
    $label = trim(mb_strtolower($label));
    $label = trim($label,'.');
    /*if(strpos($label,'.')>0){
        $label = explode('.',$label);
        $label = array_pop($label);    
    }*/

    if(count($allowed_labels)>0){
        $term_ID = array_search($label, $allowed_labels, true);
        if(!($term_ID>0)){
            $term_ID = array_search($label, $allowed_labels_plain, true);
        }
    }else{
        return false;
        //$term_ID = getTermByLabel($label); //db_structure
    }

    return $term_ID;
}

/**
* Returns term ID if code is valid and false if invalid
*
* used in import csv
* 
* @param mixed $defs
* @param mixed $defs_nonsel
* @param mixed $code
* @param mixed $dtyID
*/
public static function isValidTermCode($defs, $defs_nonsel, $code, $dtyID){
    
    if($dtyID==null || !@self::$dtyIDDefs_codes[$dtyID]){
    
        self::initialize();
        if(self::$terms==null){
            self::$terms = dbs_GetTerms(self::$system);   
            self::$dbs_terms = new DbsTerms(self::$system, self::$terms);
        }
        $allowed_terms = self::getAllowedTerms($defs, $defs_nonsel, $dtyID);
        
        $allowed_codes = array();
        
        $idx_code = self::$terms['fieldNamesToIndex']['trm_Code'];
    
        //get all codes  
        $domain = @self::$terms['termsByDomainLookup']['relation'][$allowed_terms[0]]?'relation':'enum';
        $list = self::$terms['termsByDomainLookup'][$domain];
        foreach($allowed_terms as $term_id){
           $allowed_codes[$term_id] = mb_strtolower($list[$term_id][$idx_code]);
        }
    
        //keep for future use
        if($dtyID!=null){
            self::$dtyIDDefs_codes[$dtyID] = $allowed_codes;
        }
        
    }else{
        $allowed_codes = self::$dtyIDDefs_codes[$dtyID];
    }
    
    //check if given code among allowed
    $code = trim(mb_strtolower($code));

    if(is_array($allowed_codes)){
        $term_ID = array_search($code, $allowed_codes, true);
    }else{
        return false;
        //$term_ID = getTermByCode($code); //db_structure
    }

    return $term_ID;
}

//-------------------------------------
//
// verify that given record $rec_ID is a rectype $rty_ID that suits $constraints in base field $dty_ID
// 
public static function isValidPointer( $rec_ID, $dty_ID, $rty_ID, $constraints=null ){

    $isvalid = false;
    
    self::initialize();
    
    if(isset($rec_ID) && is_numeric($rec_ID) && $rec_ID>0){
        $rty_ID = mysql__select_value( self::$mysqli, 'select rec_RecTypeID from Records where rec_ID = '.$rec_ID);
    }
    
    if ($rty_ID>0){
        
            if($constraints=='any'){
                $isvalid = true; //just check that record exists
            }else{
                
                $allowed_types = 'any';
                
                if ($constraints==null || $constraints == '') {
                    
                    $dtype = getDetailType(self::$system, self::$detailtypes, $dty_ID); //from dbs_structure
                    
                    if ($dtype) {
                        $idx = self::$detailtypes['fieldNamesToIndex']['dty_PtrTargetRectypeIDs'];
                        $constraints = @$dtype[$idx];
                    }
                }
                
                //get allowed record types
                $temp = is_array($constraints)?$constraints:explode(',',@$constraints); 
                if (count($temp)>0) {
                    $allowed_types = $temp;
                }
                
                $isvalid = ($allowed_types === 'any' || in_array($rty_ID, $allowed_types));
            }
    }
    
    return $isvalid;
}


}
?>

