"use strict";
/* ewzalrt RF012 */
jQuery(document).ready(function() {
    init_ewz_ratingforms();
});
var ewzG, ewzG1;
var popupN;  // used for id of popup 

/************************ The OnLoad Function  ****************************************/
/* called on load                                   */
/* generates the whole page from the ewzG structure */
function init_ewz_ratingforms(){
    var i, jthis;
    fixConsole();
    popupN = 0;

    // ewzG is null if not logged in
    if( null !== ewzG1 ){
        ewzG = ewzG1.gvar;
        //console.log(ewzG);
        if( ewzG.message ){
            ewz_alert(  'RF001', ewzG.message.replace(/~/g,"\n"), ++popupN  );
        }
        // make the ratingform postboxes sortable
        jQuery('#ewz_management').sortable({
            containment: 'parent',
            items: "> div",
            distance: 5
        });
        for( i = 0; i < ewzG.rating_forms.length; ++i){ 
            var rf_id = ewzG.rating_forms[i].rating_form_id.toString();
            // generate the form
            jQuery('#ewz_management').append(ewz_management(i, rf_id, ewzG.rating_forms[i], false));

            // add onChange function  
            jQuery('#ewz_rform_title_ev' + rf_id + '_').change(function(){
                jthis = jQuery(this);
                jthis.closest('div[id^="ewz-postbox-rating_form_ev"]').find('span[id^="rf_title_"]').text(jthis.val());
            });

            // add the nonce
            jQuery('.ewz_numc').html(ewzG.nonce_string);
            jQuery('input[name="ewznonce"]').each(function(index){
                jQuery(this).attr('id', 'ewznonce'+index);
            });
        }
        jQuery('#ewz_management').append('<br>');
        jQuery('#ewz_management').after(rating_form_button_str());

   }
}

/* Returns the html string for a postbox containing a single rating form */
function ewz_management( i, rf_id, eObj, is_new ){
    var formstatus, isclosed, str = '';
    if(!eObj){
        eObj= {};
    }
    
    formstatus = '<span  style="float:right">' + (eObj.rating_open ? 'Open' :  'Closed')  + '</span>';
    isclosed = ' closed';
    if( eObj.rating_form_id == ewzG.openform_id ){
        isclosed = '';
    }
    str +='<div id="ewz_admin_ratingforms_ev' + rf_id + '_" class="metabox-holder ewz_hndle">';
    str +=   '<div id="ewz-postbox-rating_form_ev' + rf_id + '_" class="ewz_box ' + isclosed + '">';
    str +=       '<h3  class="ewz_hndle" onClick="toggle_ewz_box(this)" id="tpg_header_ev' + rf_id + '_"  >';
    str +=           '<span id="rf_title_' + rf_id + '">' + eObj.rating_form_title + '</span>' + formstatus;
    str +=       '</h3>';
    str +=       '<div class="inside">';

    str +=          '<div class="ewz_formsetup">';
    str +=             '<div class="ewz_data">';
    str +=               '<h4><u>Rating Scheme:</u> &nbsp; ' + eObj.rating_scheme.scheme_name;
    str +=                  '&nbsp; &nbsp; &nbsp; &nbsp; <u>For Images Uploaded Using Layout:</u> &nbsp; ' + eObj.rating_scheme.item_layout.layout_name + ' </h4>';

    str += rating_form_data_str(i, rf_id, eObj );
    str +=             '</div>';  


    if( !is_new ){
        str +=             '<div class="ewz_data">';
        str += data_management_str( rf_id, eObj );
        str +=             '</div>';   // end of ewz_data
    }

    str +=          '</div>';     // end of ewz_formsetup
    str +=       '</div>';        // end of inside
    str +=    '</div>';           // ewz-postbox-rating_form_ev
    str += '</div>';              // ewz_admin_ratingforms_ev
    return str;
}

function data_management_str( rf_id, eObj ){
    var str = '';
    str += '<h4><img alt="" class="ewz_ihelp" src="' +  ewzG.helpIcon + '" onClick="ewz_help(\'data\')"> &nbsp; Data Management</h4>';
    str += 'Counts below may not always reflect the most recent changes.';
    str += 'Click "Recalculate" to refresh the counts.<br> ';

    str += '<div class="ewz_status" id="ewz_status_' + eObj.rating_form_id  + '">';
    str +=        eObj.status_table;
    str += '</div>';

    str += '<button class="recalc" onClick=\'recalculate(this, ' + eObj.rating_form_id + ')\'>Recalculate</button>';
    str += '<div class="ewz_80"><p>For the "one row per item" spreadsheet format: </p>';
    str += '<p class="ewz_tab">If there is more than one judge, extra blank columns need to be allowed for in the rating scheme. ';
    str += '<br><u>Each column assigned to an item the judges fill out must be followed by N-1 blank columns</u>, where N is '; 
    str += 'the total number of judges.';
    str += '<br>Failure to do this will result in data with an invalid column assignment being surrounded by "*[ ... ]*" in the spreadsheet. </p></div>';
    
    if( eObj.can_download ){
        str += '<form method="POST" action="" id="rdata_form_ev' + rf_id + '_">';
        str +=    '<div class="ewzform">';
        str +=       '<input type="hidden" name="ewzmode" value="rspread">';
        str +=       '<input type="hidden" name="rating_form_id" value="' + eObj.rating_form_id + '">';
        str +=        '<div class="ewz_numc"></div>';  
        str +=        '<img alt="" class="ewz_ihelp" src="' +  ewzG.helpIcon + '" onClick="ewz_help(\'download\')"> &nbsp; ';
        str +=       '<button type="submit" name="ss_style" value="I" id="spreadI_' + rf_id + '" class="button-secondary">';
        str +=                'Download Spreadsheet ( 1 row per item )</button>';
        str +=        ' &nbsp; &nbsp; ';

        str +=       '<button type="submit" name="ss_style" value="R" id="spreadR_' + rf_id + '" class="button-secondary">';
        str +=                'Download Spreadsheet ( 1 row per rating )</button>';
        str +=    '</div>';

        str +=    '<div class="ewz_numc"></div>';
        str += '</form>';
    }
    return str;
}



/* Return the html string for the editable data */
function rating_form_data_str(i, rf_id, eObj) {
    var str = '';
    var rs_id =  eObj.rating_scheme_id;

    str += '<form method="post" action="" id="rat_form_ev' + rf_id + '_">'; 
    str +=    '<div class="ewzform">';
    str +=       '<input type="hidden" name="rating_form_id" id="edit_rfid_' + rf_id + '" value="' + rf_id + '">';
    str +=       '<input type="hidden" name="rating_scheme_id" id="edit_rsid_' + rf_id + '" value="' + rs_id + '">';
    str +=       '<input type="hidden" name="ewzmode" value="ratingform">';


    str +=       '<table class="ewz_rating_padded">';
    str +=          '<tr><td><img alt="" class="ewz_ihelp" src="' +  ewzG.helpIcon + '" onClick="ewz_help(\'title\')">&nbsp;Title:</td>';
    str +=             '<td colspan="2">' + textinput_str('ewz_rform_title_ev' + rf_id + '_', 'rating_form_title', 50, eObj.rating_form_title) + '</td>';
    str +=             '<td></td>';
    str +=          '</tr>';
    str +=          '<tr><td><img alt="" class="ewz_ihelp" src="' +  ewzG.helpIcon + '" onClick="ewz_help(\'ident\')">&nbsp;Identifier:</td>';
    str +=             '<td>' + textinput_str('rating_form_ident_ev' + rf_id + '_', 'rating_form_ident', 15, eObj.rating_form_ident);
    str +=             '</td>';
    str +=             '<td></td>';
    str +=          '</tr>';

    str += item_selection_row( i, rf_id, eObj );

    str +=         '<tr><td><img alt="" class="ewz_ihelp" src="' +  ewzG.helpIcon + '" onClick="ewz_help(\'access\')">&nbsp;Access Control<br>(Judges):</td>';
    str +=             '<td rowspan="2" colspan="2"><select multiple="multiple" name="judges[]" id="judges' + rf_id + '"  size="8" >';
    str +=                    eObj.userlist;
    str +=                '</select>';
    str +=            '</td><td colspan="3"><i>To be listed here, judges need to be assigned the role "EntryWizard Judge",<br> which is automatically created by EntryWizard</i></td>';
    str +=         '</tr>';
    str +=         '<tr><td></td><td colspan="3"><span id="divide_warn' + rf_id + '"><i>' + eObj.divide_warn_msg + '</i></span></td></tr>';
    str +=         '<tr><td><img alt="" class="ewz_ihelp" src="' +  ewzG.helpIcon + '" onClick="ewz_help(\'open\')">&nbsp;Open Rating:</td>';
    str +=             '<td>' + checkboxinput_str('rating_open' + rf_id + '_', 'rating_open', eObj.rating_open ) + '</td>';
    str +=         '</tr>';

    str +=      '</table>';
    str +=      '<div class="ewz_numc"></div>';
    str +=      '<p>';
    str +=      '<button id="rat_form_wf' + rf_id + '_" type="button" class="button-primary" ';
    str +=              'onClick="ewz_check_rform_input( '  + "'" + rf_id +  "'" + ', ' + i + ', ' + ewzG.jsvalid  + ' )" >';

    str +=      'Save Changes</button> &nbsp;  &nbsp;  &nbsp;  &nbsp; ';
    str +=     '<button type="button" id="rfdel_' + rf_id + '_" class="button-secondary"';
    str +=             'onClick="delete_rating_form( this, ' + eObj.ratingcount + ')">Delete Rating Form</button>';
    str +=      '</p>';
    str +=  '</div>';
    str += '</form>';
    return str;

}

function item_selection_row( i, rf_id, eObj ){
    var own = false, newinfo = '', str = '';
    if( eObj.item_selection !== undefined ){
        own = eObj.item_selection.own;
    }
   if( String(rf_id).substring( 0, 1) == 'X' ){
        newinfo = '<br><i>More options will be available<br>after saving the rating form</i>';
    }
    str += '<tr id="item_sel' + rf_id + '">';
    str +=      '<td><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'iselect\')">&nbsp;Item Selection:' + newinfo + '</td>';

    if( ewzG.rating_forms[i] !== undefined ){
        str +=  '<td>Webform(s):<br>' + webform_selection_string( eObj.rating_scheme.item_layout_id, i, rf_id ) + '</td>';
        str +=  '<td>Shuffle Item Order:<br>' +  checkboxinput_str('shuffle_' + rf_id + '_', 'shuffle', eObj.shuffle );  + '</td>';
    }

    str +=     '<td>User&#39;s Own <br>Images Only:<br>';
    str +=          checkboxinput_str("own" + rf_id, "own", own );
    str +=     '</td>';

    if( eObj.hasOwnProperty('field_options' )){
        str +=  '<td>';
        str +=   field_selection_str( eObj );
        str +=  '</td>';
    }
    str += '</tr>';
    return str;
}


/* Return the html string for the field boxes for selecting items */
function field_selection_str( eObj ){
    var field_id1, field_id2, fid,
        num = 0, str ='';
    for( fid in eObj.field_options ){
        if(eObj.field_options.hasOwnProperty(fid) && eObj.field_options[fid]){
            ++num;
        }
    }
    if( num > 0 ){   
        str +=  '<TABLE class="ewz_compact"><TBODY>';
        str +=     '<TR>';

        // header row
        for( field_id1 in eObj.field_options ){
           if(eObj.field_options.hasOwnProperty(field_id1)){
               str += '<TH>';
               if( eObj.field_options[field_id1] ){
                   str +=   eObj.field_names[field_id1];
               }
               str += '</TH>';
           }
        }
        str +=    '</TR>';
        str +=    '<TR>';

        // selection boxes
        for( field_id2 in eObj.field_options ){
           if(eObj.field_options.hasOwnProperty(field_id2)){
               str += '<TD>';
               if( eObj.field_options[field_id2] ){
                   str += '<select multiple="multiple"  name="fopt[' + field_id2 +'][]"  id="l'+ eObj.rating_form_id + 'f' + field_id2 + '_opt_">';
                   str +=      eObj.field_options[field_id2];
                   str += '</select>';
               }
               str += '</TD>';
           }
        }
        str +=   '</TR>';
        str += '</TBODY></TABLE>';
    }
    return str;
}

function rating_form_button_str(){
    var str = '';
    str += '<div class="clear alignleft">';
    str +=    '<img alt="" class="ewz_ihelp" src="' +  ewzG.helpIcon + '" onClick="ewz_help(\'rfsort\')"> &nbsp;';
    str +=    '<button  type="button" class="button-secondary" id="rating_form_add_" onClick="add_new_rating_form()">Add a New Rating Form</button> ';
    str +=    'for rating-scheme ';
    str +=    '<select id="rating_scheme_id">' +  ewzG.schemes_list + '</select> <span class="ewz_space50"></span>';
    str +=    '<button type="button" class="button-secondary" id="rating_forms_save2_" onClick="save_ratingform_order()">Save Order of Rating Forms</button> ';
    str += '</div> ';
    return str;
}


function del_judge_ratings( judge_id, rf_id ){
    var judge_name = jQuery('#jname_' + rf_id + '_' + judge_id).text();
    ewz_confirm("Really remove ALL ratings from this form for judge " + judge_name + "?  This action cannot be undone.",
               function(){ 
                  do_del_judge_ratings( judge_id, rf_id );
               }, 
               null, ++popupN );
}      

function do_del_judge_ratings( judge_id, rf_id ){
    var the_nonce = jQuery('input[name="ewznonce"]').val();
    var jqxhr = jQuery.post( ewzG.ajaxurl,
                             {
                               action:  'ewz_del_judge_ratings',
                               judge_id: judge_id,
                               rating_form_id: rf_id,
                               ewznonce: the_nonce
                             },
                             function (response) {
                                 jQuery('#ewz_status_' + rf_id).html( response );
                              }
                            ).fail( function(){ ewz_alert( 'RF008', 'Sorry, there was a server error', ++popupN );} );
}      

function judge_reopen( judge_id, rf_id ){ 
    var judge_name = jQuery('#jname_' + rf_id + '_' + judge_id).text();
    ewz_confirm("Really allow judge " + judge_name + " to edit ratings again?",
                function(){ 
                    do_judge_reopen( judge_id, rf_id );
                },
                null, ++popupN );
}

function do_judge_reopen( judge_id, rf_id ){ 
    var the_nonce = jQuery('input[name="ewznonce"]').val();
    var jqxhr = jQuery.post( ewzG.ajaxurl,
                             {
                               action:   'ewz_reopen',
                               judge_id: judge_id,
                               rating_form_id: rf_id,
                               ewznonce: the_nonce
                             },
                             function (response) {
                                 jQuery('#ewz_status_' + rf_id).html( response );
                              }
                            ).fail( function(){ ewz_alert( 'RF009', 'Sorry, there was a server error', ++popupN );} );       
}

/* Display all webforms with layout_id equal to item_layout_id */
function webform_selection_string( item_layout_id, i, rf_id ){
    var wfm, str ='', sel = '';
    str += '<select multiple="multiple" name="webform_ids[]" id="webform_ids' + rf_id + '">';
    for( wfm in ewzG.webforms ){
        sel = '';
        if(  ewzG.webforms[wfm].layout_id == item_layout_id ){
            var open = '';
            if( ewzG.webforms[wfm].hasOwnProperty('open') ){
                if( ewzG.webforms[wfm].open || ewzG.webforms[wfm].open_for.length > 0 ){
                    open = ' ** OPEN for Upload';
                }
            }
            if( jQuery.inArray( String( ewzG.webforms[wfm].webform_id ), ewzG.rating_forms[i].item_selection.webform_ids ) > -1 ){
               sel =  ' selected="selected"';
            }
            str += '<option value="' + ewzG.webforms[wfm].webform_id + '"' + sel + '>' + ewzG.webforms[wfm].webform_title + open + '</option>';
        }
    }
    str += '</select>';
    return str;
}


function add_new_rating_form(){
    var jQnew, newid,
        num = ewzG.rating_forms.length,
        newform = {};
    newform.can_manage_rating_form = true;
    newform.ratingcount = 0;
    newform.item_selection = {};
    newform.rating_open = false;
    newform.rating_scheme = ewzG.schemes[jQuery('#rating_scheme_id').val()];
    newform.rating_scheme_id = jQuery('#rating_scheme_id').val();
    newform.rating_form_title = '--- New Rating Form ---';
    newform.rating_form_id = '';
    newform.rating_form_ident = '';
    newform.divide_warn_msg = '';
    newform.userlist = ewzG.userlist;
    ewzG.rating_forms[num] = {};
    ewzG.rating_forms[num]['item_selection']={};
    ewzG.rating_forms[num]['item_selection']['webform_ids'] = ewzG.webforms;
    newid = 'X'+num;
    jQnew = jQuery(ewz_management(num, newid, newform, true));
    if( jQnew.find('select[id^="webform_ids"] option').size() < 1 ){
        ewz_alert( "RF002", "You cannot create a Rating Form without at least one webform.  No Webforms have been created using this layout.", ++popupN );
        return;
    }
    jQnew.find('span[id^="tpg_header"]').first().html("New Rating Form: <i>To make it permanent, set the options and save</i>");
    jQnew.find('.ewz_numc').html(ewzG.nonce_string);
    jQnew.find('input[name="ewznonce"]').each(function(index){
        jQuery(this).attr('id', 'ewznonce' + newid + index);
    });
    jQnew.find('input[name="rating_form_id"]').val('');
    jQnew.find('input[id^="ewz_rform_title_ev"]').change(function(){
        jQuery(this).closest('div[id^="ewz-postbox-rating_form_ev"]').find('span[id^="rf_title_"]').text(jQuery(this).val());
    });

    jQuery('#ewz_management').append(jQnew);
}

function  ewz_check_rform_input( rf_id, evnum, do_js_check){
    var jform = jQuery('#rat_form_ev' + rf_id + '_');
    
    if( do_js_check) {
        // disable the submit button
        jQuery('#rat_form_wf' +  evnum + '_').prop("disabled", true);
        var except;
        try{
            // must have a title
            var jtitle = jform.find('input[id^="ewz_rform_title_ev"]');
            if( !(jtitle.val() && jtitle.val().trim()) ){
                err_alert(evnum, ewzG.errmsg.formTitle);
                return false;
            }
            // must have an ident of the right form
            var jident = jform.find('input[id^="rating_form_ident_ev"]');
            if( !( jident.val() && jident.val().match(/^[a-z0-9_\-]+$/i)) ){
                err_alert(evnum, ewzG.errmsg.formIdent);
                return false;
            }
            // must have at least one judge
            var selj = jform.find('select[id^="judges"]');
            if( ( selj.val() === undefined ) ||  ( selj.val() == null )){
                err_alert(evnum, ewzG.errmsg.judge);
                return false;
            }
            // must have at least one webform
            var selwf = jform.find('select[id^="webform_ids"]');
            if( ( selwf.val() === undefined ) ||  ( selwf.val() == null )){
                err_alert(evnum, ewzG.errmsg.webform);
                return false;
            }
        } catch(except) {
            jQuery('#rat_form_wf' +  evnum + '_').prop("disabled", false);
            err_alert( evnum, "Sorry, there was an unexpected error: " + except.message);
            return false;
        }
    }
    if( rf_id.toString().substring(0, 1 ) == 'X' ){

        jform.submit();
    } else {
        var form = ewzG.rating_forms[evnum];
        var msg = '';

        // warn if rating form is open and  any webform is open for upload
        if( jform.find('input:checked[id^="rating_open"]').length > 0 ){
            var selwf = jform.find('select[id^="webform_ids"]');
            selwf.find( 'option:selected:contains("** OPEN for Upload")').each(function() {
                msg += "A webform selected here is currently open for upload. Users will get an error message if they attempt to delete an item that has a rating."; 
                return;
                } );
        }
             
        if( form.rating_open || ( form.curr_total > 0 ) ){
            var old_judges = form.str_judges;
            var new_judges = jQuery('#ewz_admin_ratingforms_ev' + form.rating_form_id + '_').find('select[id^="judges"]').val();
            var added_judges = jQuery(new_judges).not(old_judges).get();
            var removed_judges = jQuery(old_judges).not(new_judges).get();
            if( form.divide_warn_msg && ( added_judges.length > 0  || removed_judges.length > 0 ) ){
                msg += "<br>The judge selection has been changed, and there is a field divided between the judges. ";
                msg += "Assignment of this field to the judges will change.<br>";
            }
            for( var i=0; i<removed_judges.length; i++) {
                if( form.rating_status[removed_judges[i]] > 0 ){
                     msg += "<br>A judge with existing ratings is being removed. Those ratings will no longer be displayed in the spreadsheet.<br>";
                    break;
                }
            }
            // option originally selected, not selected now, and "all" is not selected now for the same option list
            jQuery('#item_sel' + rf_id ).find('option[selected="selected"]').not(":selected").each(function(index){
                 if( jQuery(this ).closest('select').val() != '~*~' ){
                     msg += "<br>You have changed the selection of items.<br><br>";
                     msg += "Removing items from the selection will mean that any existing ratings for them will not appear in the spreadsheet. ";
                     msg += "<br><br>If there are existing ratings, their counts will still show on the rating page, ";
                     msg += "even though the items themselves will no longer display.";
                 } 
            });
            var jown = jQuery('#own' + rf_id);
            if( jown.prop( "checked" ) && ( !jown.prop( 'defaultChecked' ) ) ){
                msg += "<br>Restriction to judges' own items has been added. This means that some already-existing ratings may not appear in the spreadsheet";
            } 
        }

        if( msg ){
           ewz_confirm("Are you quite sure?<br>" + msg, 
                       function(){jform.submit();}, 
                       null, ++popupN );
        } else {
            jform.submit();
        }       
    }    
 }

function err_alert(evnum, msg){
    jQuery('#rat_form_wf' +  evnum + '_').prop("disabled", false);
    ewz_alert('RF003', msg, ++popupN);
}

function recalculate( button, rating_form_id ){
    var rf_nonce = jQuery('input[name="ewznonce"]').val();
    jQuery(button).prop("disabled", true);
    var jqxhr = jQuery.post( ewzG.ajaxurl,
                             {
                                 action: 'ewz_recalc',
                                 ewznonce:   rf_nonce,
                                 rating_form_id: rating_form_id
                             },        
                             function(response) { 
                                 jQuery('#ewz_status_' + rating_form_id).fadeOut(100).fadeIn(100);
                                 jQuery('#ewz_status_' + rating_form_id).html(response);
                                 jQuery(button).prop("disabled", false);

                             }
                          ).fail( function(){ ewz_alert( 'RF010', 'Sorry, there was a server error', ++popupN );} );        
}

function save_ratingform_order(){
    var rf_nonce = jQuery('input[name="ewznonce"]').val();
    var data = {
        action: 'ewz_save_rating_form_order',
        ewznonce:   rf_nonce,
        ewzmode:  'rf_set',
        rforder: new Object()
    };
    if( jQuery('input[id^="edit_rfid"][value=""]').length > 0 ){
        ewz_alert("RF004", "Please save your unsaved Rating_Form before trying to rearrange them", ++popupN);
       return;
    }
        
    jQuery('input[id^="edit_rfid"]').each(function(index){
        data['rforder'][jQuery(this).val()] = index;
    });
    data['action'] = 'ewz_save_rating_form_order';
    data['ewznonce'] = rf_nonce;
    var jqxhr = jQuery.post( ewzG.ajaxurl,
                         data,
                         function (response) {
                             jQuery("#temp_del").remove();
                                 ewz_alert( 'RF005', response, ++popupN );
                         }
                       ).fail( function(){ ewz_alert( 'RF011', 'Sorry, there was a server error', ++popupN );} );       
}

/* Actually delete the form on the server via ajax.  If successful, delete it from the page. */
function delete_rating_form( button, ratingcount ){
    var jbtn = jQuery(button);
    var formdiv = jbtn.closest('div[id^="ewz_admin_ratingforms_ev"]');
    var fid = formdiv.find('input[name="rating_form_id"]').first().attr("value");
    if( '' === fid || null === fid || undefined ===  fid ){
        formdiv.remove();
        return;
    }
    var confirmstring = '';
    if( ( ratingcount !== undefined ) && ( ratingcount > 0 ) ){
       confirmstring +=  ewzG.errmsg.warn + "<br>"  + ewzG.errmsg.hasitems + "\n\n";
    }
    confirmstring += ewzG.errmsg.reallydelete;
    confirmstring += "<br>" + ewzG.errmsg.noundo;

    ewz_confirm( confirmstring,
                 function(){ 
                     do_wipe_rform( jbtn, formdiv, fid ); 
                 },
                 null, ++popupN );
}

function do_wipe_rform( jbtn, formdiv, fid ){
try{
     var fname = jbtn.closest('div[id^="ewz-postbox-rating_form_ev"]').find('span[id^="rf_title_"]').text();
     var d_nonce = formdiv.find('input[name="ewznonce"]').val();
     jbtn.after('<span id="temp_del" style="text-align:left">Processing, please wait ... <img alt="Please Wait" src="' + ewzG.load_gif + '"/></span>');
    var except;
     var jqxhr = jQuery.post( ewzG.ajaxurl,
                          {
                              action: 'ewz_delete_rform',
                              rating_form_id: fid,
                              rating_form_title: fname,
                              ewznonce: d_nonce
                          },
                          function (response) {
                              jQuery("#temp_del").remove();
                              if( '1' == response ){
                                  formdiv.remove();
                              } else {
                                  ewz_alert( 'RF006', response, ++popupN );
                              }
                          }
                        ).fail( function(){ ewz_alert( 'RF012', 'Sorry, there was a server error', ++popupN );} );  
}catch(except){
    console.log("Problem in ewz_delete_rform: " +   except.message );  // needed for admin
}
}

