/*
* editCMS.js - loads websiteRecord.php in edit mode
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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

var layoutMgr;  

function hLayoutMgr(){

    var _className = "hLayoutMgr";

    var pnl_counter = 1;

    var body = $(this.document).find('body');
    
    var isEditMode = false;

    //
    //
    //
    function _layoutInitKey(layout, i){
        
        if(!layout[i].key){
            layout[i].key = pnl_counter;
            layout[i].title = '<span data-lid="'+pnl_counter+'">' + layout[i].name 
                                +'</span>';
            layout[i].folder = (layout[i].children && layout[i].children.length>0);
        
            pnl_counter++;
        }
    }
    
    //---------------------------------------
    //
    // container - id or element
    // layout - JSON
    //
    function _layoutInit(layout, container, supp_options){
        container = $(container);
        
        container.empty();   
        
        if(typeof layout === 'string'){
            if(layout.indexOf('data-heurist-app-id')>0){ //old format
            
                container.html(layout);
            
                window.hWin.HAPI4.LayoutMgr.appInitFromContainer( document, '#main-content', supp_options );
                return false;
            }
        }
        
        var res = window.hWin.HEURIST4.util.isJSON(layout);
        
        if(res===false){
            //if(layout==''){ layout = 'Add content here'}
            
            layout = [{name:'Page', type:'group',
                    children:[
                        {name:'Content', type:'text', css:{}, content: layout}
                    ] 
                }]; 
        }else{
            layout = res;    
        }
        
        for(var i=0; i<layout.length; i++){
            
            _layoutInitKey(layout, i);
            
            var ele = layout[i];
            
            if(ele.type=='cardinal'){
                
                _layoutInitCardinal(ele, container);
                
            }else if(ele.type=='tabs'){
                
                _layoutInitTabs(ele, container);
                
            }else if(ele.type=='accordion'){
             
                _layoutInitAccordion(ele, container);
                
            }else if(ele.children && ele.children.length>0){ //free, flex or group
                
                _layoutInitGroup(ele, container);
                
            }else if( (ele.type && ele.type.indexOf('text')==0) || ele.content){
                //text elements
                _layoutInitText(ele, container);
                
            }else if(ele.type=='widget' || ele.appid){
                //widget element
                
                _layoutAddWidget(ele, container);
                
            }
        }//for
        
        return layout;
    }//_layoutInit

    function _layoutInitGroup(layout, container){
        
        //create parent div
        $d = $(document.createElement('div'));
        $d.attr('id','hl-'+layout.key).attr('data-lid', layout.key) 
                .appendTo(container);
                
        if(isEditMode){
            $d.css({'border':'2px dotted gray','border-radius':'4px','margin':'4px'});  
        }

        if(layout.css && !$.isEmptyObject(layout.css)){
            $d.css(layout.css);
        }
        
        _layoutInit(layout.children, $d);
        
    }
    function _layoutInitText(layout, container){

        $d = $(document.createElement('div'));
        $d.attr('id','hl-'+layout.key).attr('data-lid', layout.key)
            .addClass('tinymce-body editable')
            .appendTo(container);
            
        if(isEditMode){
            $d.css({'border':'1px dotted gray','border-radius':'4px','margin':'4px'});  
        } 
            
        if(layout.css && !$.isEmptyObject(layout)){
            $d.css( layout.css );    
        }

        $d.html(layout.content);
    }
    
    //
    //
    //
    function _layoutAddWidget(layout, container){

        $d = $(document.createElement('div'));
        $d.attr('id','hl-'+layout.key).attr('data-lid', layout.key)
        .addClass('heurist-widget editable')
        .appendTo(container);
        
        if(!layout.css){
            layout.css  = {};    
            layout.css['minHeight'] = '100px';
        } 
        layout.css['position'] = 'relative';
        //layout.css['height'] = '100%';
        
        //default min-height position depends on widget
        var app = _getWidgetById(layout.appid);
        if(app.minw>0 && !layout.css['minWidth']){
            layout.css['minWidth'] = app.minw;
        }
        if(app.minh>0 && !layout.css['minHeight']){
            layout.css['minHeight'] = app.minh;
        }

        if(isEditMode) {
            $d.css('border','2px dashed red');
        }
        
        if(layout.css && !$.isEmptyObject(layout)){
            
            $d.removeAttr('style');
            $d.css( layout.css );    
        }
        
        _layoutInitWidget(layout, container.find('#hl-'+layout.key));

    }
    
    //
    //
    //
    function _getWidgetById(id){

        var i;
        for(i=0; i<cfg_widgets.length; i++){
            if(cfg_widgets[i].id==id){
                return cfg_widgets[i];
            }
        }
        return null;
    }
    
    //
    //
    //
    function _layoutInitWidget(layout, container){

        
        //var layout = _layoutContentFindElement(_layout_cfg, container.attr('data-lid'));

        var app = _getWidgetById(layout.appid); //find in app array (appid is heurist_Search for example)

        if (app && app.script && app.widgetname) { //widgetname - function name to init widget

            if($.isFunction($('body')[app.widgetname])){ //OK! widget script js has been loaded            

                container[app.widgetname]( layout.options );   //call function

            }else{

                $.getScript( window.hWin.HAPI4.baseURL + app.script, function() {  //+'?t='+(new Date().getTime())
                    if($.isFunction(container[app.widgetname])){
                        container[app.widgetname]( layout.options );   //call function
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr('Widget '+app.widgetname+' not loaded. Verify your configuration');
                    }
                });

            }

        }
    }

    //
    // groups of containers    
    //
    function _layoutInitCardinal(layout, container){
        
        var key_id = 'hl-'+layout.key;
        
        if(container.attr('id')==key_id){
            $d = container;    
        }else{
            $d = container.find('#'+key_id);
        }
        
        if($d.length>0){
            container = $d.parent();            
            $d.remove();
        }
        
        //create parent div
        $parent = $(document.createElement('div'));
        $parent.attr('id', key_id)
          .attr('data-lid', layout.key)
          .css({height:'100%',width:'100%'})
          .appendTo(container);
        
        if(isEditMode) $parent.css('border','2px dashed green');
        
        
        var layout_opts = {applyDefaultStyles: true, maskContents: true};
    
        for(var i=0; i<layout.children.length; i++){
            
            _layoutInitKey(layout.children, i);
            
            lpane = layout.children[i];
            var pos = lpane.type;
            
            if(lpane.size){
                layout_opts[pos+'__size'] = lpane.size;
            }
            if(Hul.isnull(lpane.resizable) || lpane.resizable ){
                if(lpane.minsize){
                    layout_opts[pos+'__minSize'] = lpane.minsize;
                }
            }else{
                layout_opts[pos+'__spacing_open'] = 0;
            }
            
            //create cardinal div
            $d = $(document.createElement('div'));
            $d.addClass('ui-layout-'+pos)
              //.attr('id','hl-'+lpane.key)
              //.attr('data-lid', lpane.key)
              .appendTo($parent);

              
            $d2 = $(document.createElement('div'));
            $d2.attr('id','hl-'+lpane.key)
              .attr('data-lid', lpane.key)
              .addClass('layout-content')
              //.addClass('ent_wrapper')
              .appendTo($d);
              
              
            //@todo additional container for children>1        
            layout_opts[pos+'__contentSelector'] = '#hl-'+lpane.key;
                    
            //init                    
            _layoutInit(layout.children[i].children, $d2);
                    
        }//for
    
    
        $parent.layout( layout_opts );
    }
    
    //
    //
    //
    function _layoutInitTabs(layout, container){
        
        var key_id = 'hl-'+layout.key;
        
        if(container.attr('id')==key_id){
            $d = container;    
        }else{
            $d = container.find('#'+key_id);
        }
        
        if($d.length>0){
            container = $d.parent();            
            $d.remove();
        }
        
        //create parent div
        $d = $(document.createElement('div'));
        $d.attr('id', key_id)
          .attr('data-lid', layout.key)
          .appendTo(container);
          
        if(isEditMode) $d.css('border','2px dotted blue');
          
        if($d.parent().hasClass('layout-content')){
            $d.addClass('ent_wrapper');    
        }

        //tab panels    
        _layoutInit(layout.children, $d);
                
        //tab header
        $d = body.find('#'+key_id);
        var groupTabHeader = $('<ul>').prependTo($d);
        
        for(var i=0; i<layout.children.length; i++){
      
            //.addClass('edit-form-tab')
            $('<li>').html('<a href="#hl-'+layout.children[i].key
                                +'"><span style="font-weight:bold">'
                                +layout.children[i].name+'</span></a>')
                        .appendTo(groupTabHeader);
            
        }
        
        $d.tabs();
    }
    
    //
    //
    //
    function _layoutInitAccordion(layout, container){
       
        var key_id = 'hl-'+layout.key;
        
        if(container.attr('id')==key_id){
            $d = container;    
        }else{
            $d = container.find('#'+key_id);
        }
        
        if($d.length>0){
            container = $d.parent();            
            $d.remove();
        }
            
        //create parent div
        $d = $(document.createElement('div'));
        $d.attr('id', key_id)
              .attr('data-lid', layout.key)
              .appendTo(container);
       
        if(isEditMode) $d.css('border','2px dotted blue');
       
        //accordion panels    
        _layoutInit(layout.children, $d);
       
        //accordion headers
        for(var i=0; i<layout.children.length; i++){
      
            $d = body.find('#hl-'+layout.children[i].key);
            
            $('<h3>').html( layout.children[i].name )
                     .insertBefore($d);
            
        }
        
        $d = body.find('#'+key_id);
        $d.accordion({heightStyle: "content", 
                //active:(currGroupType == 'expanded')?0:false,
                      collapsible: true });
    }
    
    //
    // Find element in array
    //
    function _layoutContentFindElement(content, ele_id){
        
        for(var i=0; i<content.length; i++){
            if(content[i].key == ele_id){
                return  content[i];
            }else if(content[i].children && content[i].children.length>0){
                var res = _layoutContentFindElement(content[i].children, ele_id);    
                if(res) return res;
            }
        }
        return null; //not found
    }
    
    //public members
    layoutMgr = {

        getClass: function () {
            return _className;
        },

        isA: function (strClass) {
            return (strClass === _className);
        },
        
        layoutInitTabs: function(layout, container){
            _layoutInitTabs(layout, container);    
        },
       
        layoutInitAccordion: function(layout, container){
            _layoutInitAccordion(layout, container);    
        },
        
        layoutInit: function(layout, container, supp_options){
            return _layoutInit(layout, container, supp_options);
        },
        
        layoutInitKey: function(layout, i){
            _layoutInitKey(layout, i);
        },
        
        
        layoutContentFindElement: function(_layout_cfg, key){
            return _layoutContentFindElement(_layout_cfg, key);    
        },
        
        setEditMode: function(newmode){
            isEditMode = newmode;            
        }
        
    }
}
   
