<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . 'classes/ewz-base.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-exception.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-field.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-layout.php' );
require_once( EWZ_RATING_DIR . 'classes/ewz-rating-form.php' );
require_once( EWZ_RATING_DIR . 'classes/validation/ewz-rating-form-input.php' );
require_once( EWZ_PLUGIN_DIR . 'includes/ewz-common.php' );
require_once( EWZ_CUSTOM_DIR . 'ewz-custom-data.php' );


/**
 * Process input data 
 **/
function ewz_process_rating_form_input()
{
    switch( $_POST['ewzmode'] ){
        // 'spread' is caught by the ewz_
        // function in ewz_admin-rating.php, which is hooked to plugins_loaded

    case  'ratingform':

        // validate all input data ( except uploaded files )
        $input = new Ewz_Rating_Form_Input($_POST);
        $ratingform = new Ewz_Rating_Form( $input->get_input_data() );
                              // text fields are sanitized before saving
        $ratingform->save();
        break;

    default:
        throw new EWZ_Exception( 'Invalid Input ', 'mode=' . preg_replace( '/[^a-z0-9 _-]/i', '_', $_POST['ewzmode'] ) );
    }
}


/**
 * Recalculate the rating_item counts for $ratingform
 *  
 * @param   Ewz_Rating_Form  $ratingform    Changed by this function
 * @return  The html for the status area
 **/
function ewz_set_status_table( &$ratingform )
{
    assert( is_a( $ratingform,  'Ewz_Rating_Form' ) );
    if( !isset( $ratingform->rating_status['count'] ) ){
        $ratingform->rating_status['count'] = 0;
    }
    $esc_rfid = esc_attr( $ratingform->rating_form_id );
    $num_items = $ratingform->rating_status['count'];
    if( empty( $ratingform->item_selection['own'] ) || in_array( 0, $ratingform->judges ) ){
        $ratingform->status_table = "<b>Total Number of Items: $num_items </b>";
    } else {
        $ratingform->status_table = "<b>Number of Saved Ratings: $num_items </b>";
    }

    // dont show the full status table if "all users" is checked
    if( in_array( 0, $ratingform->judges ) ){
        if( isset( $ratingform->rating_status[0] ) ){
            $ratingform->status_table .= "<br><b>Total Number of Ratings: " . $ratingform->rating_status[0] . "</b>";
        } else {
            $ratingform->status_table .= "<br><b>Total Number of Ratings: </b>";
        }
    } else {
        $ratingform->status_table .= "<table id='ewz_status_table_$esc_rfid'><thead>";
        $ratingform->status_table .= '<tr><th>Judge</th><th>Number<br>Done</th><th>Percent<br>of Total</th></tr></thead><tbody>';
        foreach( $ratingform->actual_judges as $judge_id ){
            $esc_jid = esc_attr( $judge_id );
            if( $esc_jid ){
                $user_info = get_userdata( $esc_jid );
                $jname = $user_info->display_name;
                $ratingform->status_table .= "<tr><td id='jname_{$esc_rfid}_{$esc_jid}'>$jname</td>";
                if( isset( $ratingform->rating_status[$esc_jid] ) ){
                    $num_done = str_replace( ' Complete', '',  $ratingform->rating_status[$esc_jid] );
                    $percent = 0;
                    if( $num_items > 0 ){
                        $percent = round( ( $num_done / $num_items ) * 100 );
                    }
                    $ratingform->status_table.= "<td>$num_done</td>";
                    $ratingform->status_table.= "<td>{$percent}%</td>";
                    if( strpos( $ratingform->rating_status[$esc_jid], 'Complete' ) !== false ) {
                        $ratingform->status_table.= "<td>Finished<br>";
                        $ratingform->status_table.=     "<button onClick='judge_reopen($esc_jid, $esc_rfid)' ";
                        $ratingform->status_table.=            " id='judge_reopen{$esc_jid}_{$esc_rfid}'>Re-open<br>for this judge";
                        $ratingform->status_table.=     "</button>";
                        $ratingform->status_table.= "</td>";
                    } else {
                        $ratingform->status_table.= "<td></td>";
                    }
                    $disabled = $ratingform->rating_status[$esc_jid] ? '' : 'disabled="disabled"';
                    $ratingform->status_table.= "<td><button $disabled onClick='del_judge_ratings($esc_jid, $esc_rfid)' ";
                    $ratingform->status_table.= " id='judge_delete{$esc_jid}_{$esc_rfid}'>Delete ratings<br>for this judge</button></td>";
                } else {
                    $ratingform->status_table.= '<td>0</td><td>0%</td><td></td>';
                }
                $ratingform->status_table .= "</tr>";
            }
        }
        $ratingform->status_table .= "</tbody></table>";
    }
    return $ratingform->status_table;    // return is required for the ajax recalculate call
}


/**
 * Add some extra data into each rating form for use by javascript
 * 
 * Adds the html text for the option lists for selecting users, rating schemes and items
 * 
 * @param   array $user_arr  list of users with ewz-judge role ( for selecting judges )
 * @param   array of Ewz_Rating_Forms  $ratingforms
 * @return  $ratingforms with extra data added
 **/
function ewz_setup_ratings( $user_arr, $ratingforms )
{
    assert( is_array( $user_arr ) );
    assert( is_array( $ratingforms ) );
    foreach ( $ratingforms as $ratingform ) {
        $s_options = Ewz_Rating_Scheme::get_rating_scheme_opt_array( 'can_assign_layout', 
                                                                     $ratingform->rating_scheme->item_layout_id 
                                                                   );
        ewz_set_status_table( $ratingform );
        $r_user_arr = $user_arr;
        $rf_userlist  = ewz_option_list( $r_user_arr );
        $ratingform->schemes_options = ewz_option_list( $s_options  );

        $total_ratings = 0;
        $ratingform->str_judges = array();
        foreach( $ratingform->judges as $judge_id ){
            $esc_jid = esc_attr( $judge_id );
            $select_status = ' selected="selected"';
            $rf_userlist = preg_replace('/option value="' . $esc_jid . '"/', 'option value="' . $esc_jid .'"'.  $select_status, $rf_userlist );
            if( isset( $ratingform->rating_status[$esc_jid] ) ){
                $total_ratings += $ratingform->rating_status[$esc_jid];
            }
            // needed for comparison with new ones in javascript
            array_push( $ratingform->str_judges, "$esc_jid");
        }

        if( $ratingform->rating_scheme->item_layout->layout_name == null ){
            $ratingform->rating_scheme->item_layout->layout_name =' *** ERROR *** layout not found';
        }

        $ratingform->curr_total = $total_ratings;
        $ratingform->userlist = $rf_userlist;
        $ratingform->divide_warn_msg = '';
        if( $ratingform->rating_scheme->has_divide() && !in_array( 0, $ratingform->judges ) ){
            $ratingform->divide_warn_msg = '** WARNING: the rating scheme contains a field with the "divide between judges" flag set.';
            $ratingform->divide_warn_msg .= '<br>The division of the field between judges is determined by their order on the judge selection list.';
            $ratingform->divide_warn_msg .= '<br>Any change to the judge selection will change the allocation of the field to judges.';
        }
        // For the item selection dialog
        // only allow selection if a finite list of possibilies is provided
        // -- ie an 'opt', 'chk' or 'rad' upload field or a custom item with a selection list
        // 1. 'opt' fields from the layout used to upload the items
        foreach ( $ratingform->rating_scheme->item_layout->fields as $field ) {
            $ratingform->field_names[$field->field_id] = ewz_html_esc( $field->field_header );

            $list = array();
            if( in_array( $field->field_type, array( 'opt', 'chk', 'rad' ) ) ){

                if( isset( $ratingform->item_selection['fopts'][$field->field_id] ) ){
                    $list = $field->get_field_opt_array( $ratingform->item_selection['fopts'][$field->field_id] );
                } else {
                    $list = $field->get_field_opt_array( array('~*~') );
                }
                if( $list ){
                    $ratingform->field_options[$field->field_id] = ewz_option_list( $list );
                }
            }
        }

        // 2. Custom fields with a selection_list defined
        if( method_exists( 'Ewz_Custom_Data', 'selection_list' ) ){
            foreach ( Ewz_Custom_Data::$data  as $key => $name ){
                $sel_list = Ewz_Custom_Data::selection_list( $key );
                $list = array();
                if( count( $sel_list ) > 1  ){
                    $ratingform->field_names[$key] = ewz_html_esc( $name );

                    $saved = array('~*~');
                    if( isset( $ratingform->item_selection['fopts'][$key] ) ){
                        $saved = $ratingform->item_selection['fopts'][$key];
                    }
                    array_push( $list, array( 'value'=>'~*~', 'display'=>'Any', 'selected'=>in_array( '~*~', $saved ) ) );
                    foreach ( $sel_list  as $val ){
                        if( $val ){ 
                            array_push( $list, array( 'value' => $val, 'display' => $val, 
                            'selected' => in_array( $val, $saved ) ) ); 
                        } 
                    }
                }
                if( $list ){
                    $ratingform->field_options[$key] = ewz_option_list( $list  );
                }
            }  
        }
    }
    return $ratingforms;
}

/**
 * Return a list of members with 'ewz_judge' role for use with ewz_option_list
 **/
function ewz_get_judge_opt_array()
{
    if( !current_user_can( 'list_users' ) ){
        return array();
    }
    $users = get_users( array( 'role'=>'ewz_judge', 'orderby'=>'nicename') );
    $options = array();
    foreach ( $users as $user ) {
        if( $user->has_cap( 'ewz_rating' ) ){
            $display =  $user->display_name . ' ( ' . $user->user_login . ', ID=' .  $user->ID . ' )';
            array_push( $options, array(  'value' => $user->ID, 'display' => $display ) );
        }
    }
    return $options;
}


/**
 * Callback for add_submenu_page
 * 
 * Actually outputs the html for the ratings admin page
 *
 **/
function ewz_rating_form_menu()
{
    $message = '';

    if ( !Ewz_Rating_Permission::can_see_rating_form_page() ) {
        wp_die( "Sorry, you do not have permission to view this page" );
    }

    if( isset( $_POST['ewzmode'] ) ){
      try {
          ewz_process_rating_form_input();
      } catch( Exception $e ) {
            $message .= $e->getMessage();
            $message .= "\n";
      }
    }
    try {
        $user_arr =  ewz_get_judge_opt_array();
        array_unshift( $user_arr, array( 'value' => "0", 'display' => "All Logged-In Users" ) ); 
        array_unshift( $user_arr, array( 'value' => "-1", 'display' => "-- Select --" ) ); 

        $s_options = Ewz_Rating_Scheme::get_rating_scheme_opt_array();
        array_unshift( $s_options, array('value'=>'', 'display'=>'-- Select Rating Scheme --' ));
        $schemes_list = ewz_option_list( $s_options );
        try {
            $rating_forms = ewz_setup_ratings( $user_arr, 
                                               array_values( Ewz_Rating_Form::get_all_rating_forms( 'can_edit_rating_form_obj' ) ) );
        } catch( Exception $e ) {
            $rating_forms = array();
            $message .= $e->getMessage();
            $message .= "\n";
        }

        /*******************************/
        /* Pass the data to Javascript */
        /*******************************/
        $ewzG = array( 'rating_forms' => $rating_forms );
        $ewzG['ajaxurl'] = admin_url('admin-ajax.php', (is_ssl() ? 'https' : 'http'));
        $ewzG['load_gif'] = plugins_url( 'images/loading.gif', dirname(__FILE__) ) ;
        $ewzG['userlist'] = ewz_option_list( $user_arr );
        $ewzG['message'] = wp_kses( $message, array( 'br' => array(), 'b' => array() ) );
        $ewzG['helpIcon'] = plugins_url( 'images/help.png', dirname( __FILE__ ) );
        $ewzG['webforms'] = ewz_html_esc( array_values( Ewz_Webform::get_webform_titles('can_view_webform' )) );
        $ewzG['schemes'] = ewz_html_esc( Ewz_Rating_Scheme::get_all_rating_schemes() );
        $ewzG['schemes_list'] =  $schemes_list;
        $ewzG['nonce_string'] = wp_nonce_field( 'ewzadmin', 'ewznonce', true, false );
        $ewzG['jsvalid'] = Ewz_Base::validate_using_javascript();
        $ewzG['errmsg'] = array(
                'warn' => '*** WARNING ***' ,
                'reallydelete' => 'Really delete the entire rating form?',
                'noundo' => 'This action cannot be undone',
                'hasitems' => 'Deleting this rating form will also delete ALL its associated ratings.',
                'webform' => 'At least one webform must be selected. You may narrow the selection further once that is saved.',
                'judge' => 'At least one judge must be selected in order to open the rating form.',
                'formTitle' => 'Please enter a title for the rating form.',
                'formIdent' => 'Each rating form must have an identifier that starts with a lower case letter
                    and consists only of lower case letters, digits, dashes and underscores.'
            );


        // use of $ewzG1.var = ewzG  is a hack to get around the fact that wp_localize_script
        // runs html_entity_decode on scalar values.  Our data is already processed where it needs to be,
        // and some of it contains html entities which should not be decoded.
        wp_localize_script( 'ewz-admin-rating-forms', 'ewzG1',  array( 'gvar'=>$ewzG ) );

    ?>
 <div class="wrap">
    <h2>EntryWizard Rating Form Management</h2>
    <p><img alt="" class="ewz_ihelp" src="<?php print  $ewzG['helpIcon']; ?>" onClick="ewz_help('rforms')"> &nbsp;
        A rating form may be inserted into any page using the shortcode &nbsp;
         <b>&#91;ewz_show_rating&nbsp;&nbsp;identifier="xxx"&#93;</b>
         &nbsp; where xxx is the identifier you created for the form
    </p>
         <p>( NOTE: ewz_show_upload and ewz_show_rating shortcodes may <b>not</b> be mixed on the same page ) </p>

    <p>Rating forms may be dragged to rearrange. &nbsp;  &nbsp;
       <button type="button" class="button-secondary" id="ratingforms_save1_" onClick="save_ratingform_order()">Save Order of Rating Forms</button>
    </p>

    <div id="ewz_management">
        <br>  
    </div>

    <div id="help-text" style="display:none">
       <!-- HELP POPUP Rating Forms -->
       <div id="rforms_help" class="wp-dialog ewz-help" >
          <p>The EntryWizard "Rating" system is designed for viewing and/or judging the uploaded images. </p>
          <p>The Rating Form is generated by the 'ewz_rating' shortcode:  <b>&#91;ewz_show_rating&nbsp;&nbsp;identifier="xxx"&#93;</b>
         &nbsp; where xxx is the identifier you created for the rating form  
         ( note that there must be <u>no space beween the opening square bracket and the shortcode name</u> )</p>
         <p>NOTE 1: ewz_show_upload and ewz_show_rating shortcodes may <b>not</b> be mixed on the same page </p>
         <p>NOTE 2: Depending on your web hosting setup, there may be limitations on how much data a judge can save at one time. 
            Judges should be reminded to save frequently.  A Settings option allows for a popup reminder to do this. </p>
            <p>NOTE 3: Judges (and administrators) should always refresh the page after a period of inactivity, before doing any more work.</p>
             <p>-----------------</p>
          <p>A Rating Scheme describes the format of the rating form the way a layout describes the format of a WebForm, and each Rating Form must be assigned a previously-created Rating Scheme.
             Because it deals with the fields filled out when the image was uploaded, a rating scheme must be associated with a specific layout, and may only be used for items uploaded using that layout.</p>
          <p>Rating forms may be dragged to rearrange.  Click "Save Order of Rating Forms" to preserve the order for your next visit.</p>
          <p>The Rating Form is visible to, and editable by, anyone who has EntryWizard permissions to assign the layout associated with its Rating Scheme. Downloading the spreadsheets requires permission to download from any webform with the associated layout.</p>    
             <p>-----------------</p>
             <p>There are also some more parameters that may be added to the shortcode if you wish.  Here is the full list of parameters:
             <ul class="ewz_lpad"><li><b>identifier</b> e.g. identifier="competition2"  -- the identifier you created for the rating form.  
                     This parameter is required in all cases.</li>
             <li><b>item_ids</b> e.g. item_ids="256,278,300"  -- a comma-separated list of wordpress item_ids. 
                     If this parameter is present, only the listed items will be displayed. 
                     The easiest way to obtain the item_ids is to include "WP Item ID" in your spreadsheet, using the 
                     "Extra Data For Display in Spreadsheet" section of the Rating Scheme page.</li>
             <li><b>judge_ids</b> e.g. judge_ids="256,278,300" 
                  -- a comma-separated list of user_ids, which must be the Wordpress ID's of judges with access to the rating form.
                 If this parameter is present, judges <u>not</u> in the list will see nothing at all ( not even a "you do not have permission" message).
                 If it is not present, all judges specified in the rating form will be allowed to see the form. 
                 <br>Judge user_id's are shown in the judge selection box. </li>
             <li><b>rf_num</b>  e.g. rf_num="2" -- this parameter is <u>required if more than one ewz_show_rating
                  shortcode is present</u> on the page. If it is used, the "Finished" and "Using the Rating Form" buttons will not be displayed.<br>
                 Each shortcode must have a different rf_num parameter.</li>
             <li><b>view</b>   view="read", view="secondary" or view="rate"  -- specifies if the view is to allow input or just be "read-only".
                 "read" creates a totally read-only view, in which all ratings from <u>all</u> judges are displayed. 
                  "secondary" creates a view where only fields with the "secondary" box checked may be changed, the rest are read-only. 
                  The default is "rate", which is the normal view showing all fields except those with the  "secondary" box checked. If there is no
                  "view" parameter, "rate" is assumed.<br>
                 The "divide between judges" field is ignored for the "read" view, but honoured for "rate" and "secondary" views.</li>
            </ul>
              </p>
              <p>When you edit a page, you should see a drop-down list of "EWZR" rating forms. 
                 Choose the "simple" option if you are just creating a single shortcode on  the page, with the normal "rate" view  and no 
                 limitations of items or judges beyond those set on the rating_form page.<br>  
                 For anything else, choose the "general" option and it will ask you for all the required inputs.
              </p>  
              <p><u>Always check the final page view</u>, just in case of errors in the shortcode, item selection, judge assignment, etc. 
                  
                 Since the person creating the rating form would not normally be a judge, it would be very useful to have a plugin installed
                 that allows an administrator to see the site as another user ( e.g. User Switching ). 
             </p>
             <p> &nbsp;
             </p>
       </div>

       <!-- HELP POPUP Rating Forms ~ Title -->
       <div id="title_help" class="wp-dialog ewz-help" >
            <p>Each rating form needs a title which will be displayed at the top of the form.</p>
       </div>

       <!-- HELP POPUP Rating Forms ~ Identifier -->
       <div id="ident_help" class="wp-dialog ewz-help" >
                 <p>Each rating form also needs a short (no more than 15 characters) but unique identifier which is used in the shortcode. </p>
            <p>It must consist of letters, digits or underscores only ( no spaces ).</p> 
            <p>No two rating forms may have the same identifier.</p>
            
       </div>
       <!-- HELP POPUP Rating Forms ~ Item Selection -->
       <div id="iselect_help" class="wp-dialog ewz-help" >
            <p>In this section, you select the items that  will be displayed in the rating form.  
               You are required to select one or more WebForms. You may optionally check "User's own images only". 
            </p>
            <p>Once the rating form has been saved, you will also be able to restrict the images to just items that 
               match any drop-down options associated with the <u>layout used for the image upload</u>.  
               ( You may find that using these options takes 
               more resources on the server. ) Currently only fields that are drop-down option lists are available here.
            </p>
            <p>Items will normally be displayed ordered by their wordpress item_id ( usually the order in which they were uploaded ).<br>
               This will often result in all or most of one person's images being shown consecutively.<br>
               If you check "Shuffle Item Order", a new number is generated from the item_id by "moving" the last digit to the start,
               and the items are sorted by this number. <br>This is not a random shuffling, but it does create a predictable order 
               that can be repeated if a judge refreshes a page.
            </p> 
            <p>It is inadvisable to change any of these settings once any judges have created ratings.</p>               
       </div>
       <!-- HELP POPUP Rating Forms ~ Access -->
       <div id="access_help" class="wp-dialog ewz-help" >
            <p>Here you select who may see the rating form.  "All Logged-In Users" would not normally be 
               checked for a real "judging".  
               You would normally use it in combination with "Users Own Images" above, to show users a list of items they have uploaded. 
            </p>
            <p>Installing EntryWizard automatically creates a new Wordpress role 'EntryWizard Judge'. You may 
               select one or more users who have been assigned that role, and they will see all items matching the item selection above.
            </p>
            <p>Since the person creating the rating form would not normally be a judge, it would be very useful to have a plugin installed
               that allows an administrator to see the site as another user ( e.g. User Switching ).  Without that, you would have to
               make the administrator a judge, check the page view (which you should always do), and then remove the administrator 
               from the judge list.
            </p>
            <p>If you wish to use already-registered users as judges, you may also find it helpful to install a plugin that lets you 
               give multiple roles to users.
            </p>
            <p>The "ID" values displayed in the judge selection box are the values assigned by Wordpress.  You may need them if you
                wish to create any "tie breaker" or other such forms limited to particular judges ( see the general Rating Form help 
               item at the top of this page ).
            </p>
       </div>

       <!-- HELP POPUP Rating Forms ~ Open -->
       <div id="open_help" class="wp-dialog ewz-help" >
           <p>When this is checked, users with the right access will be able to see the rating form.  Otherwise 
              they will see just a message saying the form is not currently open.
           </p>
       </div>

       <!-- HELP POPUP Rating Forms ~ Data Management -->
       <div id="data_help" class="wp-dialog ewz-help" >
           <p>When judges with "EntryWizard Judge" role have been selected, this area shows a summary of how many items 
              they have rated, and whether they have finished.</p>
           <p>When "All Logged-in Users" has been selected under Access Control, the area simply shows the total number of ratings saved by all users.
              This will be 0 if the form is for display only, and contains no input fields</p>
           <p>Because updating this puts a load on the server, what you see may not always be up-to-the-minute.  
              When you require exact information, click "Recalculate".
           </p>
           <p>You may, if necessary, delete all of a judge's ratings for this rating-form.</p>
           <p>If a judge has indicated they are finished, but you wish them to make further changes, click the "reopen" button 
                  to allow them access again.</p>
       </div>
       <!-- HELP POPUP Rating Forms ~ Download -->
       <div id="download_help" class="wp-dialog ewz-help" >
           <p>When there is more than one judge, there are two possible formats for the downloaded spreadsheet.  
                  <ol><li><b>1 row per rating: </b>If there are 3 judges, each uploaded item will get 3 rows in the spreadsheet, one for each judge's inputs.</li>
                      <li><b>1 row per item: </b>There is only one row for each uploaded item, and extra columns are added for the judges' inputs.
                  To use this format, your rating scheme <u>must allow a blank column for each additional judge</u> immediately after each judge input column.<br>
                  For instance, if you have 3 judges, with each being asked for a score and a comment, the two columns right after your score column must be blank, 
                  and the two columns right after your comment column must be blank
                     </li>
                  </ol>
            </p>
            <p>With only one judge, the two formats should look the same. </p>
            <p>If the "divide" flag has been set for a field in the rating scheme, the id of the judge to whom it was displayed is shown in parentheses beside 
                  the field, or, if the judge has their own column, a star is shown beside the field. </p>
            <p>If "All Logged-in Users" has been selected under Access Control, only the "1 row per rating" format is available. </p>
       </div>

       <!-- HELP POPUP Rating Forms ~ Add / Arrange Rating Forms -->
       <div id="rfsort_help" class="wp-dialog ewz-help" >
                  <p>Add a New Rating Form creates a new Rating Form for the Rating Scheme you select 
                     ( provided there is at least one Webform associated with it ). You then need to:
              <ul class="ewz_lpad"><li>Give it a name and identifier</li>
                  <li>Select the WebForm(s) containing the items to be rated.</li>
                  <li>Select who is to be allowed access.</li>
                  <li>Check the "Open Rating" box when you are ready to allow rating.</li>
                  <li>Save your changes.</li>
                  <li>After saving, you may be able to refine your item selection, depending on the layout.</li>
              </ul>
           </p>
           <p> Rating Forms may be dragged up or down to rearrange them. Clicking "Save Order of Rating Forms" will save the order, 
               and the new order will subsequently be used for this page.
           </p>    
       </div>
    </div><!-- help -->
</div><!-- wrap -->

     <?php

       } catch( Exception $e ){
            wp_die( $e->getMessage() );
    }
} // end function ewz_rating_form_menu


