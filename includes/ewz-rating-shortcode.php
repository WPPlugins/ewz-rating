<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

/* * ************************************* */
/* Generate and process the rating form */
/* * ************************************* */
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-base.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-exception.php' );
require_once( EWZ_RATING_DIR . 'classes/ewz-item-rating.php' );
require_once( EWZ_RATING_DIR . 'classes/ewz-rating-form.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-webform.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-layout.php' );
require_once( EWZ_RATING_DIR . 'classes/ewz-rating-scheme.php' );
require_once( EWZ_RATING_DIR . 'classes/validation/ewz-item-rating-input.php' );
require_once( EWZ_RATING_DIR . 'includes/ewz-rating-template.php' );
require_once( EWZ_RATING_DIR . 'includes/ewz-admin-rating.php' );
require_once( EWZ_PLUGIN_DIR . 'includes/ewz-common.php' );


/**
 * Display the rating form
 *
 * This is the function called by the shortcode
 *
 * @param   array   $atts   attributes passed to the shortcode
 * @return  string  $output html
 **/
function ewz_show_rating( $atts )
{
    assert( is_array( $atts ) );

    if ( !is_user_logged_in() ) {
        return 'Sorry, you must be logged in to see this content'; 
    }
       
    // sort params come in GET
    $orderby = '';    // column to sort by
    if( isset($_GET['orderby']) ){
        $orderby = (integer)$_GET['orderby']; 
    }  
    $order = '';      // 'asc' or 'desc'
    if( isset($_GET['order']) ){
        $order = $_GET['order'];
    }

    // enqueue here so the javascript is only loaded if the shortcode is used ( supported by WP 3.3+ )
    wp_enqueue_script( 'ewz-rating' );
    wp_enqueue_script( 'ewz-ucommon' );
    wp_enqueue_style( 'ewz-rating-style' );
    wp_enqueue_style( 'ewz-rating-user-style' );

    $errmsg = '';
    try{
        foreach( $atts as $key => $val ){
            if( !is_string( $key ) ){
                throw new Ewz_Exception( "Invalid  parameter in shortcode 'ewz_show_rating'" );
            }
            if( !in_array( $key, array( 'identifier', 'view', 'item_ids', 'judge_ids', 'rf_num') ) ){
                throw new Ewz_Exception(  "Invalid  parameter $key for shortcode 'ewz_show_rating'" );
            }
        }

        // Check for multiple instances of the shortcode on the page
        // this code is probably not foolproof for reading attributes, so only use it to warn about invalid rf_nums
        $content = get_the_content();
        $matches = array();

        preg_match_all('/(\[ *ewz_show_rating.*?\])/ms', $content, $matches);
        // rf_num needs additional checking if there is more than one shortcode on the page
        $atts['rf_num'] = ewz_check_atts( $atts, 'rf_num',  '/^\d*$/', '0' );
        $is_multiple = false;
        if( count( $matches[0] ) > 1  ){
            // terrible waste to do this for each shortcode, but cant see how to get around it
            $n = 1;
            $seen = array();
            foreach( $matches[0] as $match ){
                $mm = array();
                if( !preg_match('/rf_num/', $match ) ){
                    $mm[1]="0";
                } else {
                    preg_match('/rf_num\s*=\D*(\d+)\D/', $match, $mm); 
                    if( !isset($mm[1]) ){
                        $mm[1]="0";
                    }
                }
                if( ($n == 1) &&  ($mm[1] != "1") ){
                    throw new Ewz_Exception( "Multiple instances of shortcode 'ewz_show_rating' in page. First does not have rf_num = '1'.");
                } 
                if( isset( $seen[(string)$mm[1]] ) ){
                    throw new Ewz_Exception( "Duplicate values for 'ewz_show_rating' parameter rf_num in page." );
                } 
                $seen[(string)$mm[1]] = 1;
                ++$n;
            }
            $is_multiple = true;
        }

        // check rest of $atts:  "identifier" is required, and possibly: view, item_ids, judge_ids, rf_num
        $idents = Ewz_Rating_Form::get_all_idents();
        $atts['identifier'] = ewz_check_atts( $atts, 'identifier', $idents, 'required' );

        $atts['item_ids'] = ewz_check_atts( $atts, 'item_ids', '/^\d+(,\d+)*$/', '' );

        $atts['judge_ids'] = ewz_check_atts( $atts, 'judge_ids', '/^\d+(,\d+)*$/', '' );

        $atts['view'] = ewz_check_atts( $atts, 'view', array( 'read', 'secondary', 'rate' ), 'rate' );

    } catch( Exception $e ) {
        $errmsg .= $e->getMessage();
    }
    if($errmsg){
        // Need a popup here, putting it the text could mean it is right at the end of a multi-shortcode page
        wp_localize_script( "ewz-rating", 
                              'ewzG1_' . $atts['rf_num'], 
                              array( 'gvar' => array( 'errmsg' => "$errmsg<br><br>Please contact your administrator."  )));
        return '*** Error in a Shortcode *** <div id="ewz_stored_' . esc_attr( $atts['rf_num'] ) . '" class="ewz_stored"></div>';
    }
    
    // Get the info for the display from the database
    $rating_form =  new Ewz_Rating_Form( $atts['identifier'], $atts['item_ids'] );

    if( !$rating_form->rating_open ){
        return '<h2>Sorry, this form is not currently open for use.</h2>';
    }

    $real_user_id = get_current_user_id();
    $eff_user_id = $real_user_id;
    $is_admin = Ewz_Rating_Permission::can_edit_rating_form_obj($rating_form);
    if( isset( $_GET['jjj'] ) && $is_admin ){
        $eff_user_id = $_GET['jjj'];
    }


    $judges = $rating_form->get_active_judge_ids();
    $eff_usr_is_judge = in_array( $eff_user_id, $judges ) || in_array( 0, $judges );
    $real_usr_is_judge = in_array( $real_user_id, $judges ) || in_array( 0, $judges );
    // if there was a judge_ids attribute, additionally check the user is one of the ids specified
    if( $atts["judge_ids"] && ( strpos(  ','.$atts['judge_ids'].',', ",{$eff_user_id}," ) === false  ) ){
        $eff_usr_is_judge = false;
    }
    if( $atts["judge_ids"] && ( strpos(  ','.$atts['judge_ids'].',', ",{$real_user_id}," ) === false  ) ){
        $real_usr_is_judge = false;
    }

    $settings = new Ewz_Settings();
    $admin_delete = false;
    $admin_delete = Ewz_Settings::get_ewz_option('admin_delete_rating') ? true : false;     

    $admin_string = ewz_get_admin_string($rating_form, $eff_user_id, $admin_delete );
    if( $is_admin ){
        if( !$real_usr_is_judge && ($real_user_id == $eff_user_id)) {
            // user is admin, not a judge and not faking a judge            
            if( !$atts['rf_num'] || $atts['rf_num'] == '1' ){
                // offer to show as a judge, once at top of page
                // TODO: not perfect, would not work if rf_num never 0 or 1
                return $admin_string;
            } else {
                return '';
            }
        }
    } else {  
        if( !$real_usr_is_judge ){
            // real user not admin or judge
            return 'Sorry, you do not have permission to access this content.';    
        }  
    } 

    // Finished checking for situations that completely stop the display of the shortcode
    // Any other error messages will be shown to the judge along with the form

    $stored_scores = array();
    try{
        if( $atts['view'] == 'read' ){
            $stored_scores = $rating_form->get_ratings_by_item_ro();
        } else {
            $stored_scores = $rating_form->get_user_ratings_by_item( $eff_user_id );

            $rating_form->check_count_limits( $stored_scores );        // cant do this on individual item_rating save, so do it here
        }
    } catch( Exception $e ) {
        $errmsg .="<br>" . esc_html( $e->getMessage() );
    }
    if( is_file( wp_upload_dir()['basedir'] . '/' . $rating_form->rating_scheme->settings['testimg'] ) ){
        $testimg =  wp_upload_dir()['baseurl'] . '/' . $rating_form->rating_scheme->settings['testimg'];
    } else {
        $testimg = plugins_url( 'images/RGBtestImg.jpg', dirname(__FILE__) );
    }
    $now = time();
    $ewzG = array( 'ajaxurl' => admin_url('admin-ajax.php', (is_ssl() ? 'https' : 'http')),
                   'load_gif' => plugins_url( 'images/loading.gif' , dirname(__FILE__) ),
                   'num_rows' => count($stored_scores),
                   'jid' => $eff_user_id,
                   'rf_id' => $rating_form->rating_form_id,
                   'maxw' => (int)$rating_form->rating_scheme->settings['maxw'],
                   'maxh' => (int)$rating_form->rating_scheme->settings['maxh'],
                   'img_pad' => (int)$rating_form->rating_scheme->settings['img_pad'],
                   'bcol' => $rating_form->rating_scheme->settings['bcol'],
                   'fcol' => $rating_form->rating_scheme->settings['fcol'],
                   'summary' => (int)$rating_form->rating_scheme->settings['summary'],
                   'finished' => (int)$rating_form->rating_scheme->settings['finished'],
                   'fields' => $rating_form->rating_scheme->fields,
                   'restrictions' => $rating_form->rating_scheme->restrictions,
                   'jsvalid' => Ewz_Base::validate_using_javascript(),
                   'do_warn' => Ewz_Base::warn_on_leaving(),
                   'view' => wp_kses( (string)$atts['view'], array() ),
                   'errmsg' => wp_kses((string) $errmsg, array() ),
                   'reload_after' => 1000 * ( $now + 14400 ),  // 4 hours from now, if there is a save then reload the page
                   'warn_after' =>   1000 * ( $now + 18000 ),  // 5 hours from now, pop up a warning to save and reload
                   'interval' =>   600000,                    // 10 minute interval before nag is repeated 
                   'testimg'  => $testimg,
                   'bg_main' => $rating_form->rating_scheme->settings['bg_main'],
                   'bg_curr' => $rating_form->rating_scheme->settings['bg_curr'],
                   'new_border' => $rating_form->rating_scheme->settings['new_border'],
                   'rf_num' => $atts['rf_num'],
                   'no_save' => ( $real_user_id != $eff_user_id ),
                   'no_delete' => ( ( $real_user_id != $eff_user_id ) && !$admin_delete ),
                   'nosavemsg' => 'SAVING ... <br>This is a demo only, nothing is actually saved. ',
                   'max_unsaved' => Ewz_Settings::get_ewz_option('max_unsaved'),
                 );
    // use of $ewzG1.var = ewzG  is a hack to get around the fact that wp_localize_script
    // runs html_entity_decode on scalar values.  Our data is already processed where it needs to be,
    // and some of it may contains html entities which should not be decoded.
    // It also contains booleans, and boolean false is sent as an empty string
    wp_localize_script( "ewz-rating", 'ewzG1_' . $atts['rf_num'], array( 'gvar' => $ewzG ));

    $output = '';
    if( !$atts['rf_num'] ){       // NB:  string "0" is false in php
        $output = '<h2><a id="columns_top"> </a>' . esc_html( $rating_form->rating_form_title ) . '</h2>';
    }
    if( !$atts["item_ids"] ){
        // adding the style here to make sure its available as early as possible.
        $output .= '<div style=" width: 100%; height: 1920px;top: 20px; left: 0px; position: fixed; display: block;opacity: 0.7; font-size: 5em;color: #777777;font-weight: bold; background-color: #fff;  z-index: 99; text-align: center; padding-top: 300px;" class="loading"><br><br>Loading, Please Wait . . .</div>';
    }
    if(  $is_admin && ( !$atts['rf_num'] || $atts['rf_num'] == '1' ) ){
        $output .= $admin_string;
    }
    if( $real_user_id != $eff_user_id ){
        $output .= "<h2>*** View only ***</h2>";
    }
    $output .=  '<div id="ewz_stored_' . esc_attr( $atts['rf_num'] ) . '" class="ewz_stored">';
    $output .=        ewz_rating_list( $eff_user_id, $stored_scores, $rating_form, $atts, $is_multiple );

    $output .=  '</div>';
    
    return $output;
}

function ewz_get_admin_string( $rating_form, $user_id, $admin_delete ){
    assert(  is_a( $rating_form, 'Ewz_Rating_Form' ) ); 
    assert(  Ewz_Base::is_pos_int( $user_id ) );
    assert(  is_bool($admin_delete) );
    
    // remove any existing jjj variable from the query string and add a dummy one with value j1j1j1
    // ( this will be replaced by the selected judge_id when the new query string is sent )
    $newQueryStr = '?' . preg_replace( '/&?jjj=\d+/', '' , $_SERVER['QUERY_STRING'] );
    if( strpos( $newQueryStr, '=' ) ){
        $newQueryStr .= '&';
    }
    $_newQueryStr = esc_url( $newQueryStr . 'jjj=j1j1j1' );

    $_rfid = esc_attr( $rating_form->rating_form_id );

    $_judge_select = "Display as judge: &nbsp; <select id='demo$_rfid' onChange='show_as_judge(this, \"$_newQueryStr\")'>";
    $_judge_select .=                            '<option value=""> -- select judge -- </option>';    
    // judges with effective user id selected 
    $_judge_select .=                             ewz_option_list( $rating_form->get_judges($user_id, true) );
    $_judge_select .=                          '</select>';

    $_info ="<style type='text/css'>.ewz_admin_info{ border: 1px solid red; padding: 5px; margin-bottom: 15px; padding:15px;text-align:center;}</style>";
    $_info .= "As an administrator, you may view this page as it would be seen by the selected judge.<br>";
    $_info .= "<i>(If there are multiple ewz_rating shortcodes on the page, judges are listed only for the first one.)</i><br>";
    $_info .= "If viewing as another user, you may enter data and see what error messages might be produced, but <b>nothing will actually be saved</b>.<br><br>";

    if( $admin_delete ){
        $_info .="<p style='margin-left: 12%; margin-right: 12%; border: 1px solid black;'><b>Warning:</b> Your EntryWizard settings do allow you to <b>delete</b> a rating by clicking the 'Clear Item' button. Deletions cannot be undone.</p>";
    }
    return "<div class='ewz_admin_info'>$_info  $_judge_select</div>";  
}      

function ewz_check_atts( $arr, $param, $expr, $default ){
    assert( is_array($arr) );
    assert( is_string($param) );
    assert( is_string($expr) || is_array($expr) );
    assert( is_string($default) );
    if( !isset( $arr[$param] ) ){
        $return = $default;
        $arr[$param] = $default;
    }        
    if( preg_match( '/^ *$/', $arr[$param] ) ){
        $return = $default;
        $arr[$param] = $default;
    }
    if( 'null' == $arr[$param] ){
        $return = $default;
        $arr[$param] = $default;
    }  
    if( !isset( $return ) && is_string( $expr) ){
        if( preg_match( $expr, $arr[$param] ) ){
            $return = $arr[$param];
        } else {
            throw new Ewz_Exception("Invalid value '" . $arr[$param] . "' for shortcode parameter '$param'." );
        }
    }
    if( !isset( $return ) && is_array( $expr) ){
        if( in_array ($arr[$param], $expr ) ){
            $return = $arr[$param];
        } else {
            throw new Ewz_Exception("Value '" . $arr[$param] . "' not allowed for shortcode parameter '$param'." );
       } 
    }
    if( $default == 'required' && !$return ){
        throw new Ewz_Exception("Missing value for shortcode parameter '$param'." );
    }

    return $return;
}


/**
 * Save rating data 
 * 
 * Called via ajax 
 * 
 * @param  array     $ratingdata   $_POST processed by Ewz_Item_Rating_Input
 * @return string    id of saved item_rating if ok, otherwise an error message
 **/
function  ewz_process_rating( $ratingdata ){
    assert( is_array( $ratingdata ) );
    try {
        // view is not used to instantiate the Ewz_Item_Rating 
        $view = $ratingdata['view'];
        unset( $ratingdata['view'] );

        $rating = new Ewz_Item_Rating( $ratingdata );
        if( $view == 'rate' ){
            ewz_check_restrictions( $rating, get_current_user_id() );
        }
        $rating->save();  
        return  $rating->item_rating_id;
     } catch( Exception $e ) {
        return "Rating save failed: " . $e->getMessage();
    }
}

/**
 * Check all the restrictions that apply to a rating, and throw an exception if any are not satisfied.
 *
 * @param $rating  Ewz_Item_Rating
 * @return none
 **/
function ewz_check_restrictions( $rating, $judge_id ){
    assert( is_a( $rating, 'Ewz_Item_Rating' ) );
    assert( Ewz_Base::is_nn_int( $judge_id ) );
    $rform = new Ewz_Rating_Form( $rating->rating_form_id );

    $judge_num = array_search( $judge_id, $rform->judges );
    $njudges = count( $rform->judges );
    $rscheme =  new Ewz_Rating_Scheme( $rform->rating_scheme_id );
    $item = new Ewz_Item( $rating->item_id );
    $val = array();
        

    foreach( $rscheme->fields as $rating_field_id => $rating_field ){
        $values[$rating_field_id] = Ewz_Item_Rating::rating_field_display( $rform, $rating_field, $item, $rating );
        $val[$rating_field_id] = $values[$rating_field_id][0];
    }

    $bad_data = '';
    foreach ( $rscheme->restrictions as $restr ) {
        $row_matches_restr[$restr['msg']] = true;
        foreach( $rscheme->fields as $rating_field_id => $rating_field ){
            if( isset( $restr[$rating_field_id] ) &&  ( !in_array( '~*~', $restr[$rating_field_id] ) ) ){
                // if there is a non-'~*~' restriction on a hidden field, no match
                if( $rating_field->divide && ( $rating->item_id % $njudges != $judge_num ) ) {
                     $row_matches_restr[$restr['msg']] = false;
                     break;
                } else {
                    $field_match = false;
                    // now allowing multiple values in the restriction
                    // $field_match is true if any one of them matches the actual value
                    foreach( $restr[$rating_field_id] as $r ){
                        $field_match = $field_match || ewz_is_restr_match( $r, (string)$val[$rating_field_id] );
                    }
                    if( !$field_match ){
                        $row_matches_restr[$restr['msg']] = false;
                        break;
                    }
                }
            }
        }
        if( $row_matches_restr[$restr['msg']] ) {
            $bad_data .= "Item " . $rating->item_id . ": " . $restr['msg'];
        }
    }
    if ( $bad_data ) {
        throw new EWZ_Exception( "Restrictions not satisfied: $bad_data" );
    }     
}

function ewz_is_restr_match(  $rval, $fval )
{
    assert( is_string( $rval ) );
    assert( is_string( $fval ) );
    $ismatch = true;
    switch ( $rval ) {
    case '~*~': $ismatch = true;
        break;
    case '~-~': if ( $fval ) { $ismatch = false; }
        break;
    case '~+~': if ( !$fval ) { $ismatch = false; }
        break;
    default: if ( $rval != $fval )  { $ismatch = false; }
        break;
    }
    return $ismatch;
}
