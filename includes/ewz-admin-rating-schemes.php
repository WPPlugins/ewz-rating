<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . 'classes/ewz-base.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-exception.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-field.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-layout.php' );
require_once( EWZ_RATING_DIR . 'classes/ewz-rating-scheme.php' );
require_once( EWZ_RATING_DIR . 'classes/validation/ewz-rating-scheme-input.php' );
require_once( EWZ_PLUGIN_DIR . 'includes/ewz-common.php' );
require_once( EWZ_CUSTOM_DIR . 'ewz-custom-data.php' );


/**
 * Unset all empty items in an array 
 * 
 * @param   Array $arr
 * @return  $arr with all empty members removed
 **/
function ewz_remove_empty( $arr ){
    assert( is_array( $arr ) );
    foreach( $arr as $n => $item ){
        if( empty( $item ) ){
            unset( $arr[$n] );
        }
    }
    return $arr;
}

/**
 * Process the input data
 * NB: not normally used, just a fallback if the ajax call fails for some reason
 *     Can probably be deleted 
 **/
function ewz_process_rating_scheme_input()
{
    $input = new Ewz_Rating_Scheme_Input($_POST);
    $pdata = $input->get_input_data();
    // create empty values for 'restrictions' and 'extra_cols' if they dont exist
    if ( !array_key_exists( 'restrictions', $pdata ) ) {
        $pdata['restrictions'] = array( );
    }
    if ( !array_key_exists( 'extra_cols', $pdata ) ) {
        $pdata['extra_cols'] = array( );
    }

    // set up the 'pg_column' field values froom the 'forder_f...' inputs
    foreach ( $pdata['forder'] as $col => $value ) {
        $mat = array( );
        // The index of a new field in $pdata is "Xn" where n is the number of fields
        // existing fields have index equal to their field_id
        preg_match( '/forder_f(X?\d+)_c(X?\d+)_/', $value, $mat );
        assert( 3 == count( $mat ) );
        $f_ident = $mat[2];
        if( isset( $pdata['fields'][$f_ident] ) ){
            $pdata['fields'][$f_ident]['pg_column'] = $col;
        }

        // ignore "append" in first column
        if( ( $col == 0 ) && $pdata['fields'][$f_ident]['append'] ){
            $pdata['fields'][$f_ident]['append'] = false;
        } 
    }
    $rating_scheme = new Ewz_Rating_Scheme( $pdata );
    $rating_scheme->save();   // saving sets ids for new fields
}

/**
 * Prepare the input Ewz_Rating_Schemes array for display by adding extra data needed by the javascript.
 *
 * @param  array of Ewz_Rating_Schemes  
 * @return  array of Ewz_Rating_Schemes  -- the input array with extra data added
 **/
function ewz_setup_schemes( $all_schemes )
{
    assert( is_array( $all_schemes ) );
    foreach ( $all_schemes as $k => $scheme ) {
        if( !isset( $scheme->item_layout->layout_name ) || $scheme->item_layout->layout_name == null ){
            $scheme->item_layout->layout_name =' *** ERROR *** layout not found';
        }
        // add an "nth_field" array component to the scheme to specify the field order
        // -- saves having to sort by pg_column in javascript
        $scheme->nth_field = array();
        foreach ( $scheme->fields as $fid => $f ) {
            $scheme->nth_field[$f->pg_column] = $fid;
        }

        $scheme = ewz_html_esc( $scheme );    // don't want the restr_options or color_opts escaped, so do it now.
        $bcol = $scheme->settings['bcol'];
        $fcol = $scheme->settings['fcol'];
        $scheme->bcol_options  = ewz_option_list( Ewz_Rating_Scheme::get_bcolor_opts($bcol) );
        $scheme->fcol_options  = ewz_option_list( Ewz_Rating_Scheme::get_fcolor_opts($fcol)  );


        // For generating the restrictions 
        $scheme->restr_options = array();
        foreach ( $scheme->fields as $fid => $field ) {
            if( $field->has_option_list() ){
                $scheme->field_names[$field->rating_field_id] = ewz_html_esc( $field->field_header );
                $scheme->restr_messages = array();
                       
                foreach( $scheme->restrictions as $r => $restriction ){
                    if( isset( $restriction[$fid] ) ){ 
                        $list = $field->get_rating_field_opt_array( $restriction[$fid]  );
                    } else {
                        $list = $field->get_rating_field_opt_array( array() );
                    } 
                    $scheme->restr_options[$r][$fid] = ewz_option_list(  $list, true );
                    $scheme->restr_messages[$r] =  $restriction['msg']; 
                }
                // use "-1" index for a new restriction
                $scheme->restr_options[-1][$fid] =  ewz_option_list( $field->get_rating_field_opt_array( array() ), true );
            }
        }
        // if no fields were found with an option list, remove the restriction
        foreach( $scheme->restr_options as $restr_num => $restr_opts ){
           $scheme->restr_options[$restr_num] = ewz_remove_empty( $restr_opts );
        }
   }
   return $all_schemes;
}


/**
 * Callback for add_submenu_page
 * 
 * Generates the $ewzG variable and passes it to javascript
 * Actually outputs the html for the rating schemes admin page 
 * including all the help text.
 **/
function ewz_rating_scheme_menu()
{
    if ( !Ewz_Rating_Permission::can_see_scheme_page() ) {
        wp_die( "Sorry, you do not have permission to view this page" );
    }
    $message = '';

    if( isset( $_POST['ewzmode'] ) ){
        try {
            ewz_process_rating_scheme_input();
        } catch( EWZ_Exception $e ){
            $message = $e->getMessage();
        }   
    }  
    try {

        //  Drop-down selection of layouts
        $layouts = ewz_html_esc( array_values( Ewz_Layout::get_all_layouts( 'can_edit_layout' ) ) );
        $l_options = Ewz_Layout::get_layout_opt_array( 'can_assign_layout' );
        array_unshift( $l_options, array('value'=>'', 'display'=>'-- Select Layout --' ));
        $layout_options = ewz_option_list( $l_options );
        // Schemes
        $all_schemes = ewz_setup_schemes( array_values(  Ewz_Rating_Scheme::get_all_rating_schemes() ) );
        $s_options = Ewz_Rating_Scheme::get_rating_scheme_opt_array( 'can_assign_layout' );
        array_unshift( $s_options, array('value'=>'', 'display'=>'-- Select Scheme --' ));
        $scheme_options = ewz_option_list( $s_options );

        // TODO: allow specification of sort order?

        // Set up the ewzG variable to pass to javascript
        $ewzG = array( 'item_layouts' => $layouts );
        $ewzG['schemes']= $all_schemes;
        $ewzG['layout_options'] = $layout_options;
        $ewzG['scheme_options'] = $scheme_options;
        $ewzG['message'] = $message;

        $ewzG['nonce_string'] = wp_nonce_field( 'ewzadmin', 'ewznonce', true, false );
        $ewzG['helpIcon'] = plugins_url( 'images/help.png', dirname( __FILE__ ) );
        $ewzG['load_gif'] = plugins_url( 'images/loading.gif', dirname( __FILE__ ) );
        $ewzG['empty_img'] = array();  // needed for compatibility with upload
        $ewzG['empty_lab'] = array('label' => '');  
        $ewzG['empty_str'] = array( "fieldwidth" => EWZ_MAX_FIELD_WIDTH,
                                    "maxstringchars" => EWZ_MAX_STRING_LEN,
                                    "ss_col_fmt" => "-1" 
                                  );
        $ewzG['empty_opt'] = array( "value" => "", "label" => "", "maxnum" => 0 );
        // $ewzG['empty_rad'] = array( );
        $ewzG['empty_chk'] = array( "maxnum" => 0,"chklabel" => '',"xchklabel" => '' );
        $ewzG['empty_scheme'] = new Ewz_Rating_Scheme( array('item_layout_id' => 0, 'scheme_name'=>'NEW SCHEME', 
                                                       'fields' => array(), 'forder'=>count($all_schemes) )  );
        $ewzG['empty_scheme']->nth_field = array();
        $ewzG['empty_scheme']->restr_options = array();
        $ewzG['empty_scheme']->restr_options[-1] = array();
        $ewzG['empty_scheme']->bcol_options = ewz_option_list(Ewz_Rating_Scheme::get_bcolor_opts());

        $ewzG['empty_scheme']->fcol_options = ewz_option_list(Ewz_Rating_Scheme::get_fcolor_opts());
        $ewzG['jsvalid'] = Ewz_Base::validate_using_javascript();
        $ewzG['display'] = ewz_html_esc( Ewz_Rating_Scheme::get_all_display_headers() );
        $ewzG['imgSizeNote'] = 'NOTE: these maxima should normally match the actual maximum image dimensions, which should be displayable in a browser window. See the Image Display help item for more detail.';
        $ewzG['do_warn'] = Ewz_Base::warn_on_leaving();
        $ewzG['errmsg'] = array(
            'deletehasratings' => 'There are rating forms attached to this scheme which have saved ratings. Please delete those rating forms first.',
            'deletehasratingforms' => 'This action will also delete all the rating forms using this scheme. Those rating forms have no saved ratings.' ,
            'deleteconfirm' => 'Really delete the entire scheme? If they have no attached ratings, this will also delete any rating-forms that use it . ' ,
            'schemename' => 'Please enter a name for the rating scheme.' ,
            'nofields' => 'At least one field is required.',
            'colhead' => 'Each field in a rating scheme must have a column header.' ,
            'ident' => 'Each field must have an identifier that starts with a letter and ' .
                       'consists only of letters, digits, dashes and underscores. At most 15 characters.' ,
            'optlabel' => 'Each option in an option list must have a label for the web page.' ,
            'optvalue' => 'Each option in an option list must have a value.' ,
            'option' => 'Option values may consist only of letters, digits, dashes, periods and underscores.' ,
            'chklabel' => 'Checkbox label values may consist only of letters, digits, dashes, periods, spaces and underscores.' ,
            'textlabel' => 'Label values may consist only of letters, digits, dashes, periods, spaces and underscores.' ,
            'labvalue' => 'A Label must have a value to display.' ,
            'optioncount' => 'A drop-down selection must contain at least one option' ,
            'restrmsg' => 'A restriction must have a message to show to the user.' ,
            'maxnumchar' => 'Each text entry must have a maximum number of characters.' ,
            'all_any' => 'If all the items in a restriction allow `Any` value, ' .
                          'the restriction has no effect. Please change or remove it.' ,
            'one_any' => 'If only one item in a restriction is different from `Any`, ' .
                         'the restriction has the same effect as removing the forbidden item from the selection list, ' .
                         'but is much slower. Please remove the restriction, and remove the item from the selection list instead.' ,
            'one_spec' => 'In a restriction, at least one item editable by the judge must be different from `Any`',
            'maximgw' => 'The max image width should consist of 3 or 4  digits only.' , 
            'maximgh' => 'The max image height should consist of 3 or 4  digits only.' , 
            'minpad' => 'The minimum padding value should consist of 2 or 3 digits only.' , 
            'append2' => 'You have set a column to be appended to a secondary column. This will have no effect except in a secondary view. Are you sure?',
        );
        // use of $ewzG1.var = ewzG  is a hack to get around the fact that wp_localize_script
        // runs html_entity_decode on scalar values.  Our data is already processed where it needs to be,
        // and some of it contains html entities which should not be decoded.  Also booleans get incorrectly interpreted.
        wp_localize_script( 'ewz-admin-rating-schemes', 'ewzG1',  array( 'gvar'=>$ewzG ));
?>
 <div class="wrap">
    <h2>EntryWizard Rating Scheme Management</h2>
        <div class="ewz_inotes">
    </div>
    <p><img alt="" class="ewz_ihelp" src="<?php print  $ewzG['helpIcon']; ?>" onClick="ewz_help('general')"> &nbsp;
     Images rated under one rating scheme <u>must all have been uploaded via webforms with the same layout</u>.
        </p>
    <p>Schemes may be dragged to rearrange. &nbsp;  &nbsp;
    <button  type="button" class="button-secondary ewz_orderbtn" id="schemes_save1_" onClick="save_scheme_order()">Save Order of Schemes</button>
    </p>

    <div id="ewz_schemes">
    <br>
    </div>

    <div id="help-text" style="display:none">
       <!-- HELP POPUP Schemes -->
       <div id="general_help" class="wp-dialog ewz-help" >
            <p>The EntryWizard "Rating" system is designed for viewing and/or judging the uploaded images. 
               The Rating Form is the screen generated by the 'ewz_rating' shortcode. 
               It displays selected images together with whatever information you wish the judges to see, and any 
               input fields you wish the judges to fill out.</p>
            <p>So long as there is only one rating form on the page, the judges may, with a few exceptions, sort the images by any of the fields displayed or filled out.</p>
            <p>A Rating Scheme describes the format of the rating form the way a layout describes the format of a WebForm.</p>
            <p>It is, however, just a little more complicated. For each item you have the possibility not only of asking for various forms of input information, but also of displaying, in a read-only fashion, any of the fields originally uploaded with the item, and/or associated "extra" data fields.  </p>
            <p>Because it deals with the fields filled out when the image was uploaded, a rating scheme must be associated with a specific layout, and may only be used for items uploaded using that layout.</p>
            <p>A rating Scheme is visible to, and editable by, anyone who has EntryWizard permissions to edit its associated Layout.</p>

       </div>

       <!-- HELP POPUP Schemes ~ Scheme Name -->
       <div id="name_help" class="wp-dialog ewz-help" >
            <p>The scheme requires a unique name which will appear on a dropdown menu when you come to creating the rating form.</p>                 
       </div>

       <!-- HELP POPUP Schemes ~ Fields -->
       <div id="field_help" class="wp-dialog ewz-help" >
            <p>To display any field read-only to the judges, drag it from the left-hand column to the right.</p>
            <p>You will then be able to click on it, open the box, and fill out some information. </p>
            <p>You can specify  the header you wish to appear over the column, 
               a short (up to 15 characters ) identifier that will be used as a column header in the downloaded spreadsheet, 
               and the column of the spreadsheet in which the data is to appear. 
            </p>
            <p>A field dragged in this way but <u>not yet saved</u> may be dragged back to the left. 
               Once saved, the field gets a "Delete" button which must be clicked to remove it.</p>
            <p>Each field corresponds to a column in the judging view. 
               So long as there is only one rating shortcode on a page, the judge may sort the view by any non-image column that 
               does not contain appended fields</p>
            <p>Note that data labelled "WP User" is visible only to admins with the wordpress "list_users" capability</p>
       </div>

       <!-- HELP POPUP Schemes ~ Fields ~ Item Affected By Restrictions -->
       <div id="restr1_help" class="wp-dialog ewz-help" >
            <p>Restrictions may be applied to rating fields in the same way they are applied to upload fields.</p>  
            <p>If the fields for a particular item match what is specified in the restriction, an error message will be generated 
               and the rating will not be saved.</p>
            <p>So you may, for instance, specify that if a "Comment Requested" column was checked in the original image upload, 
               the Comment column in the rating may not be left blank.
            </p> 
            <p><b>NOTE:</b> Restrictions will <b>not</b> be applied if the shortcode parameter "view" has been set to 
                            "secondary".
            </p>
       </div>

       <!-- HELP POPUP Schemes ~ Fields ~ Column Header for Web Page -->
       <div id="webcol_help" class="wp-dialog ewz-help" >
           <p>This is the text that will appear at the top of the column on the rating page.</p>    
       </div>

       <!-- HELP POPUP Schemes ~ Fields ~ Data Type -->
       <div id="dtype_help" class="wp-dialog ewz-help" >
           <p>For a <u>read-only</u> field, dragged in from the left-hand column, the data type shows the origin of the data displayed. 
              For an input field, it specifies the type of field -- text, checkbox, etc.</p> 
          <p>Image files display as thumbnails with links to the full-size image in a separate window.</p>    

           <p>Each <u>input</u> field contains one of the following types of user input:</p>
                        <ol>
                            <li><p><b>A text input:</b> The user may input any  piece of text,
                                   subject to the length restrictions you set.</p>
                                   <p>  To help in generating the
                                       one-item-per-line spreadsheet download, there are two characters that
                                       may not be used in such input: <b>~</b> and <b>|</b>.  
                                       If these appear in the input, they will be replaced by <b>_</b>.</p>
                                </li>
                            <li><b>A drop-down option list:</b> You choose the values that appear.
                                The user must select one of these values. </li>
                            <li><b>A checkbox:</b> 
                                The user may either check the box or leave it blank.</li>
                        </ol>

           <p>Users may alter the data they enter so long as the rating form is open. </p>

       </div>

       <!-- HELP POPUP Schemes ~ Fields ~ Field Identifier -->
       <div id="ident_help" class="wp-dialog ewz-help" >
           <p>The field identifier is used as the header for the column in the downloaded spreadsheet. At most 15 characters.</p>    
       </div>

       <!-- HELP POPUP Schemes ~ Fields ~ Spreadsheet Column -->
       <div id="sscol_help" class="wp-dialog ewz-help" >
           <p>You may specify in which column this data is to appear in the downloaded spreadsheet.</p> 
           <p><b>NOTE:</b> If you plan to use the "one line per item" spreadsheet format, and have more than one judge, 
              <u>extra blank columns need to be allowed for</u>. </p>
             <p>Each column assigned to an item the judges fill out must be 
              followed by N-1 blank columns, where N is the number of judges. Don't forget to take into account any 
              columns assigned in the Extra Data area.<br>
              To see a summary of the assigned columns for the rating scheme, click the button below, next to the Save Changes and Delete buttons.
           </p> 
       </div>

       <!-- HELP POPUP Schemes ~ Fields ~ Required -->
       <div id="req_help" class="wp-dialog ewz-help" >
            <p>When this is checked, the field is required, and a rating in which this field has not been set will generate an error message.</p>
            <p>Note that "Requiring" a checkbox means requiring it to be checked. 
               In that case, the checkbox may not have a "Maximum number that may be checked", and that item will be disabled.</p>
       </div>

       <!-- HELP POPUP Schemes ~ Fields ~ Append -->
       <div id="append_help" class="wp-dialog ewz-help" >
           <p>Checking this forces the field to be displayed below the previous one instead of in its own column.</p>    
           <p>May be used to display an image title below its thumbnail.</p>   
           <p>This feature is also helpful in narrowing the rating page so that it can be displayed next to the image page even on a single monitor setup.</p>
           <p>Note that column sorting will not work on appended columns of this kind.</p>    
       </div>

       <!-- HELP POPUP Schemes ~ Fields ~ Divide -->
       <div id="divide_help" class="wp-dialog ewz-help" >
           <p>Some camera clubs make it a practise to require a comment on an image from only one of several judges.  This option
              makes it possible to do that.</p>
           <p>If the box is checked, then for each item only one judge will see the field. The others will see nothing.</p>    
           <p>Judge number 1 will see an item only if the item_id has remainder 1 when divided by the number of judges.</p>    
           <p>Judge number 2 will see an item only if the item_id has remainder 2 when divided by the number of judges, .. etc...<br>
              Judges are numbered in the order in which the selected judges appear in the judge selection list.</p>    
           <p>Restrictions are enforced only on fields that are displayed.</p>  
           <p>Thus you may create a "Comment" text input, make it required, and check the "divide" flag.  
              Every item should then get a comment from one, and only one, judge. </p>
           <p>If you have been using the approach of putting a "comment requested" field in the upload layout, you could do this:</p>
               <ul class="ewz_lpad"><li> Display the "comment requested" field to the judges with "divide" checked, so only one judge sees it for each item</li>
                <li>Leave the Comment field optional. </li>
                <li>Add a restriction that the combination "Comment Requested is checked"  and "Comment is blank" is not acceptable</li>
               </ul>
           <p style="padding-left: 35px;">The comment will then be required if the "comment requested" flag was checked, and otherwise optional.</p>  
           <p>The division between judges may not be perfectly even, but in most cases should be comparable. You can see the division by downloading
              the spreadsheet - the judge who can see the field is shown in parentheses in the field column.</p>
           <p> </p>
           <p><b>If you use this feature, any change to the judge selection will change the division of this field between them.</b>
              If a substitution is necessary after the first ratings have been created, it would be better to give the new judge 
              the old judge's login.</p>
           <p><u>This choice has no effect if "Image Owner" is among your judge selections.</u></p>
       </div>

       <!-- HELP POPUP Schemes ~ Fields ~ Secondary -->
       <div id="secondary_help" class="wp-dialog ewz-help" >
           <p>Once you have some or all ratings created, you may need to obtain further information about an item from a judge.
              ( The most common situation would be needing to break ties. )</p>
           <p>In the normal rating view ( 'view="rate"' or no 'view' parameter in the shortcode -- see the Help at the top of the 
              rating forms page ), a field with this box checked is not displayed at all.</p>
           <p>Using the additional parameter 'view="secondary"' in the shortcode will display all the other fields read-only,
              but fields with "secondary" checked will be editable.  Thus you can ask a judge to choose which of several images is 
              the best, or add an additional score.  Normally you would also specify a list of item_ids, and perhaps a list of judge_ids.
           </p>
           <p><b>NOTE:</b> Restrictions may not be applied on fields with the "Secondary" flag set.</p>
       </div>

       <!-- HELP POPUP Schemes ~ Fields ~ Text Field ~ Maximum Number of Characters -->
       <div id="maxchar_help" class="wp-dialog ewz-help" >
            <p>The user will not be able to type more than this many characters in the text box.</p>
       </div>
       <!-- HELP POPUP Schemes ~ Fields ~ Text Field ~ Number of Lines of Visible Text -->
       <div id="textrows_help" class="wp-dialog ewz-help" >
            <p>If this is set to a number greater than one, a multi-line "text area" input field is generated,
               instead of a single-line text input.  In that case, the "Number of Characters Visible in One Line" is interpreted
               as the width of the text area.
            </p>
       </div>
       <!-- HELP POPUP Schemes ~ Fields ~ Text Field ~ Number of Characters Visible in One Line -->
       <div id="maxvis_help" class="wp-dialog ewz-help" >
          <p>This controls the width of the text box ( the number of characters ). </p>
          <p>If you have many fields, you may need to keep the total width of your form in mind
             when setting this value.
          </p>
          <p>On the other hand, a box that is smaller than the number of characters the user
             wishes to type is not very comfortable to use. If you need many more characters
             than the width of your form allows, try setting the "Number of Lines" to something
             bigger than 1. That creates a "textarea" instead of a normal text input.
          </p>
       </div>
       <!-- HELP POPUP Schemes ~ Fields ~ Text Field ~ Spreadsheet Column for Formatted Text -->
       <div id="frmtstr_help" class="wp-dialog ewz-help" >
          <p>If a column is selected, that column in the spreadsheet will contain this item
             re-formatted so that each word starts with a capital letter.
          </p>
          <p>Useful for titles used in displays.</p>
          <p>( The text is shown unchanged if it already contains both upper and lower-case characters. )</p>
       </div>
       <!-- HELP POPUP Schemes ~ Fields ~ Check Box ~ Maximum Number-->
       <div id="chkmax_help" class="wp-dialog ewz-help" >
          <p>The maximum number of items in the rating form that may have this checkbox checked by a single judge.</p>
       </div>
       <!-- HELP POPUP Schemes ~ Fields ~ Check Box ~ Labels-->
       <div id="chklabel_help" class="wp-dialog ewz-help" >
          <p>In a read-only view, by default a checked checkbox displays as "checked", and an unchecked on as " - ".  
             You may replace these with any text of your choice that consist only of letters, digits, dashes, periods, spaces and underscores.
          </p>
       </div>
       <!-- HELP POPUP Schemes ~ Fields ~ Fixed-Text Label-->
       <div id="textlabel_help" class="wp-dialog ewz-help" >
          <p>The text to display on the page.  If the field is "divided between judges", this text will display only to the judge assigned to the item.  Otherwise it will display the same to all judges.
          </p>
       </div>
       <!-- HELP POPUP Schemes ~ Fields ~ Option List ~ Options -->
       <div id="opt_help" class="wp-dialog ewz-help" >
           <p>For each option you create, you need to select: </p>
               <ul class="ewz_lpad">
                  <li><b>Label for Web Page:</b> the label that is displayed in the drop-down
                      list the user selects from.<br>
                      If you have many fields, you may need to keep the total width of your
                      form in mind when setting this value.<br>
                      May contain only letters, digits, dashes, periods and underscores</li>
                 <li><b>Value for Spreadsheet:</b> what you see in the corresponding
                      spreadsheet column when the user selects this item.<br>
                      May contain only letters, digits, dashes, periods and underscores<br>
                      (Behind the scenes, this is also the value stored in the database)<br>
                      <i>Hint:</i> When you set the Label, if the Value has not already been set it defaults 
                      to that of the Label. If you want different values for Label and Value, try setting the Value first.
                 </li>
              </ul>
              Use the 'X' button to delete an option.  
             To change the position of an option, click next to it to select it, then use the up/down arrows to move it.
          
       </div>

       <!-- HELP POPUP Schemes ~ Fields ~ Add A Field -->
       <div id="ftype_help" class="wp-dialog ewz-help" >
           <p>Click on one of these buttons to add a new input field that the judge may fill out.
              Click on the new field to open it up and set its parameters.
           </p> 
           <p>The "Fixed Text Label" is only going to be of much use if the field is to be divided among the judges.  In that case, it 
              will only be displayed if the item is assigned to the judge. So, for instance, you can create a label with the content 
             "Comment Required", and add a restriction that the comment field may not be blank if the label field is not blank.
              This allows you to have a comment field that is normally optional, but that is required for items assigned to the judge.</p>
            <p>Each field corresponds to a column in the judging view. 
               So long as there is only one rating shortcode on a page, the judge may sort the view by any non-image column that 
               does not contain appended fields</p>
       </div>
       <!-- HELP POPUP Schemes ~ Restrictions -->
       <div id="restr_help" class="wp-dialog ewz-help" >
           <p>Restrictions may be applied to rating fields in the same way they are applied to upload fields.</p> 
  
          <p>Normally, any combination of allowed entry field values is allowed.
              There may, however, be occasions where you wish to disallow some particular combination.
          </p>
          <p>You may, for instance, specify that if a "Comment Requested" column was checked in the original image upload,
             the Comment column in the rating may not be left blank.
          </p>
          <p>To do this, you may create a restriction forbidding the combination
             <i>Comment Requested = checked </i> and <i>Comment = Blank</i>, with the message
             "A comment is required for this item.".
          </p>
          <p>If a user clicks "Save" when any item has one of these forbidden combinations,
             your message will pop up and the rating will not be saved.
          </p>
          <p><br><b>Set up all your fields and click "Save Changes" first before adding
             any restrictions</b>.  Once a field has had a restriction placed on it,
             some items within the field may no longer be edited. These fields are
             indicated by a red outline. If you need to change them, you must first
             delete the restriction.
          </p>
          <p><b>NOTE:</b> Restrictions may <b>not</b> be applied on fields with the "Secondary" flag set.
             Restrictions will <b>not</b> be enforced if the shortcode parameter "view" has been set to 
                            "secondary"</p>
       </div>
       <!-- HELP POPUP Schemes ~ Restrictions ~ Restriction Message -->
       <div id="rmsg_help" class="wp-dialog ewz-help" >
           <p>If the data entered for the rating matches the restriction, nothing will be saved, and this is the message that will be shown to the user.</p>
       </div>
       <!-- HELP POPUP Schemes ~ Display ~ Rating Page Display -->
       <div id="rpage_help" class="wp-dialog ewz-help" >
             <p>You may opt to display: </p> 
             <ol>
             <li> In the bottom-left corner of the rating page, a box containing the total 
                  number of items to be rated, and the number of ratings currently saved by the judge</li>
             <li> At the top of the rating page, a button the judge may use to indicate that they have finished. 
                  When the judge clicks this, all items are checked to make sure no required fields have been left blank. 
                    Then, if everything is correct, 
                    <ul class="ewz_lpad"><li>The judge no longer has access to the page</li>
                        <li>The judge status in the Data Administration area shows "Finished", 
                            and a "Re-open for this judge" button appears.</li>
                    </ul>
                    Note that if there is no "required" field, the judge may click "Finished" at any time.  
                    In most cases, it would probably be a good idea to include at least one required field, 
                    even if it is simply a checkbox to indicate the item has been seen.        
               </li>
             </ol>
            <p> The above items will <u>not</u> be shown if the shortcode satisfies any of:</p> 
               <ul class="ewz_lpad"><li> The view parameter is not the default "rate" view. </li>
                   <li> Items are limited by an "item_ids" parameter. </li>
                   <li> The "rf_num" parameter is greater than zero </li>
               </ul>
            <p>Main Table Colors:<br><br>
               The row referring to the image currently displayed in the image window is highlighted,
                    and unsaved rows get a border around them.<br> 
                This means that the <u>border and background color of the rows is controlled by javascript, not by your theme</u>.<br>  
                If the default color scheme ( white background with highlighted rows in light gray and unsaved rows bordered in black )
                does not work with your theme,  try changing the values at the bottom of this section.
            </p>
       </div>
       <!-- HELP POPUP Schemes ~ Display ~ Large Image Display -->
       <div id="idisplay_help" class="wp-dialog ewz-help" >
          <h3>Please read this section carefully.</h3>
          <p>The maximum width and height set here are given as instructions to the browser. If the image does not fit within the size 
             parameters you set, it will be resized by the user's browser before displaying.  
             That may not be a very accurate process, and it will also waste bandwidth.</p>

          <p>The uploaded images themselves should ideally fit within the maximum width and height you specify here, without any resizing. 
             The size should be no larger than the <u>interior</u> size of the browser window used to view them.
             ( Browsers and operating systems vary in how much space is taken up by the borders and headers. )</p>

          <p>To ensure the images fit within the screen, you will probably set minimum pixel dimensions for any monitor used for judging.
             The plugin comes with a generic test imageto help you check this. See the Path to Test Image help item.
          </p>

          <p>For security reasons, browsers do not allow removal of the location bar in the image display.  To make sure
             the image is not too close to the ( usually white and visually distracting ) location bar, you can specify a minimum 
             amount of space above the image.  Dont forget to allow for this space and for the location bar itself when setting the 
             maximum image height.
          </p>
          <p>It's a tight fit, but if the maximum image dimensions are 1280x1024, a carefully-designed rating window with no sidebars
             can just be resized to fit beside the image window on a 1920x1200 monitor.  
          </p>
          <p>If you regularly have uploaded images that are too big to display like this, it might be a good idea to move them to another 
             folder, and replace them by downsized versions ( with the same filenames ) for judging. 
          </p>
       </div>

       <!-- HELP POPUP Schemes ~ Display ~ Test Image -->
       <div id="testimg_help" class="wp-dialog ewz-help" >
             <p>To aid in sizing the image window and correcting obvious colour management issues, a generic test image is provided 
             with the plugin.  However, this image is 3600x2880 pixels, which in most cases is far larger than required, and will be 
             downsized within the browser.  Also, if  your maximum dimensions do not have these proportions, the image will appear distorted.
             </p>
             <p>To provide your own test image for the scheme, place it somewhere within your Wordpress uploads folder, and enter the
             path here. If your test image includes monitor test patterns, as the generic one does, you may wish to include instructions 
             for their use on the page above the shortcode. 
             </p>
             <p>For example, if you upload your test image to: </p>
             <pre> &nbsp;  &nbsp;  your-base-directory/wp-content/uploads/my_site_images/1280x1024test.jpg</pre>
              <p>then in the box you would enter:</p>
             <pre> &nbsp;  &nbsp;  my_site_images/1280x1024test.jpg</pre>
             <p><u>Your test image should have the exact dimensions you specify as the maximum, and have a border that is very clearly 
                visible against your choice of background color.</u></p>
       </div>

       <!-- HELP POPUP Schemes ~ Extra Data -->
       <div id="xtra_help" class="wp-dialog ewz-help" >
           <p>In addition to the information entered by or displayed to judges, you may include several
            other items of information in the spreadsheet that you download.
           </p>
           <p>The source of the data is shown in parentheses. </p>
             <ul class="ewz_lpad"><li><b>"WP User data"</b> is information you control via the Wordpress Users menu. 
                     <u>Note that this data is only visible to admins with the right permission</u>.
                 </li>
                 <li><b>"EWZ Item data"</b> is information about an item stored by this plugin but not
                     actually set by the user.<br>
                     It may include data uploaded by the administrator via a .csv file
                     ( See the Data Management area on the WebForms page ).<br>
                     The WP Item ID is a numeric identifier created by Wordpress.  You will need this
                     if you wish to upload such a .csv file.
                 </li>
                 <li><b>"EWZ Webform data"</b> is mainly set by the administrator on the WebForms page. 
                     The WP Webform ID is a numeric identifier created by Wordpress.
                 </li>
                 <li><b>"Custom data"</b> is optional. Wordpress stores some information about a
                     user, but there are plugins, like CIMI User Extra Fields or S2Member,  that allow you
                     to add more information.<br>
                     If you have such a plugin, and if it provides a function to access the
                     information, you may tell EntryWizard about it -- see the "ewz-extra.txt"
                     file.
                 </li>
             </ul>
          
       </div>

       <!-- HELP POPUP Schemes ~ Add / Rearrange Schemes -->
       <div id="lsort_help" class="wp-dialog ewz-help" >
          <p>To create a new scheme, you may either create a brand-new one ( no fields to start with ), or copy an existing one. 
             Either way, after creating the scheme you need to:</p>
              <ul class="ewz_lpad">
                  <li>Give it a name</li>
                  <li>Choose which data you wish displayed ( read-only ) on the rating form, and drag those items 
                      from the left-hand column to the "Fields to be displayed in Rating" area. 
                      ( An unsaved field created in this way may be dragged back to remove it. )</li>
                  <li>Click on the items to expand, and give them names and identifiers.</li>
                  <li>Add any fields you wish to have filled in by the judge by clicking the relevant buttons below 
                      the "Fields to be displayed in Rating" area.</li>
                  <li>Set the size and placement of the image</li>
                  <li>Choose any extra data you wish displayed in the downloaded spreadsheet</li>
                  <li>Save your changes</li>
                  <li>If required, add restrictions after saving</li>
              </ul>
          
           <p> Rating Schemes may be dragged up or down to rearrange them. Clicking "Save Order of Schemes" will save the order, 
               and the new order will subsequently be used for this page and for the dropdown menus of rating schemes
               in the rating forms page
           </p>    
       </div>
   </div><!-- help -->
</div> <!-- wrap -->

<?php
              } catch( Exception $e ){
        wp_die( $e->getMessage() );
    }
}   // end function ewz_rating_scheme_menu


