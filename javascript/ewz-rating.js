'use strict';
/* ewzalrt JR020 */
var rating_win;
var popupN;  // used for id of popup 

jQuery(window).on("load", function() { 
    jQuery('.loading').hide();
});

jQuery(document).ready( function() {
    jQuery("img").on("contextmenu",function(){
        return false;
    }); 

    popupN = 0;
    for ( var wkey1 in window ) {
        if (window.hasOwnProperty(wkey1)) {
            if (wkey1.substring(0, 5) === 'ewzG1') {
                do_setup( window[wkey1], wkey1.substring(6) );
            }
        }
    }
    sortables_init();
    window.name = 'ewz_mainw';
});

function ewz_get_judge_help( ){
   jQuery('#ewz_jhelp').dialog({
      'dialogClass': 'no-close ewz_alert',
      'title': 'Help',
      'width': 700,
      'maxHeight': 700,
       resizable: true,
      'closeOnEscape': true,
       'modal'  : false,
        buttons:[ {
            text:"Ok",
            id: "ewz_ok_help",
           click: function() 
            {
                jQuery( this ).dialog( "close" );
            }
        }]
          });
}


function show_as_judge(jselect, qstr){ 
    var st = window.location.search; 
    var jstring = jQuery(jselect).val();   
    if( jstring.length > 0 ){  
        window.location.search = qstr.replace('j1j1j1', jstring ); 
    } else { 
        window.location.search = qstr.replace(/[?&]?jjj=j1j1j1/, '' );  
    } 
} 

function get_ewzG( rf_num ){
    var ewzG;
    for ( var wkey1 in window ) {  
        if (window.hasOwnProperty(wkey1)) {
            if (wkey1 === 'ewzG1_' + rf_num ) { 
                ewzG = window[wkey1].gvar;
            }
        }
    }
    return ewzG;
}

function htmlDecode(value) {
  return jQuery("<textarea/>").html(value).text();
}

function do_setup( ewzG1_NUM, rf_num ) {
    var ewzG_NUM = ewzG1_NUM.gvar;

    if( ewzG_NUM.do_warn ){
        window.addEventListener("beforeunload", function (e) {
            var enabled = jQuery('button[id ^="ewz_savebtn' + rf_num + '_"]:enabled').length;
            var is_admin_preview = ( window.location.search.indexOf('jjj') > 0 );
            if( !is_admin_preview && enabled > 0 ){
                var confirmationMessage = "Really leave without saving your " + enabled + " unsaved items?";
                (e || window.event).returnValue = confirmationMessage; //Gecko + IE
                return confirmationMessage;                            //Webkit, Safari, Chrome
            }
        });
    }
    if( ewzG_NUM.errmsg  ){
        var curr = jQuery('.alertDivJR001').html();
        if( !( curr && curr.includes( htmlDecode( ewzG_NUM.errmsg)) ) ){
            ewz_alert( 'JR001', ewzG_NUM.errmsg, ++popupN );
        }
    }
    var tid = '#ewz_rtable_' + ewzG_NUM.rf_num;
    jQuery(tid).css("background-color", ewzG_NUM.bg_main);
    jQuery(tid + " tr.ewz_new td").css( "border-top", "1px solid " + ewzG_NUM.new_border);
    jQuery(tid + " tr.ewz_new td").css( "border-bottom", "1px solid " + ewzG_NUM.new_border);
    jQuery(tid + " tr.ewz_new").css( "border", "1px solid " + ewzG_NUM.new_border);
    // After all ajax calls are finished, update the ewz_jstatus div with the new counts
    if( !ewzG_NUM.no_save ){
        jQuery( document ).ajaxStop(function() {
            if( jQuery('#ewz_jstatus').length ){
                var the_nonce = jQuery('.ewz_rtable').find('input[name="ewzratingnonce"]').first().val();
                var jqxhr = jQuery.ajax({  type: "POST",  
                                           url: ewzG_NUM.ajaxurl,  
                                           data: {
                                               action: 'ewz_get_judge_count',
                                               judge_id: ewzG_NUM.jid,
                                               rating_form_id: ewzG_NUM.rf_id,
                                               ewzratingnonce: the_nonce
                                           },  
                                           success: function(response, textStatus,  jq) { 
                                               if ( response.match(/.*Items saved/) ) {
                                                   var oldstatus = jQuery('#ewz_jstatus').html();

                                                   if( oldstatus ){
                                                       jQuery('#ewz_jstatus').html( oldstatus.replace( /^.*Items saved/, response) );
                                                   }
                                                   var d = new Date();
                                                   if( d.getTime() > ewzG_NUM.reload_after ){
                                                       document.location.reload(true); 
                                                   }
                                               } else {
                                                   ewz_alert( 'JR003', response, ++popupN );
                                               }
                                           },
                                           error:  function( response, textStatus, errorThrown ){
                                               ewz_alert( 'JR016', 
                                                          'Sorry, there was an error when getting the "Items Saved" status: ' + errorThrown,
                                                          ++popupN 
                                                        );
                                           },
                                           dataType: 'text',
                                           global: false
                                        });
            }
        }); 
    }
    ewzG_NUM.warn_after = parseInt( ewzG_NUM.warn_after );  
};

// needed for sorting -- set the "ts_custom" attribute of the cell
function ewz_ts_set( rf_num, inpt){
    var ts_value;
    var jinpt = jQuery( inpt );
    var ewzG = get_ewzG( rf_num );

    if( inpt.type == "checkbox" ){
        ts_value = jinpt.is(':checked') ? 1 : '';
    } else {
        ts_value = jinpt.val();
    }
    var jtd = jinpt.closest('td');
    jtd.attr("ts_custom", ts_value );
    jtd.closest('tr').find('button[class^="ewz_savebtn"]').prop('disabled',false);
    jtd.closest('tr').find('button[class^="ewz_clearbtn"]').prop('disabled',false);

    var d = new Date();
    if( d.getTime() > ewzG.warn_after ){
        ewz_alert( 'JR004', document.title + "\nYour access to this page will eventually expire if you do not save something.\nTo avoid losing data, you should save your changes now.  The page should then refresh.", ++popupN );
        ewzG.warn_after = ewzG.warn_after + ewzG.interval;   
    }
}

var avail_h, avail_w, rwin_h, rwin_w;

//************* Image Display and Sorting Functions ************

// onClick for the clear button at the end of each row
function ewz_clear_row( rf_num, button ){
    var ewzG = get_ewzG( rf_num );
    var jbutton = jQuery(button);
    
    var jrow = jbutton.closest('tr');
    jbutton.prev("button").prop('disabled', false );
    var has_saved = ( parseInt( jbutton.closest('td').find('input[name="item_rating_id"]').val() ) > 0 );

    if( !has_saved ){   
        ewz_blank_row( jrow );
    } else {
        ewz_confirm( 'This will delete all the responses you saved for this item, and cannot be undone.  Are you sure?',
                    function(){ ewz_delete_saved_rating( ewzG, jrow, jbutton); },
                     null,
                     ++popupN );
    }
}
    
function ewz_blank_row( jrow ){
    jrow.find("input:text").closest('td').attr('ts_custom', '');
    jrow.find("input:text").val('').attr("value", '');

    jrow.find("input:radio").closest('td').attr('ts_custom', '');
    jrow.find("input:radio").prop("checked", false ).attr("checked", false );

    jrow.find("input:checkbox").closest('td').attr('ts_custom', '');
    jrow.find("input:checkbox").prop("checked", false ).attr("checked", false );

    jrow.find("option").prop("selected", false ).attr("selected", false ).prop("disabled", false);

    jrow.find("select").closest('td').attr('ts_custom', '');
    jrow.find("select").val('');

    jrow.find("textarea").closest('td').attr('ts_custom', '');
    jrow.find("textarea").val('').attr("value", '');

    jrow.addClass('ewz_new');
    jrow.find('input[name="item_rating_id"]').attr("value", "0");
    jrow.find('button').prop("disabled", true ); 
}

function ewz_delete_saved_rating( ewzG, jrow, jbutton ){
    if( ewzG.no_delete ){
        ewz_alert( 'JR015', ewzG.nosavemsg, ++popupN);
        ewz_blank_row( jrow );
    } else {

            jbutton.after('<input type="hidden" id="ajax_action" name="action" value="ewz_delete_rating" /> ' +
                          '<input type="hidden" id="view" name="view" value="' + ewzG.view + '">' +
                          '<input type="hidden" id="judge_id" name="judge_id" value="' + ewzG.jid + '"/>');
            var str =  jrow.find( "input, textarea, select" ).serialize();
            var callback = jQuery.proxy( process_delete_response, this, jbutton, jrow );

            var jqxhr = jQuery.post( ewzG.ajaxurl,
                                     str,
                                     callback
                                   ).fail( function(){ewz_alert( 'JR017', 'Sorry, there was a server error', ++popupN );} );
    }
 }

function process_delete_response(  jbutton, jrow,  response )
{
    jQuery('#ajax_action' ).remove();
    jQuery('#view' ).remove();
    if ( response.match(/^1$/) ) {
        ewz_blank_row( jrow );
    } else {
        ewz_alert( 'JR014', response, ++popupN );
    }
}


function ewz_show_image( rf_num, the_content, return_row ){

    var ewzG = get_ewzG( rf_num );
    var minpad = 10;

    return_row = typeof return_row !== 'undefined' ?  return_row : 0;
    if(  !rating_win || rating_win.closed || ( undefined == rating_win  ) || ( rating_win == null ) || ( rating_win.top == null ) ){
        avail_h = window.screen.availHeight - 80; // -80 for url-bar and window decorations
        avail_w = window.screen.availWidth - 130; // leave some space on each side for nav buttons

        rwin_h = Math.min( avail_h,  parseInt(ewzG.maxh) + parseInt(ewzG.img_pad) + 2*minpad + 50 );  // 50 for link below image
        rwin_w = Math.min( avail_w,  parseInt(ewzG.maxw) ) + 130; 

        // There should not be any spaces around the commas or anywhere else in the window config string.
        rating_win = window.open( '', 'Rating', 
                                  'height=' + rwin_h + ',width=' + rwin_w + ',menubar=no,toolbar=no,status=no,resizable=yes,scrollbars=yes' );
    }

    var content;
    if( the_content == "-1" ){
        var label;
        if( return_row > 0 ){
            label = "Back";
        } else {
            label = "To First Image";
        }
        content =  '<div class="ewz_win">';
        content +=         '<img  src="' + ewzG.testimg + '" width="' + ewzG.maxw + '" height="' + ewzG.maxh + '">';
        content += '</div>'; 
        content += '<button class="testbutton" id="backImgBtn" onClick="opener.ewz_rating_window(' + rf_num + ',' + return_row + ' )">' + label + '</button>';
    } else {
        content = the_content;
    }


    rating_win.document.open();
    rating_win.document.write( '<style type="text/css">' );
    rating_win.document.write(    'body { background: ' + ewzG.bcol + '; color:' + ewzG.bcol + '; margin: 0px;  overflow: auto;}' );
    rating_win.document.write(    '.ewz_win { ' );
    rating_win.document.write(              ' position:absolute;top:20%;left:50%;margin-right:-50%; ');
    rating_win.document.write(              ' transform:translate(-50%,-20%); -webkit-transform:translate(-50%,-20%); -ms-transform:translate(-50%,-20%);');
    rating_win.document.write(              ' padding-top: ' + parseInt(ewzG.img_pad) + 'px;  white-space:nowrap; ' );
    rating_win.document.write(            ' }' );
    rating_win.document.write(    '.ewz_win img { ' );
    rating_win.document.write(                  ' max-width:' + ewzG.maxw + 'px;'  );
    rating_win.document.write(                  ' max-height:' + ewzG.maxh + 'px;' );
    rating_win.document.write(                  ' padding: ' + minpad + 'px;' );
    rating_win.document.write(                 '}');

    rating_win.document.write(    '.buttonrow { margin-bottom:10px; margin-left: auto; margin-right: auto; padding-bottom: 10px; }');
    rating_win.document.write(    '.prevbutton,.nextbutton {  background-color:transparent; color:' + ewzG.fcol + ';border: 0px;  }');
    rating_win.document.write(    '.testbutton { position:fixed;bottom:20; right:20;  background-color:transparent; color:' + ewzG.fcol + ';border: 0px;  }');
    rating_win.document.write( '</style> ' );

    rating_win.document.write(    content );
    rating_win.document.close();   
}

function ewz_rating_window( rf_num, rownum ) {
    var ewzG = get_ewzG( rf_num );

    jQuery('#ewz_rtable_' + rf_num + ' > tbody > tr' ).attr("style", "background: " + ewzG.bg_main + ";");
    jQuery('#ewz_rtable_' + rf_num + ' > tbody > tr > td ' ).attr("style", "background: " + ewzG.bg_main + ";");

    // change the background of the currently-selected image row ( counts from 1, not 0 )        
    var rnum = rownum + 1;
    var jrow =  jQuery('#ewz_rtable_' + rf_num + ' > tbody > tr:nth-of-type(' + rnum + ')');
    jrow.attr("style", "background: " + ewzG.bg_curr + ";");
    jQuery('#ewz_rtable_' + rf_num + ' > tbody > tr:nth-of-type(' + rnum + ') > td ' ).attr("style", "background: " + ewzG.bg_curr + ";");

    // scroll the scoring page to the item 
    jQuery("body, html").animate({ scrollTop: jrow.offset().top - jrow.height() }, {}, 600);

    // set up the html and add the content, which is stored hidden in the last column of #ewz_rtable
    var content = jQuery('#ewz_rtable_' + rf_num + ' > tbody > tr:nth-of-type(' + rnum + ')').find('div[class^="ewz_rating_page"]').html();

    ewz_show_image( rf_num, content );

    // hide the next link on the last image, and the previous link on the first
    if( rownum == 0 ){ jQuery('body', rating_win.document ).find('.prevbutton').hide(); }
    if( rownum == ( ewzG.num_rows - 1 ) ){ jQuery('body', rating_win.document ).find('.nextbutton').hide(); }

    var notsaved = ewz_count_not_saved( rf_num );
    if( notsaved >= ewzG.max_unsaved ){
        ewz_alert( "JR020", "There are " + notsaved + " unsaved items. You risk losing data if you do not save them before making more changes.", ++popupN );
    }
}

// call to this added to table-sorting script by js to keep the 'next' and 'previous' links 
// working after a sort
function ewz_fixLinks( link ){
    var jtable = jQuery(link).closest( 'table[id^="ewz_rtable_"]' );
    var rf_num = jtable.attr("id").substring(11);
    var jrows = jtable.find('tbody').children();
    jrows.find('[class="nextbutton"]').each( function( index ){
        jQuery(this).attr("onclick","opener.ewz_rating_window( " + rf_num + ", " + (index + 1 ) + ")");
    });
    jrows.find('[class="prevbutton"]').each( function( index ){
        jQuery(this).attr("onclick","opener.ewz_rating_window( " + rf_num + ", " + (index - 1 ) + ")");
    });     
    jrows.find('img.thumb').each( function( index ){
        jQuery(this).attr("onclick","ewz_rating_window( " + rf_num + ", " + index + ")");
    });
    if( ( rating_win !== undefined ) && ( rating_win.top !== null ) ){
        ewz_rating_window( rf_num, 0);
    }
}

// jump to the first unrated item
function to_next_item( rf_num ){
    var jrow = jQuery('tr[class="ewz_new"]').first();
    jQuery("body, html").animate({ scrollTop: jrow.offset().top - jrow.height() }, {}, 600);
    ewz_rating_window( rf_num, jrow.index() );
}

//************* Judge Finished ************
function judge_finished( rf_num ){
    var ewzG = get_ewzG( rf_num );
    var notsaved = ewz_count_not_saved( rf_num );
    if( notsaved > 0 ){
        ewz_alert( "JR005", "There are still " + notsaved + " unsaved items. Please save them first.", ++popupN );
        return;
    }
    if( ewz_check_all_rows(  ewzG, rf_num ) ){
        ewz_confirm( "This will remove your access to the page. Are you sure you are completely finished? ",
                     function(){ 
                         do_judge_finished(ewzG, rf_num);
                     },
                     null, ++popupN );
    }
}

function do_judge_finished(ewzG, rf_num){
    if( ewzG.no_save ){
        ewz_alert( 'JR006', ewzG.nosavemsg, ++popupN);
    } else {
        var the_nonce = jQuery('#ewz_rtable_' + rf_num).find('input[name="ewzratingnonce"]').first().val();
        var jqxhr = jQuery.post( ewzG.ajaxurl,
                                 {
                                     action: 'ewz_done',
                                     judge_id: ewzG.jid,
                                     rating_form_id: ewzG.rf_id,
                                     ewzratingnonce: the_nonce
                                 },                            
                                 function(response) { 
                                     if ( response == '1') {
                                         document.location.reload(true);
                                     } else {
                                         ewz_alert( 'JR007', response, ++popupN );
                                     }
                                 }
                               ).fail( function(){ ewz_alert( 'JR018', 'Sorry, there was a server error', ++popupN );} ); 
    }       
}



function ewz_count_not_saved( rf_num ){
    var notsaved = 0;
    jQuery('#ewz_rtable_' + rf_num).find('button[class^="ewz_savebtn"]').each( function(){
        var jthis = jQuery(this);
        if( !jthis.prop("disabled")){    
            ++notsaved;
        }
    });
    return notsaved; 
}


function ewz_check_all_rows(  ewzG, rf_num ){
    var has_error = false;
    jQuery('#ewz_rtable_' + rf_num).find('button[class^="ewz_savebtn"]').each( function(){
        var row_status =  ewz_check_row( rf_num, ewzG, this );
        switch ( row_status ) {
        case 'req':  has_error = true ; return false;
            break;
        case 'restr': has_error = true ; return false;
            break;
        }
    });
    return !has_error;

}


//************* Checks Before Saving ************
var ewz_alert_done;
function ewz_check_required( rf_num, ewzG, jrow, fvalues ){
    if( !ewzG.jsvalid ){
        return true;
    }
    for( var fid in ewzG.fields ){
        if( ewzG.fields.hasOwnProperty(fid) ){
            if( ( ( ewzG.view == 'rate' ) || ( ewzG.fields[fid].is_second && ( ewzG.view == 'secondary' ) ) )  && 
                ( ewzG.fields[fid].required ) && 
                ( jrow.find('[name="rating[' + fid +']"]').length > 0 )  &&  isblank( fvalues[fid][0] ) 
              ){
                // hilite the problem item and move to it
                ewz_rating_window( rf_num, jrow.index() );         
                ewz_alert_done = false;   // need both html and body in next line, may find both
                jQuery("body, html").animate({ scrollTop: jrow.offset().top - jrow.height() }, 
                                             {
                                                 complete: function() { 
                                                     if( !ewz_alert_done ){
                                                         jrow.find('button').first().focus();  // otherwise goes to top on close
                                                         ewz_alert( "JR008", "Items Not Saved: " +  ewzG.fields[fid].field_header + " is required.", ++popupN );
                                                         ewz_alert_done = true;
                                                         return false;  // otherwise goes to top on close
                                                     }
                                                 },
                                                 duration: 600
                                             }
                                            );
                return false;
            }
        }
    }
    return true;
}

/* If any restrictions are not satisfied, show an ewz_alert and return false */
/* Otherwise, return true                                                */
function restrictions_check( rf_num, ewzG, jrow, fvalues ){
    if( ewzG.view == 'read' ){
        return true;
    }
    if( ewzG.view == 'secondary' ){
        return true;
    }
    if( !ewzG.jsvalid ){
        return true;
    }
    for ( var restr1 in ewzG.restrictions) { 
        if (!ewzG.restrictions.hasOwnProperty(restr1)) { continue; }
        var msg = ewzG.restrictions[restr1].msg;
        var row_matches_restr = true;     

        // set row_matches_restr for the row to false if any field does not match
        for ( var field_id in ewzG.fields) { 
            if (!ewzG.fields.hasOwnProperty(field_id)) { continue; }
            var field_restrs = ewzG.restrictions[restr1][field_id];
            if( field_restrs !== undefined ){
                var field_matches_restr = r_field_matches_restr( field_restrs, fvalues[field_id][0] );
                row_matches_restr = row_matches_restr && field_matches_restr;
                if( !row_matches_restr ){
                    break; 
                }
            }
        }

        // if row_matches_restr is still true, ewz_alert and return false
        if ( row_matches_restr ) {
            // hilite the problem item and move to it
            ewz_rating_window( rf_num, jrow.index() );                  // select the problem item
            ewz_alert_done = false;   // need both html and body in next line, may find both
            jQuery('html, body').animate({ scrollTop: jrow.offset().top - jrow.height() }, 
                                         { complete: function(){ 
                                             if( !ewz_alert_done ){
                                                 jrow.find('button').first().focus();  // otherwise goes to top on close
                                                 ewz_alert( "JR009", "Items Not Saved: " + ewzG.restrictions[restr1].msg + "." , ++popupN); 
                                                 ewz_alert_done = true;
                                                 return false;  // otherwise goes to top on close
                                             }
                                         }
                                         }, 600 );
            return false;
        }
    }
    return true;
}


function r_field_matches_restr( restriction_vals, entered_val ) {
    var restriction_val;
    for( var r in restriction_vals ){
        if( restriction_vals.hasOwnProperty(r) ){
            switch ( restriction_vals[r] ) {
            case  undefined:
                return true;
            case  '~*~':
                return true;
            case  '~-~':
                if (isblank(entered_val)) {
                    return true;
                }
                break;
            case  '~+~':
                if (!isblank(entered_val)) {
                    return true;
                }
                break;
            default:
                if( restriction_vals[r] == entered_val ) {
                    return true;
                }
                break;
            }
        }
    }
    return false;
}

function ewz_check_max_counts( rf_num, ewzG ){
    if( !ewzG.jsvalid ){
        return true;
    }
    for ( var fnum in ewzG.fields ) { 
        if( ewzG.fields.hasOwnProperty(fnum) ){
            var field = ewzG.fields[fnum];
            var ftype = field.field_type; 
            if ( ftype === 'chk' ){
                var maxn = field.fdata['chkmax'] ;
                if( maxn !== undefined && maxn > 0) { 
                    if ( maxn < jQuery( '#ewz_rtable_' + rf_num  + ' input:checked[name="rating[' + field.rating_field_id + ']"]' ).length ){
                        ewz_alert( "JR010", "Items Not Saved: No more than " + maxn + " items may have " +  field.field_header + " checked.", ++popupN );
                        return false;
                    }  
                }              
            }
        }
    }
    return true;
}

function ewz_check_row( rf_num, ewzG, button ){
    var jbutton = jQuery(button);
    var jrow = jbutton.closest('tr');
    var fvalues = get_inputs( ewzG, jrow);
    var req_check = ewz_check_required( rf_num, ewzG, jrow, fvalues );
    if( !req_check ){
        return 'req'; 
    }
    if( jrow.hasClass('ewz_new') ){
        var all_blank = true;
        for ( var fnum in ewzG.fields ) { 
            if( ewzG.fields.hasOwnProperty(fnum) ){
                var field = ewzG.fields[fnum];
                var ftype = field.field_type; 
                if( !( ftype == 'fix' || ftype == 'xtr' || ftype == 'lab' ) ){
                    if( !isblank( fvalues[field.rating_field_id][0] ) ){
                        all_blank = false;
                        break;
                    } 
                }
            }
        }
        if( all_blank ){
            return 'blank';
        }
    }
    var restr_check = restrictions_check( rf_num, ewzG, jrow, fvalues );
    if( restr_check ){
        return 'ok';
    } else {
        return 'restr';
    }
}

function get_inputs( ewzG, jrow)
{
    // second array value not needed now but will be if we ever add maxcounts to option lists
    var fvalues = {}, fid, jelem, fixval,fixdisplay;
    for (var i in ewzG.fields) {
        if (!ewzG.fields.hasOwnProperty(i)) { continue; }
        fid = ewzG.fields[i].rating_field_id;
        switch(ewzG.fields[i].field_type){
        case 'fix': 
            fixval = jrow.find('span[class="ewzval_' + fid + '"]').find('img').attr("src");
            if( ( fixval !== undefined ) && ( fixval.length > 0 ) ){
                fvalues[fid] = [ fixval, fixval ];
                break;
            }
        case 'xtr':
        case 'lab':
            fixval = jrow.find('[class="ewzval_' + fid + '"]').text();
            fvalues[fid] = [ fixval, fixval ];
            break;
        case 'str':
            jelem = jrow.find(':input[name$="[' + fid + ']"]');
            fvalues[fid] = [ jelem.val(), jelem.val() ];
            break;
        case 'opt':
            jelem = jrow.find(':input[name$="[' + fid + ']"] option:selected');
            fvalues[fid] = [ jelem.val(), jelem.text() ];
            break;
        case 'chk':
            jelem = jrow.find(':input[name$="[' + fid + ']"]');
            if( jelem.is(':checked') ){
                fvalues[fid] = [  1, 'checked' ];
            } else {
                fvalues[fid] = [  '', 'checked'  ];
            }
            break;
        }
    }
    return fvalues;
}

//************** Save Changed Items ************

var last_ewz_alert_done;
// onClick for the save button at the end of each row
function ewz_save_changed_rows( rf_num, button ){
    var ewzG = get_ewzG( rf_num );
    var jbutton = jQuery(button);
    var insertion = '<span id="temp_gen" style="text-align:left">Processing, please wait ... <img alt="Please Wait" src="' + ewzG.load_gif + '"/></span>';
    jbutton.after(insertion);

    var good_rows = [];
    var has_error = false;

    var max_check = ewz_check_max_counts( rf_num, ewzG );
    if( max_check ){
        jQuery('#ewz_rtable_' + rf_num).find('button[class^="ewz_savebtn"]').each( function(){
            var jthis = jQuery(this);
            if( !jthis.prop("disabled")){ 
                var row_status = ewz_check_row( rf_num, ewzG, this );
                switch(row_status){
                case 'blank': jthis.prop("disabled", true );  
                    break;
                case 'req':
                case 'restr': has_error = true; return false;  // stop at first error
                    break;
                case 'ok':  good_rows.push( this ); 
                    break;
                }
            }
         } );
        if( !has_error ){
            ewz_save_rows( ewzG, good_rows );
        } 
    }
    jQuery("#temp_gen" ).remove();
}


function ewz_save_rows( ewzG, buttons ){ 
    if( ewzG.no_save ){
        ewz_alert( 'JR011', ewzG.nosavemsg, ++popupN);
    } else {
        for( var row_n = 0; row_n < buttons.length; ++row_n ){
            var jbutton = jQuery(buttons[row_n]);
            var jrow = jbutton.closest('tr');
            jbutton.after('<input type="hidden" id="ajax_action" name="action" value="ewz_save_rating" /> ' +
                          '<input type="hidden" id="view" name="view" value="' + ewzG.view + '"/>' +
                          '<input type="hidden" id="judge_id" name="judge_id" value="' + ewzG.jid + '"/>');
            var str =  jrow.find( "input, textarea, select" ).serialize();
            var callback = jQuery.proxy( process_response, this, jbutton, jrow );
            jbutton.prop("disabled", true ); 

            var jqxhr = jQuery.post( ewzG.ajaxurl,
                                     str,
                                     callback
                                   ).fail( function(){ ewz_alert( 'JR019', 'Sorry, there was a server error', ++popupN );} );
        } 
   }
}

function process_response(  jbutton, jrow,  response )
{
    jQuery('#ajax_action' ).remove();
    jQuery('#view' ).remove();
    if ( response.match(/^[1-9][0-9]*$/) ) {
        jrow.removeClass('ewz_new');
        jrow.find('input[name="item_rating_id"]').val(response);
        jbutton.prop("disabled", true ); 
    } else {
        jbutton.prop("disabled", false ); 
        ewz_alert( 'JR012', response, ++popupN );
    }
}

//************** Table Sorting Script ************
/*
  Table sorting script  by Joost de Valk, check it out at http://www.joostdevalk.nl/code/sortable-table/.
  Based on a script from http://www.kryogenix.org/code/browser/sorttable/.
  Distributed under the MIT license: http://www.kryogenix.org/code/browser/licence.html .
  Copyright (c) 1997-2007 Stuart Langridge, Joost de Valk.
  Version 1.5.7
  Date sorting removed  and custom overrides restored ( from the earlier Langridge version )  by Josie Stauffer.
*/

//addEvent(window, "load", sortables_init);

var SORT_COLUMN_INDEX;
var thead = false;

function sortables_init() {
    // Find all tables with class sortable and make them sortable
    if (!document.getElementsByTagName) return;
    var tbls = document.getElementsByTagName("table");
    for (var ti=0;ti<tbls.length;ti++) {
        var thisTbl = tbls[ti];
        if (((' '+thisTbl.className+' ').indexOf("sortable") != -1) && (thisTbl.id)) {
            ts_makeSortable(thisTbl);
        }
    }
}

function ts_makeSortable(t) {
    if (t.rows && t.rows.length > 0) {
        if (t.tHead && t.tHead.rows.length > 0) {
            var firstRow = t.tHead.rows[t.tHead.rows.length-1];
            thead = true;
        } else {
            var firstRow = t.rows[0];
        }
    }
    if (!firstRow) return;
    
    // We have a first row: assume it's the header, and make its contents clickable links
    for (var i=0;i<firstRow.cells.length;i++) {
        var cell = firstRow.cells[i];
        var txt = ts_getInnerText(cell);
        if (cell.className != "unsortable" && cell.className.indexOf("unsortable") == -1) {
            cell.innerHTML = '<a href="#" class="sortheader" id="' + cell.className + '" onclick="ts_resortTable(this, '+i+');ewz_fixLinks(this);return false;">'+txt+'&nbsp;<span class="sortarrow">&darr;</span></a>';
        }
    }
}

function ts_getInnerText(el) {
    if (typeof el == "string") return el;
    if (typeof el == "undefined") { return el; }
    if (el.getAttribute("ts_custom") != null) {
        return el.getAttribute("ts_custom");
    }
    if (el.innerText) return el.innerText;  //Not needed but it is faster
    var str = "";
    
    var cs = el.childNodes;
    var l = cs.length;
    for (var i = 0; i < l; i++) {
        switch (cs[i].nodeType) {
        case 1: //ELEMENT_NODE
            str += ts_getInnerText(cs[i]);
            break;
        case 3: //TEXT_NODE
            str += cs[i].nodeValue;
            break;
        }
    }
    return str;
}

function ts_resortTable(lnk, clid) {
    var span;
    for (var ci=0;ci<lnk.childNodes.length;ci++) {
        if (lnk.childNodes[ci].tagName && lnk.childNodes[ci].tagName.toLowerCase() == 'span') span = lnk.childNodes[ci];
    }
    var spantext = ts_getInnerText(span);
    var td = lnk.parentNode;
    var column = clid || td.cellIndex;
    var t = getParent(td,'TABLE');
    // Work out a type for the column
    if (t.rows.length <= 1) return;
    var itm = "";
    var i = 0;
    while (itm == "" && i < t.tBodies[0].rows.length) {
        var itm = ts_getInnerText(t.tBodies[0].rows[i].cells[column]);
        itm = trim(itm);
        if (itm.substr(0,4) == "<!--" || itm.length == 0) {
            itm = "";
        }
        i++;
    }
    if (itm == "") return; 
    var sortfn = ts_sort_caseinsensitive;
    if (itm.match(/^-?[£$€Û¢´]\d/)) sortfn = ts_sort_numeric;
    if (itm.match(/^-?(\d+[,\.]?)+(E[-+][\d]+)?%?$/)) sortfn = ts_sort_numeric;
    SORT_COLUMN_INDEX = column;
    var firstRow = new Array();
    var newRows = new Array();
    for (var k=0;k<t.tBodies.length;k++) {
        for (i=0;i<t.tBodies[k].rows[0].length;i++) { 
            firstRow[i] = t.tBodies[k].rows[0][i]; 
        }
    }
    for (var k=0;k<t.tBodies.length;k++) {
        if (!thead) {
            // Skip the first row
            for (j=1;j<t.tBodies[k].rows.length;j++) { 
                newRows[j-1] = t.tBodies[k].rows[j];
            }
        } else {
            // Do NOT skip the first row
            for (var j=0;j<t.tBodies[k].rows.length;j++) { 
                newRows[j] = t.tBodies[k].rows[j];
            }
        }
    }
    newRows.sort(sortfn);
    if (span.getAttribute("sortdir") == 'down') {
        var ARROW = '&darr;';
        newRows.reverse();
        span.setAttribute('sortdir','up');
    } else {
        var ARROW = '&uarr;';
        span.setAttribute('sortdir','down');
    } 
    // We appendChild rows that already exist to the tbody, so it moves them rather than creating new ones
    // don't do sortbottom rows
    for (var i=0; i<newRows.length; i++) { 
        if (!newRows[i].className || (newRows[i].className && (newRows[i].className.indexOf('sortbottom') == -1))) {
            t.tBodies[0].appendChild(newRows[i]);
        }
    }
    // do sortbottom rows only
    for (var i=0; i<newRows.length; i++) {
        if (newRows[i].className && (newRows[i].className.indexOf('sortbottom') != -1)) 
            t.tBodies[0].appendChild(newRows[i]);
    }
    // Delete any other arrows there may be showing
    var allspans = document.getElementsByTagName("span");
    for (var ci=0;ci<allspans.length;ci++) {
        if (allspans[ci].className == 'sortarrow') {
            if (getParent(allspans[ci],"table") == getParent(lnk,"table")) { // in the same table as us?
                allspans[ci].innerHTML = '&darr;';
            }
        }
    }               
    span.innerHTML = ARROW;
}

function getParent(el, pTagName) {
    if (el == null) {
        return null;
    } else if (el.nodeType == 1 && el.tagName.toLowerCase() == pTagName.toLowerCase()) {
        return el;
    } else {
        return getParent(el.parentNode, pTagName);
    }
}


function ts_sort_numeric(a,b) {
    var aa = ts_getInnerText(a.cells[SORT_COLUMN_INDEX]);
    aa = clean_num(aa);
    var bb = ts_getInnerText(b.cells[SORT_COLUMN_INDEX]);
    bb = clean_num(bb);
    return compare_numeric(aa,bb);
}
function compare_numeric(a,b) {
    var a = parseFloat(a);
    a = (isNaN(a) ? 0 : a);
    var b = parseFloat(b);
    b = (isNaN(b) ? 0 : b);
    return a - b;
}
function ts_sort_caseinsensitive(a,b) {
    var aa = ts_getInnerText(a.cells[SORT_COLUMN_INDEX]).toLowerCase();
    var bb = ts_getInnerText(b.cells[SORT_COLUMN_INDEX]).toLowerCase();
    if (aa==bb) {
        return 0;
    }
    if (aa<bb) {
        return -1;
    }
    return 1;
}
function addEvent(elm, evType, fn, useCapture)
// addEvent and removeEvent
// cross-browser event handling for IE5+,       NS6 and Mozilla
// By Scott Andrew
{
    if (elm.addEventListener){
        elm.addEventListener(evType, fn, useCapture);
        return true;
    } else if (elm.attachEvent){
        var r = elm.attachEvent("on"+evType, fn);
        return r;
    } else {
        ewz_alert("JR013", "Handler could not be removed", ++popupN);
    }
}
function clean_num(str) {
    var str = str.replace(new RegExp(/[^-?0-9.]/g),"");
    return str;
}
function trim(s) {
    return s.replace(/^\s+|\s+$/g, "");
}

