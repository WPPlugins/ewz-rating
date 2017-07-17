'use strict';
/* ewzalrt RS014 */

jQuery(document).ready(function() {
    init_ewz_schemes();
});
var ajaxurl;
var ewzG, ewzG1;  
var newfieldN;  // number of latest added field
var newschemeN;  // number of latest added scheme
var popupN;  // used for id of popup 
var g_changesMade, g_doingSubmit;

/************************ The OnLoad Function  ****************************************/
/* called on load                                   */
/* generates the whole page from the ewzG structure */
function init_ewz_schemes() {
    fixConsole();
    newfieldN = 0;
    g_changesMade = false;
    g_doingSubmit = false;

    popupN = 0;
    newschemeN = 0;
    // ewzG is null if not logged in
    if (null !== ewzG1) {
        ewzG = ewzG1.gvar;
       if( ewzG.do_warn ){ 
           window.addEventListener("beforeunload", function (e) {
                if( g_changesMade && !g_doingSubmit ){
                    var confirmationMessage = "Really leave this page? Changes you made may not be saved.";
                    (e || window.event).returnValue = confirmationMessage; //Gecko + IE
                    return confirmationMessage;                            //Webkit, Safari, Chrome
                }
           });
        }
        // console.log(ewzG);
        for (var snum = 0; snum < ewzG.schemes.length; ++snum) {
            // error message, page titles, fields
            var sid = ewzG.schemes[snum].rating_scheme_id;
            jQuery('#ewz_schemes').append(scheme_str( snum, sid, ewzG.schemes[snum]));

            // add some functionality
            setup_scheme( snum, sid );
            
            // add onChange function  
            jQuery('#f' + sid + '_scheme_name_').change(function(){
                var jthis = jQuery(this);
                jthis.closest('div[id^="ewz_ewz_box-scheme_f"]').find('h3[id^="tpg_header_f"]').text(jthis.val());
            });


            set_drag_drop( sid );
        }
        if (ewzG.message) {
            ewz_alert('RS001', ewzG.message, ++popupN); 
        }
        jQuery('#ewz_schemes').after(add_scheme_button_str());

        // make the scheme postboxes sortable
        jQuery('#ewz_schemes').sortable({
            handle: '.ewz_hndle',
            containment: 'parent'
        });
        initcaps_for_1line();
        jQuery( '.color-pick' ).wpColorPicker();

        // note when changes made
        jQuery('#ewz_schemes :input:not(:button)').change(sid, function(){                
            g_changesMade = true;
        });
    }
}

function initcaps_for_1line(){
    jQuery('select[id$="fdata_textrows_"]').change(function(){
        var jthis = jQuery(this);
        var nlines = jthis.val();
        var jrf_row = jQuery(this).closest('tbody').find('tr.reformat');
        if( nlines > 1 ){
            jrf_row.find('select[id$="fdata_sscol_fmt_"]').prop("disabled", true);
            jrf_row.hide();
        } else {
            jrf_row.find('select[id$="fdata_sscol_fmt_"]').prop("disabled", false);
            jrf_row.show();
        }
    });
}

function set_drag_drop(sid){

    var rfields = '#ewz_sortable_rfields' + sid;
    var lfields = '#ewz_layout_fields' + sid;


     jQuery(lfields ).sortable({
         connectWith: rfields,
         receive: function(event, ui ){
            if( ui.item.attr("id").indexOf('_fieldsX') == -1 ) {
                ui.sender.sortable("cancel");
            }
         },
         stop: function(event, ui ){
             if( ( ui.sender == null || ui.sender === undefined ) && ui.item.parent().attr("id").match(/^ewz_layout_fields/)){
                   jQuery(this).sortable("cancel");
             }}
     }).disableSelection();

     jQuery(rfields).sortable({
       connectWith: lfields
      });

    jQuery(rfields).find('.ewz_hndle').disableSelection();
}


/************************ Functions Returning an HTML String ****************************************/

function scheme_header_str(sid, scheme){
    var str='';
    str += '<input type="hidden" name="ewzmode" value="ratingscheme">';
    str += '<input type="hidden" name="rating_scheme_id" id="rating_scheme_id' + sid + '_"value="' + scheme.rating_scheme_id + '">';
    str += '<input type="hidden" name="item_layout_id" id="item_layout_id' + sid + '_"value="' + scheme.item_layout_id + '">';
    str += '<div class="ewz_data ewz_95">';

    if (scheme.n_rating_forms > 0) {
       str += '<div class="ewz_warn">Warning: This scheme is in use by ' + scheme.n_rating_forms + ' rating forms.<br />';
       if(  scheme.n_item_ratings > 0 ){
          str +=    ' containing ' + scheme.n_item_ratings + ' ratings<br />';
       }
       str +=    'Changes made now could cause problems.';
       str += '</div> ';
    }

    str +=    '<p class="ewz_sect_title">Rating Scheme for items uploaded using EntryWizard layout "' + scheme.item_layout.layout_name + '"</p>';
    str +=    '<p class="ewz_sect_title">';
    str +=       'General Information';
    str +=    '</p>';
    str +=    '<table>';
    str +=        '<tr><td><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'name\')">&nbsp;Name for this scheme</td>';
    str +=            '<td>' + textinput_str('f' + sid + '_scheme_name_', 'scheme_name', 60, scheme.scheme_name) + '</td>';
    str +=        '</tr>';
    str +=    '</table>';
    str += '</div>';
    return str;
}

/* This box contains the actual rating-scheme fields */
function field_box_str(sid, scheme){
    var str='';
    str += '<div class="ewz_data ewz_95l">';
    str +=    '<span class="ewz_sect_title"><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'field\')">';
    str +=        'Fields to be displayed in Rating';
    str +=    '</span> &nbsp; &nbsp; &nbsp; ';
    str +=    '( <i>Items affected by restrictions ';
    str +=        '<img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'restr1\')">  )';
    str +=    ' are outlined in red and may not be edited</i>';
    str +=    '<div class="ewz_95">';
    str +=       '<div id="ewz_sortable_rfields' + sid + '">';
    // <br> needed at top and bottom to drag a field to the top or bottom
    str +=          '<br />';
     
    for (var i = 0; i < scheme.nth_field.length; ++i) { 
        str += field_str(sid, scheme.nth_field[i],  scheme.fields[scheme.nth_field[i]]);
    }
    str +=          '<br />';
    str +=       '</div>';
    str +=       '<p>';
    str +=          '<img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'ftype\')">&nbsp;Add another field: &nbsp; ';
    str +=          '<button type="button" class="button-secondary" id="addTextBtn_f' + sid + '_" onClick="add_rfield( this, ' + "'str'" + ')">';
    str +=             'A Text Entry</button>';
    str +=          '<button type="button" class="button-secondary" id="addOptBtn_f' + sid + '_" onClick="add_rfield( this, ' + "'opt'" + ')">';
    str +=             'A Drop-down Selection</button>';
    str +=          '<button type="button" class="button-secondary" id="addChkBtn_f' + sid + '_" onClick="add_rfield( this, ' + "'chk'" + ')">';
    str +=             'A Check Box</button>';
    str +=          '<button type="button" class="button-secondary" id="addLabBtn_f' + sid + '_" onClick="add_rfield( this, ' + "'lab'" + ')">';
    str +=             'A Fixed-Text Label</button>';
    str +=       '</p>';
    str +=    '</div>';
    str += '</div>';

    return str;
}

/* This box contains optional fields for dragging into the actual fields box */
function layout_fields_str(sid,  scheme){
    var str = '';
    var fid, field;

    str += '<div class="ewz_data ewz_95r">';
    str +=    '<span class="ewz_sect_title"><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'field\')">';
    str +=       'Drag items right to display in Rating<br \><br \>Layout Fields:';
    str +=    '</span><br \>';
    str +=    '<div class="ewz_95" id="ewz_layout_fields' + sid + '">';

    str +=          '<br />';
    for ( fid in scheme.item_layout.fields ) { 
        if( scheme.item_layout.fields.hasOwnProperty(fid) ){
            field = scheme.item_layout.fields[fid];
            field.ss_column = -1;
            if( !is_field_displayed( field.field_id, scheme.fields ) ){
                // contents of the postbox to be dragged 
                str +=  field_str( sid, 'X' + newfieldN, field );
                ++newfieldN;
            }
        }
    }
    str +=       '<br \><span class="ewz_sect_title">Extra Data:</span><br \>';
    for (var key in ewzG.display) {
        if (ewzG.display.hasOwnProperty(key)) { 
            var disp = ewzG.display[key];
            disp.key = key;
            if( !is_key_displayed( key, scheme.fields ) ){
                // contents of the postbox to be dragged 
                str +=  field_str( sid, 'X' + newfieldN,  disp  );
                ++newfieldN;
            }
        }
    }

    str +=          '<br />';
    str +=    '</div>';
    str += '</div>';
    return str;
}

/* The restrictions box */
function all_restrictions_str(snum, sid){
    var str = '', restr_count = 0;
    var list = ewzG.schemes[snum].restr_options[-1];
    for( var i in list ){
        if( list.hasOwnProperty(i) ){
            restr_count++;
        }
    }
    if( restr_count > 1 ){
        str += '<div class="ewz_95 ewz_data" id="all_restrs_' + sid + '" style="clear:both">';
        str += '<p class="ewz_sect_title"><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'restr\')">';
        str +=     'Optional Restrictions On Allowed Field Values';
        str += '</p>';
        str += '<div class="ewz_95">';
        str +=    '<div id="ewz_restricts_f' + sid + '_">';
        for ( var restr in  ewzG.schemes[snum].restrictions ) {
            if( ewzG.schemes[snum].restrictions.hasOwnProperty(restr)) {
                str +=  restriction_str(snum, sid, restr);
            }
        }
        str +=    '</div>';
        str +=    '<button type="button" id="add_restr_f' + sid + '_" class="button-secondary" onClick="add_restriction(' + snum + ', this)">';
        str +=         'Add A New Restriction';
        str +=    '</button>';
        str += '</div>';
        str += '</div>';
    }
    return str;
}

/* Extra data for display in spreadsheet */
function spreadsheet_str(sid, scheme){
    var str = '';
    str += '<div class="ewz_95 ewz_data">';
    str +=    '<p class="ewz_sect_title"><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'xtra\')">';
    str +=        'Extra Data For Display in Spreadsheet Only';
    str +=    '</p>';
    str +=    '<div class="ewz_95">';
    str +=       '<div id="spread_f' + sid + '_" class="ewz_box">';
    str +=          '<div class="handlediv" onClick="toggle_ewz_box(this)" title="Click to toggle"><br /></div>';
    str +=          '<h3 id="hspread_f' + sid + '_" class="ewz_hndle"  onClick="toggle_ewz_box(this)">Select Items</h3>';
    str +=          '<div class="inside"  style="display: none;">';
    str +=             '<table class="ewz_field">';

    for (var key in ewzG.display) {
        if (ewzG.display.hasOwnProperty(key)) {
            var kvalue = scheme.extra_cols[key];
            var khead = ewzG.display[key].header;
            var korigin = ewzG.display[key].origin;
            str +=        '<tr><td>' + khead + ' ( <i> ' + korigin + ' data )</i></td>';
            str +=            '<td>';
            str += colinput_str('f' + sid + '_extra_cols_' + key + '_', 'extra_cols[' + key + ']', kvalue, 'ssc' + sid);
            str +=            '</td>';
            str +=        '</tr>';
        }
    }
    str +=             '</table>';
    str +=          '</div>';
    str +=       '</div>';
    str +=    '</div>';
    str += '</div>';

    return str;
}

function display_str( sid, scheme ){ 
    var str = '';
    str += '<div class="ewz_95 ewz_data" id="options_' + sid + '" style="clear:both">';
    str += '<table>';
    str += '<tr><td style="vertical-align: top;"><img alt="" class="ewz_ihelp" src="' +  ewzG.helpIcon + '" onClick="ewz_help(\'idisplay\')">&nbsp;Large Image Display:</td>';
    str +=     '<td colspan="3">';    
    str +=        '<table>';
    str +=          '<tr><td>Max display width of main image </td>';
    str +=              '<td><input type="text" name="settings[maxw]" id="ewz_settings_maxw'  + sid + '" value="' + scheme.settings.maxw + '"></td>';
    str +=              '<td rowspan="2" colspan="2" class="ewz_td_r"> ' + ewzG.imgSizeNote + '</td></tr>';
    str +=          '<tr><td>Max display height of main image</td>';
    str +=              '<td><input type="text" name="settings[maxh]" id="ewz_settings_maxh'  + sid + '" value="' + scheme.settings.maxh + '"></td></tr>';
    str +=          '<tr><td>Background color around image</td>';
    str +=              '<td id="td_bcol' + sid +'"><input class="color-pick" value="' + scheme.settings.bcol + '" type="text" name="settings[bcol]" id="ewz_settings_bcol'  + sid + '">';
    str +=               '</td>';
    str +=           '</tr>';
    str +=          '<tr><td>Text color</td>';
    str +=              '<td id="td_fcol' + sid +'"><input class="color-pick" value="' + scheme.settings.fcol + '" type="text" name="settings[fcol]" id="ewz_settings_fcol'  + sid + '">';
    str +=               '</td>';
    str +=               '<td rowspan="2"  colspan="2" class="ewz_td_r"><img alt="" class="ewz_ihelp" src="' +  ewzG.helpIcon + '" onClick="ewz_help(\'testimg\')"> &nbsp; &nbsp;Path to Test Image (from uploads folder)';
    str +=               '&nbsp;&nbsp;&nbsp;<input type="text" style="width: 250px;" maxlength="200" name="settings[testimg]" id="ewz_settings_testimg' + sid + '" value="' + scheme.settings.testimg + '"></td>';
    str +=           '</tr>';
    str +=          '<tr><td>Minimum amount of background-colour &nbsp; <br>padding above image ( in pixels ) &nbsp; </td>';
    str +=              '<td><input type="text" id="ewz_settings_imgpad'  + sid + '" name="settings[img_pad]" value="' + scheme.settings.img_pad + '"></td>';
    str +=          '</tr>';
    str +=       '</table>';

    str +=    '</td>';
    str += '</tr>';
    str += '<tr><td> &nbsp; </td></tr>';

    str += '<tr><td style="vertical-align: top;"><img alt="" class="ewz_ihelp" src="' +  ewzG.helpIcon + '" onClick="ewz_help(\'rpage\')">&nbsp;Rating Page Display:</td>';
    str +=     '<td colspan="3">';    
    str +=        '<table class="ewz_settings">';
    str +=          '<tr>';
    str +=              '<td><label for="summ' + sid + '">Show count of completed items: &nbsp; <input type="checkbox" id="summ' + sid + '" name="settings[summary]"';
    if( scheme.settings.summary ){ str += ' checked="checked"'; }
    str +=              '></td>';
    str +=              '<td><label for="finish' + sid + '">Show "Finished" button: &nbsp; <input type="checkbox" id="finish' + sid + '" name="settings[finished]" ';
    if( scheme.settings.finished ){ str += ' checked="checked"'; }
    str +=              '></td>';
    str +=              '<td><label for="jhelp' + sid + '">Show "Using the Rating Form" help button: &nbsp; <input type="checkbox" id="jhelp' + sid + '" name="settings[jhelp]" ';
    if( scheme.settings.jhelp ){ str += ' checked="checked"'; }
    str +=              '></td>';
    str +=          '</tr>';
    str +=           '<tr><td colspan="3"> &nbsp; </td></tr>';
    str +=           '<tr><td colspan="3"><i>Leave the settings below at their default values unless the rating page colors are a problem:</i></td></tr>';
    str +=          '<tr><td colspan="2">Main background color for Rating table</td>';
    str +=              '<td><input class="color-pick" value="' + scheme.settings.bg_main + '" type="text" name="settings[bg_main]" id="ewz_settings_bg_main'  + sid + '">';
    str +=               '</td>';
    str +=           '</tr>';
    str +=          '<tr><td colspan="2">Background color for the current row in the Rating table</td>';
    str +=              '<td><input class="color-pick" value="' + scheme.settings.bg_curr + '" type="text" name="settings[bg_curr]" id="ewz_settings_bg_curr'  + sid + '">';
    str +=               '</td>';
    str +=          '</tr>';
    str +=          '<tr><td colspan="2">Border color for a row in the Rating table that has never been saved</td>';
    str +=              '<td><input class="color-pick" value="' + scheme.settings.new_border + '" type="text" name="settings[new_border]" id="ewz_settings_new_border'  + sid + '">';
    str +=               '</td>';
    str +=          '</tr>';
    str +=        '</table>';
    str +=     '</td>';
    str +=  '</tr>';
    str +=  '</table>';
    str +=  '</div>';

    return str;
}



function save_delete_str(sid, scheme, top){
    var str = '';

    str += '<tr>';
    str +=    '<td><button type="button" id="lsub_f' + sid + top + '_" class="button-primary"  onClick="ewz_check_scheme_input(this, ' + ewzG.jsvalid + ')">';
    str +=         'Save Changes to <i>' + scheme.scheme_name + '</i></button>';
    str +=    '</td>';
    str +=    '&nbsp;  &nbsp;  &nbsp;  &nbsp;';
    
    str +=    '<td><button type="button" id="ldel_f' + sid + top + '_" class="button-secondary" ';
    str +=         'onClick="delete_scheme(this, ' + scheme.n_rating_forms + ', ' + scheme.n_item_ratings + ' )">Delete ';
    str +=          '<i>' + scheme.scheme_name + '</i>';
    str +=         '</button>';
    str +=    '</td>';
        str += '               <td class="ewz_right"><button type="button" id="lspr_col_f' + sid + top + '_" class="button-secondary" ';
        str +=                     'onClick="show_spread_columns(' + sid + ",'" + scheme.scheme_name + "')" + '">Show Summary of Assigned Spreadsheet Columns';
        str += '                   </button>';
        str +=                '</td>';
    str += '</tr>';


    return str;
}

/* Returns the html string for a postbox containing a single scheme */
function scheme_str( snum, sid, scheme) {

    var str;
    /*********** Postbox *************/
    str =  '<div id="ewz_admin_schemes_f' + sid + '_" class="metabox-holder ewz_hndle">';

    str +=    '<div id="ewz_ewz_box-scheme_f' + sid + '_" class="ewz_box  closed" style="display: block;" >';
    str +=       '<h3 id="tpg_header_f' + sid + '_"  class="ewz_hndle" onClick="toggle_ewz_box(this)">' + scheme.scheme_name + '</h3>';

    /*********** General *************/
    str +=       '<div class="inside"  style="display: none;">';
    str +=          '<form method="POST" action="" id="sch_form_f' + sid + '_">';
    str +=             '<div class="ewzform">';

    str +=                '<table class="ewz_buttonrow">';
    str +=                    save_delete_str(sid, scheme, 'T');
    str +=                '</table>';

    str +=                 scheme_header_str(sid,  scheme);

    /*********** Fields *************/
    str +=                '<div class="ewz_holder">';
    str +=                   '<div style="float:right; width:78%;">';  
    str +=                       field_box_str(sid, scheme);
    str +=                   '</div>';

    str +=                   '<div  style="float:left;width:20%;">';  
    str +=                       layout_fields_str(sid, scheme);
    str +=                   '</div>';

    str +=                '</div>';

    /*********** Restrictions *************/

    str +=                 all_restrictions_str(snum, sid);

    /*********** Display Options *************/
 
    str +=                 display_str(sid, scheme);

    /*********** Spreadsheet Data *************/
 
    str +=                 spreadsheet_str(sid, scheme);
    /*********** Save/Delete *************/
    str +=                '<div class="ewz_numc"><br /></div>';
    str +=                '<table class="ewz_buttonrow">';
    str +=                    save_delete_str(sid, scheme, '');
    str +=                '</table>';


    str +=                '<div class="ewz_waitmessage"></div>';
    str +=             '</div>';
    str +=          '</form>';
    str +=       '</div>';    // inside
    str +=    '</div>';
    str += '</div>';

    return str;
}


/* set up the variables for generating a single field postbox within the field box */
/* ( also used to generate the contents of the draggable fields in the left-hand column ) */
function field_str( sid, rating_field_id,  fObj ) {
    var fld, fid;
    // if rating_field_id is an integer, the item was already on the db
    var saved = ( parseInt( rating_field_id, 10 ) === rating_field_id );
    var ftype = fObj.field_type;
    if( saved ){
        if( ftype != 'fix' && ftype != 'xtr' ){
            ftype = 'edt';
        }
    } else {  
        if( fObj.isnewinput ){
            ftype = 'edt';
        } else {
            ftype = fObj.field_id ? 'fix' :  'xtr';
        }            
    }

    var rfield;
    if( ftype == 'edt' ){
        rfield = fObj; 
        rfield.data_type = type_data_field_str( sid, fid, fld, fObj.field_type, fObj.fdata );
        rfield.rq = rfield.required;
        if( rfield.field_type == 'rad' ){
            rfield.rq = 'disabled';
        }
    } else {
        if( saved ){
            // an already-saved field, may be 'fix', 'xtr' or a regular field
            rfield = fObj; 
            switch( ftype ){
            case 'xtr':   rfield.data_type = 'Extra data ( ' + fObj.field_header + ' from ' + fObj.fdata.origin  + ' )';
                break;
            case 'fix': rfield.data_type = 'Data uploaded with item ( ' + fObj.field_header + ' )';
                break;
            }
        } else {
            rfield = { rating_field_id: rating_field_id,
                       field_type: ( ( undefined == fObj.field_id ) ? 'xtr' : 'fix' ),
                       field_header: fObj.field_header || fObj.header,
                       field_ident:  fObj.field_ident || fObj.key
                     };
             if( undefined == fObj.field_id ){
                 rfield.fdata = { origin: fObj.origin, dobject: fObj.dobject, dvalue: fObj.header, dkey: fObj.key };
                 rfield.data_type = 'Extra data ( ' + fObj.header + ' from ' + fObj.origin  + ' )';
             } else {
                 rfield.fdata = { field_id: fObj.field_id };
                 rfield.data_type = 'Data uploaded with item ( ' + fObj.field_header + ' )';
             } 
         }         
    }

    rfield.saved = saved;
    rfield.ftype = ftype;
   
    fld = 'fields[' + rfield.rating_field_id + ']';
    fid = 'sch' + sid + '_fields' + rfield.rating_field_id + '_';

    return rating_field_str( sid, fld, fid, rfield );
}

/* return the html for a single field postbox within the field box */
function rating_field_str( sid, fld, fid, rfield )
{
    var str = '';
    str += '<div id="' + fid + 'field_mbox_" class="ewz_box  closed">';
    str +=   '<h3  id="field_title_' + fid + '" class="ewz_hndle" onclick="maybe_toggle_ewz_box(this)">' + rfield.field_header + '</h3>';   
    str +=   '<div class="inside">';
    str +=      '<input type="hidden" name="' + fld + '[rating_field_id]' + '" value="' + rfield.rating_field_id + '">';
    str +=      '<input type="hidden" name="forder[]" value="forder_f' + sid + '_c' + rfield.rating_field_id + '_">';

    if( rfield.field_type == 'xtr' || rfield.field_type == 'fix' ){
        str +=  '<input type="hidden" name="' + fld + '[field_ident]' + '" value="' + rfield.field_ident + '">';   
        str +=  '<input type="hidden" name="' + fld + '[field_type]'  + '" value="' + rfield.field_type + '">';
        str +=  '<input type="hidden" name="' + fld + '[required]'    + '" value=true>';
    }
    if( rfield.field_type == 'xtr' ){
        str +=  '<input type="hidden" name="' + fld + '[fdata][origin]'  + '" value="' + rfield.fdata.origin + '">';
        str +=  '<input type="hidden" name="' + fld + '[fdata][dobject]' + '" value="' + rfield.fdata.dobject + '">';
        str +=  '<input type="hidden" name="' + fld + '[fdata][dvalue]'  + '" value="' + rfield.fdata.dvalue + '">';
        str +=  '<input type="hidden" name="' + fld + '[fdata][dkey]'    + '" value="' + rfield.fdata.dkey + '">';
    }
    if( rfield.field_type == 'fix' ){
        str +=  '<input type="hidden" name="' + fld + '[fdata][field_id]' + '" value="' + rfield.fdata.field_id + '">';
    }

    str +=      '<table  class="ewz_field">';

    str +=          '<tr><td><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'webcol\')">';
    str +=                  '&nbsp;Column Header For Web Page: ';
    str +=              '</td>';
    str +=              '<td>';  
    str +=                  textinput_str(fid + 'field_header_', fld + '[field_header]', 50, rfield.field_header, 'onChange="update_title(this)"' );
    str +=              '</td>';
    if( rfield.ftype == 'edt' ){
       str +=           '<td rowspan="5"  id="special_' + fid + '" > ' + type_data_field_str(sid, fid, fld, rfield.field_type, rfield.fdata );
       str +=           '</td>';
    }
    str +=          '</tr>';
   
    str +=          '<tr><td><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'dtype\')">';
    str +=                  '&nbsp;Data Type: ';
    str +=              '</td>';
    if( rfield.ftype == 'edt' ){
        str +=          '<td class="ewz_shaded">' + type_opt_str(sid, fid, fld, rfield.field_type ) + '</td>';
    } else {
        str +=          '<td>' + rfield.data_type + '</td>'; 
    }
    str +=          '</tr>';

    str +=          '<tr><td><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'ident\')">';
    str +=                     '&nbsp;Field Identifier: ';
    str +=              '</td>';
    str +=              '<td>' + textinput_str(fid + 'field_ident_', fld + '[field_ident]', 15, rfield.field_ident) + '</td>';
    str +=          '</tr>';
 
    str +=          '<tr><td><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'sscol\')">';
    str +=                     '&nbsp;Spreadsheet Column: ';
    str +=              '</td>';
    str +=              '<td>' + colinput_str(fid + 'ss_column_', fld + '[ss_column]', rfield.ss_column, 'ssc' + sid ) + '</td>';
    str +=          '</tr>';

    if( rfield.ftype == 'edt' ){
        var change = ' onChange="req_no_max(this)"';
        str +=      '<tr><td><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'req\')">&nbsp;Required: </td>';
        str +=          '<td>' + checkboxinput_str(fid + 'required_', fld + '[required]', rfield.required, change ) + '</td>';
        str +=      '</tr>';
    }

    str +=          '<tr><td><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'append\')">';
    str +=                      '&nbsp;Append to Previous Column in Webform: ';
    str +=              '</td>';
    str +=              '<td>' + checkboxinput_str(fid + 'append_', fld + '[append]', rfield.append ) + '</td>';
    str +=          '</tr>';

    str +=          '<tr><td><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'divide\')">';
    str +=                      '&nbsp;Divide among Judges: ';
    str +=              '</td>';
    str +=              '<td>' + checkboxinput_str(fid + 'divide_', fld + '[divide]', rfield.divide ) + '</td>';
    str +=          '</tr>';
    
    str +=          '<tr><td><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'secondary\')">';
    str +=                      '&nbsp;Display in a "secondary" view only: ';
    str +=              '</td>';
    str +=              '<td>' + checkboxinput_str(fid + 'is_second_', fld + '[is_second]', rfield.is_second ) + '</td>';
    str +=          '</tr>';
    str +=       '</table>';
    if( rfield.saved || rfield.ftype == 'edt' ){
        str +=   '<div style="text-align:right; padding:10px;"> ';
        str +=      '<button type="button" class="button-secondary" id="del_' + fid + '" onClick="delete_rfield(this)">Delete Field</button>';
        str +=   '</div>';
    }
    str +=    '</div>';  // class = "inside"
    str += '</div>';
    return str;
}

/* contents of a single restriction postbox */
function restriction_str(snum, sid, rnum ) {
    var txt, msgnm, msg, newnum, list;
    var rid = "add_restr_" + sid +  "_R" + rnum + '_';

    list =  ewzG.schemes[snum].restr_options[-1];
    if( ewzG.schemes[snum].restr_messages[rnum ] == undefined ){
        newnum = -1;
        msg = "--- New Restriction ---";
    } else {
        newnum = rnum;
        msg = ewzG.schemes[snum].restr_messages[rnum];
    }
    msgnm = 'restrictions[' + rnum + '][msg]';

    txt  = '<div id="restr_title_f' + sid + '_r' + rnum + '_" class="ewz_subpost ewz_box closed">';
    txt += '   <div class="handlediv" onClick="toggle_ewz_box(this)" title="Click to toggle"><br /></div>';
    txt += '   <h3 id="restr_f' + sid + '_r' + rnum + '_" class="ewz_hndle"  onClick="toggle_ewz_box(this)">' + msg + '</h3>';
    txt += '   <div class="inside"  style="display: none;">';

    txt += '      <div class="ewz_add_restr" id="' + rid + '">';
    txt += '         <table class="ewz_field">';
    txt += '            <tr><td>Forbidden combination:</td>';
     txt += '               <td><i>(starred items may not be changed by the judge)</i></td>';
    txt +=             '</tr>';
    for( var field_id in ewzG.schemes[snum].fields ) {
        if( ewzG.schemes[snum].fields.hasOwnProperty(field_id) ){
            var field = ewzG.schemes[snum].fields[field_id];
            if( field.is_second ){ continue; }
            var is_opt = ( ( field.field_type == 'opt' ) || ( ( field.field_type == 'fix' ) && ( field.field.field_type == 'opt' ) ) );
            var nmstr = 'restrictions[' + rnum + '][' + field_id + ']';
            var mult = is_opt ? ' multiple="multiple"' : '';
            var fixflag = ( field.field_type == 'fix' || field.field_type == 'xtr' || field.field_type == 'lab' ) ? ' *' : '';
            if( undefined !== list[field_id] && list[field_id].length > 0 ){
                txt += '<tr><td class="ewz_leftpad">' + field.field_header  + ":</td>";
                txt += ' <td class="ewz_leftpad">';
                txt += '        <select name="' + nmstr  + '[] " id="f' + sid + '_restrictions_' + rnum + '__' + field_id + '_" ' + mult + ' >';
                txt +=              ewzG.schemes[snum].restr_options[newnum][field_id];                
                txt += '        </select>' + fixflag ;
                txt += '    </td>';
                    
                txt += '</tr>';
            }
        }
    }    
    txt +=             '<tr><td ><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'rmsg\')">&nbsp;Message: </td>';
    txt +=                 '<td>';
    txt +=                 '   <input type="text" name="' + msgnm + '" id="f' + sid + '_restrictions_' + rnum + '__msg_" value="' + msg + '">';
    txt +=                 '</td>';
    txt +=             '</tr>';
    txt +=          '</table>';
    txt +=       '</div>';
    txt +=       '<p style="text-align:right">';
    txt +=          '<button type="button" class="button-secondary" id="x' + rid + '" onClick="delete_restriction(this)">Remove Restriction</button>';
    txt +=       '</p>';
    txt +=    '</div>'; // inside
    txt += '</div>';
    
    return txt;
}

/* button to add a new scheme */
function add_scheme_button_str() {
    var str = '<table class="ewz_control" >';
    str +=    '<tr>';
    str +=       '<td><img alt="" class="ewz_ihelp" src="' +  ewzG.helpIcon + '" onClick="ewz_help(\'lsort\')"></td>';
    str +=       '<td><button  type="button" id="add_scheme" class="button-secondary" onClick="new_rating_scheme()">';
    str +=               'Add a New Scheme';
    str +=            '</button>';
    str +=        '</td>';
    str +=        '<td>for items uploaded using layout: ';
    str +=        '</td>';
    str +=        '<td>';
    str +=             '<select id="ewz_add_for_layout" >';
    str +=                   ewzG.layout_options;
    str +=             '</select>';
    str +=        '</td>';
    str +=     '</tr>';
    str +=     '<tr>';
    str +=       '<td>&nbsp;</td>';
    str +=        '<td>';
    str +=           '<button  type="button" id="copy_scheme" class="button-secondary" onClick="copy_rating_scheme()">';
    str +=               'Add a New Scheme';
    str +=           '</button>';
    str +=        '</td>';
    str +=        '<td>';
    str +=            'with options copied from: ';
    str +=        '</td>';
    str +=        '<td>';
    str +=            '<select id="ewz_add_for_scheme" >';
    str +=                 ewzG.scheme_options;
    str +=             '</select>';
    str +=        '</td>';
    str +=     '</tr>';
    str +=  '</table>';
    str +=        '<button  type="button" class="button-secondary ewz_orderbtn" id="schemes_save2_" onClick="save_scheme_order()">';
    str +=                 'Save Order of Schemes';
    str +=             '</button>';
    return str;
}


/************************ Functions That Actually Do Something  ****************************************/

function save_scheme_order(){
    var sc_nonce = jQuery('input[name="ewznonce"]').val();
    var data = {
        action: 'ewz_save_scheme_order',
        ewznonce:   sc_nonce,
        ewzmode:  'sc_set',
        scorder: new Object()
    };

    if( jQuery('input[id^="rating_scheme_id"][value=""]').length > 0 ){
        ewz_alert("RS002", "Please save your unsaved rating schemes before trying to rearrange them", ++popupN);
        return;
    }
        
    jQuery('input[id^="rating_scheme_id"]').each(function(index){
        data['scorder'][jQuery(this).val()] = index;
    });
    data['action'] = 'ewz_save_scheme_order';
    data['ewznonce'] = sc_nonce;
    var jqxhr = jQuery.post( ajaxurl,
                             data,
                             function (response) {
                                 jQuery("#temp_del").remove();
                                     ewz_alert( 'RS003', response, ++popupN );
                             }
                           ).fail( function(){ ewz_alert( 'RS011', 'Sorry, there was a server error', ++popupN );} );       
}

function req_no_max(checkbox){
    if( jQuery(checkbox).is( ":checked" ) ){
        jQuery(checkbox).closest('table[class="ewz_field"]').find('[id$="_fdata_chkmax_"]').attr("value", 0).prop("disabled", true);
    } else {
       jQuery(checkbox).closest('table[class="ewz_field"]').find('[id$="_fdata_chkmax_"]').prop("disabled", false);
    }
}

/* return true if the field has been selected to show in the field box */
function is_field_displayed( field_id, rating_fields ){
    for( var r in  rating_fields ){
        if( rating_fields.hasOwnProperty( r ) ){
            if( 'fix' == rating_fields[r].field_type && rating_fields[r].fdata['field_id'] == field_id ){
                return true;
            } 
        }
    }
    return false;
}

function is_key_displayed( key, rating_fields ){
    for( var r in  rating_fields ){
        if( rating_fields.hasOwnProperty( r ) ){
            if( 'xtr' == rating_fields[r].field_type &&  rating_fields[r].fdata['dkey'] == key ){
                return true;
            } 
        }
    }
    return false;
}

function new_rating_scheme(){
 
    var layout_id = jQuery('#ewz_add_for_layout').val();
    var scheme =  ewzG.empty_scheme;

    for( var i = 0; i < ewzG.item_layouts.length; ++i ){
       if( ewzG.item_layouts[i].layout_id == layout_id ){
           scheme.item_layout = ewzG.item_layouts[i];
           scheme.item_layout_id = layout_id;
           if( ewzG.item_layouts[i].n_items < 1 ){
               ewz_confirm( "Warning: there are no items uploaded for this layout. Still create the rating scheme?",
                            function(){ do_create_scheme( scheme ); },
                            null,
                            ++popupN);
           } else {
              do_create_scheme( scheme );
           } 
       }
    }
}
function copy_rating_scheme(){
    var fromid  = jQuery('#ewz_add_for_scheme').val();
    var fromnum = jQuery('#ewz_add_for_scheme').prop("selectedIndex");
    var schemes = jQuery('div[id^="ewz_admin_schemes_f"]');
    var to_num = 'X' + newschemeN;
    ++newschemeN;

    var fromstringid = 'ewz_admin_schemes_f' + fromid + '_';
    var tostringid = 'ewz_admin_schemess_f' + to_num + '_';
    var jQnew = jQuery('#' + fromstringid).clone();

    var sscstr = new RegExp('ssc' + fromid + '(\D)', "g");
    var newhtml = jQnew.html().replace(sscstr, 'ssc' + to_num + '$1');
    jQnew.html(newhtml);

    jQnew.attr("id", tostringid);
    var re = new RegExp(fromid);     // NB: no "g", only replace first occurrence
    jQnew.find('[id]').each(function() {
        jQuery(this).attr("id", jQuery(this).attr("id").replace(re, to_num ));
    });
    jQnew.find('input[name="forder[]"]').each(function() {
        jQuery(this).attr("value", jQuery(this).attr("value").replace('_f' + fromid + '_', '_f' + to_num + '_'));
    });
    jQnew.find('label').each(function() {
        jQuery(this).attr("for", jQuery(this).attr("for").replace(fromid, to_num));
    });

    // remove restrictions  -- TODO: copy restrictions, too
    jQnew.find('#ewz_restricts_f' + to_num + '_').empty();

    jQnew.find('input[name="rating_scheme_id"]').attr("value", "");
    jQnew.find('input[name$="[rating_field_id]"]').attr("value", "");
    jQnew.find('select:disabled,textarea:disabled,input:disabled,button:disabled').prop("disabled", false);

    jQnew.find('div[class="ewz_warn"]').html('');
    jQnew.find('h3[id^="tpg_header"]').first().html("New Scheme: <i>To make it permanent, set the options and save</i>");
    jQnew.find('[id$="scheme_name_"]').first().attr("value", "");
    jQnew.find('.ewz_numc').html(ewzG.nonce_string);

    jQnew.find('#add_restr_f' + to_num + '_').after(" &nbsp; <i>Restrictions may not be added until the rating scheme has been saved.</i>");
    jQnew.find('#add_restr_f' + to_num + '_').prop("disabled", true);

    jQnew.find('button[id^="lsub_f"]').text("Save Changes to New Scheme");
    var jdelbtn = jQnew.find('button[id^="ldel_f"]');
    jdelbtn.text("Delete New Scheme");
    jdelbtn.attr('onclick', null);
    jdelbtn.click(function() {
        delete_scheme(this, to_num, 0);
    } );
    jQnew.insertAfter(schemes.last());

    enable_restricted_fields(to_num);

    setup_scheme(schemes.length, to_num );
}

function do_create_scheme( scheme ){

    var snum = ewzG.schemes.length;
    var new_sid = 'X' + newschemeN;
    ++newschemeN;
      
    scheme.scheme_name = '--- New Rating Scheme for ' + scheme.item_layout.layout_name + ' ---';
    ewzG.schemes[snum] = scheme;

    jQuery('#ewz_schemes').append( scheme_str( snum, new_sid, scheme ) );
    set_drag_drop(new_sid);
    jQuery('#ewz_admin_schemes_f' + new_sid + '_').find('.ewz_numc').html(ewzG.nonce_string);
     
    jQuery('#ewz_admin_schemes_f' + new_sid + '_').find( '.color-pick' ).wpColorPicker();

    jQuery('#ewz_admin_schemes_f' + new_sid + '_').find('input[name="ewznonce"]').each(function(index){
        jQuery(this).attr('id', 'ewznonce' + index);
    });
    jQuery('#f' + new_sid + '_scheme_name_').change(function() {
        update_scheme_name(this);
    });
    jQuery('#f' + new_sid + '_scheme_name_').keyup(function() {
        update_scheme_name(this);
    });

    jQuery('#ewz_admin_schemes_f' + new_sid + '_').find('input[name="rating_scheme_id"]').val('');
    jQuery('#ewz_admin_schemes_f' + new_sid + '_').find('input[id$="_scheme_name_"]').change(function(){
        jQuery(this).closest('div[id^="ewz_ewz_box-scheme_f"]').find('h3[id^="tpg_header_f"]').text(jQuery(this).val());
    });
}  


/* toggle the field box only if it is in the main fields box, not in the draggables box */
function maybe_toggle_ewz_box(handle) {
    var jhndle = jQuery(handle);
    var form = jhndle.closest('form').get();
    if( jhndle.closest( 'div[id^="ewz_sortable_rfields"]', form ).length > 0 ){
        jhndle.closest('.ewz_box').children(".inside").toggle();
    } else {
        // needed if dragged back to layout fields area
        jhndle.closest('.ewz_box').children(".inside").hide();
    }
}

/* disable editing of fields appearing in restrictions */
function disable_restricted_fields(sid) {
    jQuery("#ewz_restricts_f" + sid + '_').find('option:selected').each(function() {
        if (jQuery(this).val() !== '~*~') {
            var field_id, optval, jTxt;
            field_id = jQuery(this).parent().attr('id').replace(/^.*__/, '').replace('_', '');
            disable_and_flag(jQuery('#sch' + sid + '_fields' + field_id + '_field_type_'));
            disable_and_flag(jQuery('#sch' + sid + '_fields' + field_id + '_is_second_'));
            disable_and_flag(jQuery('#del_sch' + sid + '_fields' + field_id + '_'));
            optval = jQuery(this).val();
            switch (optval) {
                case "~+~":
                case "~-~":
                    disable_and_flag(jQuery('#sch' + sid + '_fields' + field_id + '_required_'));
                    break;
                default:
                    jTxt = jQuery("input[id^='sch" + sid + "_fields" + field_id + "_fdata_'][id$='_value_'][value='" + optval + "']");
                    disable_and_flag(jTxt.parent().siblings().last().find("button"));
                    disable_and_flag(jTxt);
                    break;
            }
        }

    });
}

/* disable "required" checkbox if the field type is checkbox, radio or label */
function disable_required_for_chk_or_rad(sid){
    jQuery("#ewz_sortable_rfields" + sid ).find('select[id$="_field_type_"]').each(function(){
        var jthis = jQuery(this);
        var type = jthis.val();
        if( type == 'chk' || type == 'rad' ){
            jthis.closest('table[class="ewz_field"]').find('input[id$="_required_"]').prop("checked", false).prop("disabled", true);
        }
        if( type == 'lab' ){
            jthis.closest('table[class="ewz_field"]').find('input[id$="_required_"]').prop("checked", true).prop("disabled", true);
        }
    });
}

/* enable fields disabled via  disable_restricted_fields*/
function enable_restricted_fields(sid) {
    var content;
    jQuery("#sch_form_f0_").find(".ewz_disabled").each(function() {
        jQuery(this).find('input,button').prop("disabled", false);
        content = jQuery(this).html();
        jQuery(this).replaceWith(content);
    });
}


/* Set up some onChange functions for a scheme */
function setup_scheme(snum,  sid) {

    // set postbox header from title
    jQuery('#f' + sid + '_scheme_name_').keyup(function() {
        update_scheme_name(this);
    });
    jQuery('#f' + sid + '_scheme_name_').change(function() {
        update_scheme_name(this);
    });

    // disable ss_column values that are in use already
    disable_ss_options('.ssc' + sid);
    // now run the function defined above
    jQuery("#f" + sid + "_max_num_items_").change();

    var jScheme = jQuery('#ewz_admin_schemes_f' + sid + '_');
    // add the nonce
    jScheme.find('.ewz_numc').html(ewzG.nonce_string);
    jScheme.find('input[name="ewznonce"]').each(function(index) {
        jQuery(this).attr('id', 'ewznonce' + sid + index);
    });

    // disable fields appearing in restrictions
    disable_restricted_fields(sid);

    // disable required fields  radio buttons
    disable_required_for_chk_or_rad(sid);

   // add a div for the spreadsheet columns popup
    jScheme.append('<div id="spread_cols_display' + sid + '" class="wp-dialog" ></div>');

}



function add_restriction(snum, button) {
    var jRestrictDiv = jQuery(button).parent().find('div[id^="ewz_restricts_f"]'),
    sid = get_scheme_num(button);
    var nrestrs = jQuery(button).closest('div[id^="all_restrs_"]').find('div[id^="restr_title_f"]').length;
    jRestrictDiv.append(restriction_str(snum, sid, nrestrs));
    jRestrictDiv.find(':input:not(:button)').change(function(){
        if(!g_changesMade){g_changesMade=true;}
    });
}

function delete_restriction(button) {
    var sid = get_scheme_num(button);
    // delete the restriction
    jQuery(button).closest('.ewz_box').remove();
    enable_restricted_fields(sid);
    // there may be other restrictions, enable clears everything
    disable_restricted_fields(sid);
}

/* Called on change of scheme name, to change the ewz_box title */
function update_scheme_name(textfield) {
    var jtext, jscheme;

    jtext = jQuery(textfield);
    jscheme = jtext.closest(jQuery('div[id^="ewz_ewz_box-scheme_f"]'));
    jscheme.find('h3[id^="tpg_header_f"]').text(jtext.val());
    jscheme.find('button[id^="lsub_f"] i').text(jtext.val());
    jscheme.find('button[id^="ldel_f"] i').text(jtext.val());
}


/* Adds a new field to the current scheme, of type defined by field_type */
function add_rfield(add_field_btn, field_type) {
    var form = jQuery(add_field_btn).closest('form[id^="sch_form"]');
  
    var newid = 'X' + newfieldN;
    var newfid = form.attr("id").replace('sch_form_', '') + 'fields' + newid + '_';
    var sid = form.attr("id").replace('sch_form_f', '').replace('_', '');

    var fdata = {};

    var data = {};
    fdata = get_empty_field_str( field_type ); 
    data.fdata = fdata;
    data.field_ident = '';
    data.field_header = '--- New Field ---';
    data.rating_field_id = newid;
    data.field_type = field_type;
    data.required = 0;
    data.ss_column = '-1';
    data.isnewinput = 1;

    form.find('div[id^="ewz_sortable"]').append( jQuery(field_str(sid, newid, data)) );
    jQuery('h3[id="field_title_' + newid + '"]').html("-- New Field --");
    
    disable_required_for_chk_or_rad(sid);

    var jNewBox = jQuery('#sch' + sid + '_fields' + newid + '_field_mbox_');
    // fire a change event on the spreadsheet column select boxes to disable used columns
    jNewBox.find('select[id$="ss_column_"]').change();

    jNewBox.find(':input:not(:button)').change(function(){
        if(!g_changesMade){g_changesMade=true;}
    });
    ++newfieldN;
}
  

/* Just deletes the scheme from the current page, does not change what is stored on the server */
function delete_js_scheme( id, thediv, lname ) {
    var re, index;
    thediv.remove();
}

/* Just deletes the field on the current page, does not change what is stored on the server */
function delete_js_field( rating_scheme_id, rating_field_id, jdiv ) {
    var index = js_find_by_key(ewzG.schemes, 'rating_scheme_id', rating_scheme_id);
    jdiv.find('select[onchange^="disable_ss_options"]').prop("selectedIndex", 0);
    delete ewzG.schemes[index].fields[rating_field_id];
    jdiv.remove();
    jQuery('#ewz_addscheme').html(ewzG.schemes_options);
    jQuery('select[id*="restrictions"][id$="__' + rating_field_id + '_"]').remove();
}

/* First checks for attached webforms or items */
/* Actually deletes the scheme on the server via ajax. If successful, calls delete_js_scheme to delete it on the current page. */
function delete_scheme(button, nratingforms, nratings) {
    var jbutton = jQuery(button);
    var thediv = jbutton.closest('div[id^="ewz_admin_schemes_f"]');
    var id = thediv.find('input[name="rating_scheme_id"]').first().attr("value");
    var lname = jbutton.closest('div[id^="ewz_ewz_box-scheme_f"]').find('h3[id^="tpg_header_f"]').text();
    lname = lname.replace(/To make it permanent.*$/, '');
    if (id === undefined || null === id || '' == id ) {
        delete_js_scheme(id, thediv, lname);
        return;
    } 
    if (nratings > 0) {
        ewz_alert('RS004', ewzG.errmsg.deletehasratings, ++popupN);
        return;
    }
    ewz_confirm( ewzG.errmsg.deleteconfirm,
                 function(){ 
                     do_delete_scheme( thediv, jbutton, id, lname  );
                 },
                 null,
                 ++popupN);    
}

function  do_delete_scheme( thediv, jbutton, id, lname ){
    var del_nonce = thediv.find('input[name="ewznonce"]').val();
    jbutton.after('<span id="temp_load" style="text-align:left"> &nbsp; <img alt="" src="' + ewzG.load_gif + '"/></span>');
    var jqxhr = jQuery.post(ajaxurl,
                        {
                            action: 'ewz_del_scheme',
                            rating_scheme_id: id,
                            ewznonce: del_nonce
                        },
                        function(response) {
                            jQuery("#temp_load").remove();
                            if ('1' == response) {
                                delete_js_scheme(id, thediv, lname);
                            } else {
                                ewz_alert('RS005', response, ++popupN);
                            }
                        }
                       ).fail( function(){ ewz_alert( 'RS012', 'Sorry, there was a server error', ++popupN );} );
}



/* Actually deletes the field on the server via ajax. If successful, deletes it on the current page. */
function delete_rfield(del_field_btn) {
    var jbutton, fname, jfield_div, jform_div, rating_field_id, scheme_id, del_nonce, jqxhr;

    jbutton = jQuery(del_field_btn);
    jfield_div = jbutton.closest('div[id$="field_mbox_"]');
    rating_field_id = jfield_div.find('input[name^="fields"]').filter(":hidden").val();
    if ( rating_field_id === undefined || '' == rating_field_id || '0' == rating_field_id || rating_field_id.match(/^X/)) {
        jfield_div.find('select[onchange^="disable_ss_options"]').prop("selectedIndex", 0);
        jfield_div.find('select[onchange^="disable_ss_options"]').change();
        jfield_div.remove();
    } else {
        fname = jfield_div.find('h3[id^="field_title"]').text();
        ewz_confirm( "Really delete the '" + fname + "' field?",
                     function(){ 
                         do_delete_field(jbutton, jfield_div, rating_field_id );
                     },
                     null, ++popupN );
    }
}

function do_delete_field( jbutton, jfield_div, rating_field_id ){
      var jform_div = jbutton.closest('form[id^="sch_form_f"]');
      var scheme_id = jform_div.find('input[name="rating_scheme_id"]').filter(":hidden").val();
      var del_nonce = jform_div.find('input[name="ewznonce"]').val();
      jbutton.after('<span id="temp_load" style="text-align:left"> &nbsp; <img alt="" src="' + ewzG.load_gif + '"/></span>');
      var jqxhr = jQuery.post(ajaxurl,
              {
                  action: 'ewz_del_rating_field',
                  rating_field_id: rating_field_id,
                  scheme_id: scheme_id,
                  ewznonce: del_nonce
              },
      function(response) {
          jQuery("#temp_load").remove();
          if ('1' == response) {
              delete_js_field(scheme_id, rating_field_id, jfield_div);
          } else {
              ewz_alert('RS006', response, ++popupN);
          }
      }
      ).fail( function(){ ewz_alert( 'RS013', 'Sorry, there was a server error', ++popupN );} );
}


function get_scheme_num(element) {
    var id = jQuery(element).closest('div[id^="ewz_admin_schemes"]').attr('id');
    return id.replace('ewz_admin_schemes_f','').replace('_','');
}


var ewz_errors_done;  // global
function ewz_check_scheme_input(button, do_check) {
    var except1, except2;
    var ok = true;

    // stop the "do you really want to leave" message that would otherwise show when the "save changes" button is clicked
    g_doingSubmit = true;  

    ewz_errors_done = [];

    var jform = jQuery(button).closest('form');
    var sid = jform.attr("id").replace('sch_form_f', '').replace('_', '');
    jQuery('#lsub_f' + sid + '_').prop("disabled", true);

    if (do_check) {
        try {
            // remove leading and trailing spaces from all inputs
            // NB: need index arg so value is correctly assigned
            jform.find('input').val(function(index, value) {
                return value.replace(/ +$/, '').replace(/^ +/, '');
            });
            // no scheme name
            if (!jform.find('input[id$="_scheme_name_"]').val()) {
                err_alert( sid, ewzG.errmsg.schemename);
                ok = false;
            }

            // no fields
            if (!jform.find('div[id$="field_mbox_"]')) {
                err_alert( sid, ewzG.errmsg.nofields);
                ok = false;
            }
            /* cant return from function from inside filter, so use 'ok' flag */
            // missing column header
            jform.find('input[id$="field_header_"]').filter(function() {
                return  ('' == jQuery(this).val().replace(/^\s+|\s+$/g, ''));
            }).each(function() {
                err_alert( sid, ewzG.errmsg.colhead);
                ok = false;
            });
            // missing identifier
            jform.find('input[id$="field_ident_"]').filter(function() {
                return !jQuery(this).val().replace(/^\s+|\s+$/g, '').match(/^[a-z][a-z0-9_\-]+$/i);
            }).each(function() {
                err_alert( sid, ewzG.errmsg.ident);
                ok = false;
            });
            // missing option label or value
            jform.find('input[id$="_label_"]').each(function() {
                var lab = jQuery(this).val();
                if (!lab) {
                    err_alert( sid, ewzG.errmsg.optlabel);
                    ok = false;
                    return;
                }
                if (lab.match(/[^A-Za-z0-9_\.\- ]/)) {
                    err_alert( sid, ewzG.errmsg.option);
                    ok = false;
                }
            });
            // option list must contain a valid option
            jform.find('select[id$="_field_type_"]').each(function(){
                if( jQuery(this).val() === 'opt'){
                    var jfield_table = jQuery(this).closest('table[class="ewz_field"]');
                    var header = jfield_table.find('input[id$="_field_header_"]').val();
                    if(jfield_table.find('table[id^="data_fields_"]').find('tr[id$="_row_"]').size() < 1 ){
                        err_alert( sid,  header + ': ' +  ewzG.errmsg.optioncount );
                        ok = false;
                    }
                }
            });
            // option must have a valid value
            jform.find('input[id$="_value_"]').each(function() {
                var oval = jQuery(this).val();
                if (!oval) {
                    err_alert( sid, ewzG.errmsg.optvalue);
                    ok = false;
                    return;
                }
                if (oval.match(/[^A-Za-z0-9_\.\-]/)) {
                    err_alert( sid, ewzG.errmsg.option);
                    ok = false;
                }
            });
           // invalid checkbox label
            jform.find('input[id$="_chklabel_"]').each(function() {
                var lab = jQuery(this).val();
                if (lab.match(/[^A-Za-z0-9_\.\- ]/)) {
                    err_alert( sid, ewzG.errmsg.chklabel);
                    ok = false;
                }
            });
           // invalid fixed text label value
            jform.find('input[id$="_textlabel_"]').each(function() {
                var lab = jQuery(this).val();
                if( !lab ){
                    err_alert( sid, ewzG.errmsg.labvalue);
                    ok = false;
                    return;
                }
                if (lab.match(/[^A-Za-z0-9_\.\- ]/)) {
                    err_alert( sid, ewzG.errmsg.textlabel);
                    ok = false;
                }
            });
            // restriction must have a message
            jform.find('input[id$="__msg_"]').each(function() {
                if (!jQuery(this).val()) {
                    err_alert( sid, ewzG.errmsg.restrmsg);
                    ok = false;
                }
            });
            // text input must have maxchars
            jform.find('select[id$="_maxstringchars_"]').each(function() {
                if (!jQuery(this).val().replace(/^\s+|\s+$/g, '')) {
                    err_alert( sid, ewzG.errmsg.maxnumchar);
                    ok = false;
                }
            });
            // Image specs must be digits only
            jform.find('input[id^="ewz_settings_maxh"]').each(function() {
                if( !( jQuery(this).val().match(/^[1-9][0-9][0-9][0-9]?$/)  ) ) {
                    err_alert( sid, ewzG.errmsg.maximgh);
                    ok = false;
                }
            });
            jform.find('input[id^="ewz_settings_maxw"]').each(function() {
                if( !( jQuery(this).val().match(/^[1-9][0-9][0-9][0-9]?$/)  ) ) {
                    err_alert( sid, ewzG.errmsg.maximgw);
                    ok = false;
                }
            });
            jform.find('input[id^="ewz_settings_imgpad"]').each(function() {
                if( !( jQuery(this).val().match(/^[1-9][0-9][0-9]?$/) ) ) {
                    err_alert( sid, ewzG.errmsg.minpad);
                    ok = false;
                }
            });

            // warnings re ineffective restrictions
            var spec_ed1 = jQuery('#ewz_restricts_f' + sid + '_' ).find('td:not(:contains(" *  "))').find('select[name^="restrictions"]').html();
            var spec_ed2 = jQuery('#ewz_restricts_f' + sid + '_' ).find('td:not(:contains(" *  "))').find('select[name^="restrictions"]').find('option:eq(0)').not(":selected").html();
            if ( ( undefined !== spec_ed1 && spec_ed1.length > 0 )  && ( undefined == spec_ed2 || spec_ed2.length < 1 ) ) {
                err_alert(sid, ewzG.errmsg.one_spec);
                ok = false;
            }   
            jform.find('div[id^="add_restr_"]').each(function() {
                var spec = jQuery(this).find('select[name^="restrictions"]').find('option:eq(0)').not(":selected");
                if (undefined !== spec && spec.length < 1) {
                   err_alert( sid, ewzG.errmsg.all_any );      
                    ok = false;
                } else if (undefined !== spec && spec.length < 2) {
                    err_alert( sid, ewzG.errmsg.one_any );
                    ok = false;
                }
            });
 
        } catch (except1) {
            jQuery('#lsub_f' + sid + '_').prop("disabled", false);
            ewz_alert( "RS007", "Sorry, there was an unexpected error: " + except1.message, ++popupN);
            return false;
        }       
    } 

    if (ok || !do_check) {
        // appending non-secondary to secondary
        // could be legit, likely an error
        if( jform.find('input[id$="_append_"]:checked').closest('div[id$="_field_mbox_"]').prev().find('input[id$="_is_second_"]:checked').length > 0 ){
            ewz_confirm( ewzG.errmsg.append2, 
                         function(){
                             do_submit(jform, sid);
                         },                          
                         function(){
                             jQuery('#lsub_f' + sid + '_').prop("disabled", false);                              
                         },
                         ++popupN);
        } else {
            do_submit(jform, sid);
        }
    }

    if(!ok){
        for (var key in ewz_errors_done ) {  
            if( ewz_errors_done.hasOwnProperty( key ) ){
                ewz_alert( 'RS008', key, ++popupN );
            }
        }
        jQuery('#lsub_f' + sid + '_').prop("disabled", false); 
        g_doingSubmit = false;
        return false;
    }
    g_doingSubmit = false;
    return false;       // must do this to prevent re-sending via regular submit
}

function do_submit(jform,sid ){
    var except2;
    g_changesMade = false;
    try {
        jform.find('div[class="ewz_waitmessage"]').html('Processing, please wait ... <img alt="Please Wait" src="' + ewzG.load_gif + '"/>');

        // enable all the disabled stuff so right data gets sent
        jform.find('select:disabled,input:disabled,textarea:disabled,button:disabled').prop("disabled", false);
        jform.find('input[id$="optrow_"]').prop("disabled", true);  // we DO want this disabled
        jQuery('#ewz_layout_fields' + sid).find(":input").prop("disabled", true);  // and this

        jform.append('<input type="hidden" name="action" value="ewz_scheme_changes" />');
        jQuery.post(ajaxurl,
                    jform.serialize(),
                    function(response) {
                        if ('1' == response) {
                            document.location.reload(true);
                        } else {
                            ewz_alert( 'RS009', response, ++popupN);
                            disable_ss_options('.ssc' + sid);
                            disable_restricted_fields(sid);
                        }
                    }).fail( function(){ ewz_alert( 'RS014', 'Sorry, there was a server error', ++popupN );} );

    } catch (except2) {
        ewz_alert("RS010", "Sorry, there was an unexpected error: " + except2.message, ++popupN);
        return true;   // should make regular submit work
    }
}


function err_alert( schemenum, msg )
{
    if( !ewz_errors_done[msg] )
    { 
        ewz_errors_done[msg] = true;    
    }
}


