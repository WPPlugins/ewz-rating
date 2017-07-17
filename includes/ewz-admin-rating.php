<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . 'classes/ewz-base.php');
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-exception.php' );
require_once( EWZ_RATING_DIR . 'classes/ewz-rating-permission.php' );

require_once( EWZ_RATING_DIR . 'classes/validation/ewz-rating-spreadsheet-input.php' );
require_once( EWZ_RATING_DIR . 'classes/validation/ewz-rating-form-set-input.php' );
require_once( EWZ_RATING_DIR . 'classes/validation/ewz-rating-scheme-set-input.php' );
require_once( EWZ_RATING_DIR . 'classes/validation/ewz-item-rating-input.php' );

/* * ****************   Functions to enqueue the scripts and styles ******************* */
/*  Most scripts require data stored in a variable called 'ewzG', which must first be   */
/*      generated. They are not enqueued until we know which are really needed       */

/**
 * this function is hooked within ewz_admin_menu
 **/
function ewz_enqueue_rating_form_scripts() {
     wp_enqueue_script( 'ewz-admin-rating-forms' );
     wp_enqueue_style( 'ewz-rating-admin-style' );
     wp_enqueue_style( 'ewz-rating-style' );
}
 
/**
 * Let wp add the previously-defined scripts and styles to the page
 * Called within ewz_admin_rating_schemes_menu only, to avoid unnecessary loading
 **/
function ewz_enqueue_rating_scheme_scripts() {
     wp_enqueue_script( 'ewz-admin-rating-schemes' );
     wp_enqueue_style( 'ewz-rating-admin-style' );
     wp_enqueue_style( 'ewz-rating-style' );
}

/**
 * Register the Scripts and Styles for the Admin area
 * But don't actually call them until inside the "Menu" function
 **/
function ewz_admin_rating_init(){ 
    wp_register_style( 'ewz-rating-admin-style', plugins_url( 'ewz-rating/styles/ewz-admin-rating.css' ), array('wp-color-picker'), EWZ_RATING_VERSION );

    if( isset( $_REQUEST['page'] ) ){
        if( $_REQUEST['page'] == 'ewzratingschemeadmin' ){             
             wp_register_script( 'ewz-admin-rating-schemes',
                                plugins_url( 'ewz-rating/javascript/ewz-rating-scheme.js' ),
                                      array('jquery', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-dialog', 'wp-color-picker',
                                       'jquery-ui-position','ewz-admin-common', 'jquery-ui-sortable', 'jquery-ui-draggable','jquery-ui-droppable' ),
                                EWZ_RATING_VERSION,
                                true         // in footer, so $ewzG has been defined
                                ); 
         } elseif( $_REQUEST['page'] == 'ewzratingformadmin' ){
             wp_register_script( 'ewz-admin-rating-forms',
                                plugins_url( 'ewz-rating/javascript/ewz-rating-form.js' ),
                                       array( 'jquery', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-dialog', 'ewz-admin-common',
                                       'jquery-ui-position', 'jquery-ui-sortable', 'jquery-ui-draggable','jquery-ui-droppable' ),
                                EWZ_RATING_VERSION,
                                true         // in footer, so $ewzG has been defined
                                ); 
         }
    }
}
add_action( 'admin_init', 'ewz_admin_rating_init' );

/**
 * Display the rating admin pages
 **/
function ewz_admin_rating_menu() {
   // wp function add_submenu_page( $parent_slug, $page_title, $menu_title, 
   //                               $capability, $menu_slug, $function );
    if ( Ewz_Rating_Permission::can_see_rating_form_page() ) {
           $rating_hook_suffix = add_submenu_page(
              'entrywizard', 'EntryWizard Rating Forms', 'Rating Forms',
              'read', 'ewzratingformadmin', 'ewz_rating_form_menu' );
 
           add_action( 'admin_print_styles-' . $rating_hook_suffix,  'ewz_enqueue_common_styles' );        // in entrwizard
          add_action( 'admin_print_scripts-' . $rating_hook_suffix,  'ewz_enqueue_rating_form_scripts' );

    }
    if ( Ewz_Rating_Permission::can_see_scheme_page() ) {
          $rating_hook_suffix = add_submenu_page(
              'entrywizard', 'EntryWizard Rating Schemes', 'Rating Schemes',
              'read', 'ewzratingschemeadmin', 'ewz_rating_scheme_menu' 
          );

          add_action( 'admin_print_styles-' . $rating_hook_suffix,  'ewz_enqueue_common_styles' );
          add_action( 'admin_print_scripts-' . $rating_hook_suffix, 'ewz_enqueue_rating_scheme_scripts' );     
    }
}
add_action( 'admin_menu', 'ewz_admin_rating_menu', 20 );  // low priority so last on the menu


/* * ************************  AJAX CALLS *************************** *
 *
 *  NOTE: The action 'wp_ajax_xxxxxx' is called when the 'xxxxxx'
 *        action is specified in javascript
 *
 *  Each function echoes a return status that is checked in
 *  the javascript and usually alerted to the viewer, then exits.
 *
 */
/**
 * Delete a Rating_form - handle the ajax call
 *
 * Called from the Rating Forms page via one of the "Delete Rating Form" buttons 
 * NB: action name is generated from the jQuery post, must match.
 *
 * if response is not '1', javascript caller alerts with error message.
 **/
function ewz_delete_rform_callback() {
    if ( check_admin_referer( 'ewzadmin', 'ewznonce' )  ) {
        ewz_wipe_buffers();
        try {
            if ( !(isset( $_POST['rating_form_id'] ) && is_numeric( $_POST['rating_form_id'] )) ) {
                throw new EWZ_Exception( 'Missing or non-numeric rating_form_id' );
            } 
            $rating_form = new Ewz_Rating_Form( (int)$_POST['rating_form_id'] );
            $rating_form->delete( Ewz_Rating_Form::DELETE_RATINGS );
            echo "1";
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    } else {
        echo "No deletion - authorization expired";
        error_log( "EWZ: ewz_delete_rform_callback check_admin_referer failed" );
    }
    exit();
}
add_action( 'wp_ajax_ewz_delete_rform', 'ewz_delete_rform_callback' );

/**
 * Handle the "recalculate" call from the rating-forms admin page
 * Called from the Rating Forms page via one of the Recalculate buttons in the Data Management area
 **/
function ewz_recalc_callback() {
    if ( check_admin_referer( 'ewzadmin', 'ewznonce' ) ) {
        ewz_wipe_buffers();
        try {
            if ( !(isset( $_POST['rating_form_id'] ) && is_numeric( $_POST['rating_form_id'] )) ) {
                throw new EWZ_Exception( 'Missing or non-numeric rating_form_id' );
            }
            $ratingform = new Ewz_Rating_Form( $_POST['rating_form_id'] );
            $ratingform->recalculate();
            echo ewz_set_status_table( $ratingform ); 
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    } else {
        echo "No recalculation - authorization expired";
        error_log( "EWZ: ewz_recalc_callback check_admin_referer failed" );
    }
    exit();
}
add_action( 'wp_ajax_ewz_recalc', 'ewz_recalc_callback' );


/**
 * Handle the "reopen for judge" call from the rating-forms admin page
 * Called from the Rating Forms page via one of the "Reopen for this judge" buttons in the Data Management area
 **/
function ewz_reopen_callback() {
    if ( check_admin_referer( 'ewzadmin', 'ewznonce' ) ) {
        ewz_wipe_buffers();
        try {
            if ( !(isset( $_POST['rating_form_id'] ) && is_numeric( $_POST['rating_form_id'] )) ||
                 !(isset( $_POST['judge_id'] ) && is_numeric( $_POST['judge_id'] )) ) {
                throw new EWZ_Exception( 'Missing or non-numeric rating_form_id or judge_id' );
            } 
            $ratingform = new Ewz_Rating_Form( $_POST['rating_form_id'] );
            $ratingform->reopen_for($_POST['judge_id'] );
            echo ewz_set_status_table( $ratingform );
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    } else {
        echo "Not re-opened - authorization expired";
        error_log( "EWZ: ewz_reopen_callback check_admin_referer failed" );
    }
    exit();
}
add_action( 'wp_ajax_ewz_reopen', 'ewz_reopen_callback' );

/**
 * Handle the "delete ratings for judge" call from the rating-forms admin page
 * Called from the Rating Forms page via one of the "Delete Ratings" buttons in the Data Management area
 **/
function ewz_del_judge_ratings_callback() {
    if ( check_admin_referer( 'ewzadmin', 'ewznonce' )  ) {
        ewz_wipe_buffers();
        try {
            if ( !(isset( $_POST['rating_form_id'] ) && is_numeric( $_POST['rating_form_id'] )) ||
                 !(isset( $_POST['judge_id'] ) && is_numeric( $_POST['judge_id'] )) ) {
                throw new EWZ_Exception( 'Missing or non-numeric rating_form_id or judge_id' );
            }
            $ratingform = new Ewz_Rating_Form( $_POST['rating_form_id'] );
            $ratingform->del_judge_ratings( $_POST['judge_id'] );
            echo ewz_set_status_table( $ratingform );
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    } else {
        echo "No deletions - authorization expired";
        error_log( "EWZ: ewz_del_judge_ratings_callback check_admin_referer failed" );
    }
    exit();
}
add_action( 'wp_ajax_ewz_del_judge_ratings', 'ewz_del_judge_ratings_callback' );


/**
 * A Judge is finished - handle ajax call
 *
 * Called from the front-end Ratings page via the "Finished" button
 * NB: action name is generated from the jQuery post, must match.
 *
 * if response is not '1', javascript caller alerts with error message.
 **/
function ewz_done_callback() {
    if ( wp_verify_nonce( $_POST["ewzratingnonce"], 'ewzrating' ) ) {
        ewz_wipe_buffers();
        try {
            if ( !( isset( $_POST['rating_form_id'] ) && is_numeric( $_POST['rating_form_id'] )) ){
                throw new EWZ_Exception( 'Missing or non-numeric rating_form_id' );
            }
            if( !( isset( $_POST['judge_id'] ) && ( $_POST['judge_id'] == get_current_user_id() )) ) {
                throw new EWZ_Exception( 'Missing or invalid judge_id' );
            } 
            $rating_form = new Ewz_Rating_Form( $_POST['rating_form_id'] );
            echo $rating_form->finished( $_POST['judge_id'] );
        } catch (Exception $e) {
            echo $e->getMessage();
        }        
    } else {
        echo "Error - authorization expired";
        error_log( "EWZ: ewz_done_rating_form_callback verify_nonce failed" );
    }
    exit();
}
add_action( 'wp_ajax_ewz_done', 'ewz_done_callback' );

/**
 * Delete a Field - handle ajax call
 *
 * Called from the Rating Schemes page via one of the "Delete Field" buttons
 * NB: action name is generated from the jQuery post, must match.
 * Javascript caller alerts if response is not "1" (true)
 **/
function ewz_del_rating_field_callback() {
    if ( check_admin_referer( 'ewzadmin', 'ewznonce' ) ) {
        ewz_wipe_buffers();
        try {
            if ( !( isset( $_POST['scheme_id'] ) && is_numeric( $_POST['scheme_id'] ) &&
                    isset( $_POST['rating_field_id'] ) && is_numeric( $_POST['rating_field_id'] )) ) {
                error_log( 'EWZ: ewz_del_rating_field_callback, ' .
                            'no scheme_id or no rating_field_id or id not numeric' );
                echo 'Missing or non-numeric scheme or rating_field ids';
            } else {        
                $scheme = new Ewz_Rating_Scheme( (int)$_POST['scheme_id'] );
                $scheme->delete_field( (int)$_POST['rating_field_id'] );
                echo "1";
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    } else {
        echo 'Permissions invalid, may have expired.  Try reloading the page.';
        error_log( "EWZ: ewz_del_rating_field_callback, check_admin_referer failed" );
    }
    exit();
}
add_action( 'wp_ajax_ewz_del_rating_field', 'ewz_del_rating_field_callback' );


/**
 * Called from the Rating Schemes page via one of the "Delete scheme" buttons
 * Handle a "delete scheme" call from the admin rating-schemes page
 **/
function ewz_del_scheme_callback() {
    if ( check_admin_referer( 'ewzadmin', 'ewznonce' ) ) {
        ewz_wipe_buffers();
        try {
            if ( !(isset( $_POST['rating_scheme_id'] ) && is_numeric( $_POST['rating_scheme_id'] )) ) {
                error_log( 'EWZ: ewz_del_scheme_callback, no rating_scheme_id or not numeric' );
                echo 'Missing or non-numeric  rating_scheme_id';
            } else {
                $scheme = new Ewz_Rating_Scheme( (int)$_POST['rating_scheme_id'] );
                $scheme->delete( Ewz_Rating_Scheme::DELETE_FORMS );
                echo "1";
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    } else {
        echo "Permissions invalid, may have expired.  Try reloading the page.";
        error_log( "EWZ: ewz_del_scheme_callback, check_admin_referer failed" );
    }
    exit();
}
add_action( 'wp_ajax_ewz_del_scheme', 'ewz_del_scheme_callback' );

/**
 * Delete the rating
 * Called from the front-end Rating page via one of the Clear buttons
 */
function ewz_delete_rating_callback() {
    require_once( EWZ_PLUGIN_DIR . 'includes/ewz-admin.php');
    require_once( EWZ_RATING_DIR . 'includes/ewz-rating-shortcode.php');
    if ( wp_verify_nonce( $_POST["ewzratingnonce"], 'ewzrating' ) ) {
        ewz_wipe_buffers();
        try { 
            $settings = new Ewz_Settings();
            if ( !Ewz_Settings::get_ewz_option('admin_delete_rating') ){
                if( !( isset( $_POST['judge_id'] ) && ( $_POST['judge_id'] == get_current_user_id() )) ) {
                    throw new EWZ_Exception( 'Missing or invalid judge_id' );
                }
            }
            $irating = new Ewz_Item_Rating( (int)$_POST['item_rating_id'] );
            $irating->delete();
            echo '1';
        } catch (Exception $e) {
            echo  $e->getMessage();
            error_log("EWZ: ewz_delete_rating_callback exception ". $e->getMessage());
        }
    } else {
        echo "Permissions invalid, may have expired.  Try reloading the page.";
        error_log( "EWZ: ewz_delete_rating_callback, verify_nonce failed" );
    }
    exit();
}
add_action( 'wp_ajax_ewz_delete_rating', 'ewz_delete_rating_callback' );



/**
 * Save the rating
 * Called from the front-end Rating page via one of the Save All buttons
 */
function ewz_save_rating_callback() {
    require_once( EWZ_PLUGIN_DIR . 'includes/ewz-admin.php');
    require_once( EWZ_RATING_DIR . 'includes/ewz-rating-shortcode.php');
    if ( wp_verify_nonce( $_POST["ewzratingnonce"], 'ewzrating' ) ) {
        ewz_wipe_buffers();
        try { 
            if( !( isset( $_POST['judge_id'] ) && ( $_POST['judge_id'] == get_current_user_id() )) ) {
                throw new EWZ_Exception( 'Missing or invalid judge_id' );
            }
            $input = new Ewz_Item_Rating_Input( stripslashes_deep( $_POST ) );
            $new_id = ewz_process_rating( $input->get_input_data() );
            echo $new_id;
        } catch (Exception $e) {
            echo  $e->getMessage();
            error_log("EWZ: ewz_save_rating_callback exception ". $e->getMessage());
        }
    } else {
        echo "Permissions invalid, may have expired.  Try reloading the page.";
        error_log( "EWZ: ewz_save_rating_callback, verify_nonce failed" );
    }
    exit();
}
add_action( 'wp_ajax_ewz_save_rating', 'ewz_save_rating_callback' );


/**
 * Get the count of saved ratings for the judge
 * Called from the front-end Rating page after all saves have completed
 *
 **/ 
function ewz_get_judge_count_callback() {
    require_once( EWZ_PLUGIN_DIR . 'includes/ewz-admin.php');
    require_once( EWZ_RATING_DIR . 'includes/ewz-rating-shortcode.php');
    if ( wp_verify_nonce( $_POST["ewzratingnonce"], 'ewzrating' ) ) {
        ewz_wipe_buffers();
        try{
            if ( !( isset( $_POST['rating_form_id'] ) && is_numeric( $_POST['rating_form_id'] )) ){
                throw new EWZ_Exception( 'Missing or non-numeric rating_form_id' );
            }
            if( !( isset( $_POST['judge_id'] ) && is_numeric( $_POST['judge_id'] ) ) ) {
                throw new EWZ_Exception( 'Missing or invalid judge_id' );
            }
            $result = Ewz_Item_Rating::get_judge_count( $_POST['judge_id'], $_POST['rating_form_id'] );
            echo "$result Items saved";
        } catch (Exception $e) {
            echo  'Failed to get counts: ' . $e->getMessage();
            error_log("EWZ: ewz_get_judge_count_callback exception ". $e->getMessage());
        }
    } else {
        echo "Permissions invalid, may have expired.  Try reloading the page.";
        error_log( "EWZ: ewz_get_judge_count_callback, verify_nonce failed" );
    }
    exit();
}
add_action( 'wp_ajax_ewz_get_judge_count', 'ewz_get_judge_count_callback' );

/**
 * Save a scheme
 * Called from the Rating Schemes page via the Save Changes buttons
 */
function ewz_scheme_changes_callback() {
    require_once( EWZ_RATING_DIR . 'classes/ewz-rating-scheme.php');
    if ( check_admin_referer( 'ewzadmin', 'ewznonce' ) ) {
        ewz_wipe_buffers();
        try {
            $input = new Ewz_Rating_Scheme_Input($_POST);
            $pdata = $input->get_input_data();
            // create empty values for 'restrictions' and 'extra_cols' if they dont exist
            if ( !array_key_exists( 'restrictions', $pdata ) ) {
                $pdata['restrictions'] = array( );
            }
            if ( !array_key_exists( 'extra_cols', $pdata ) ) {
                $pdata['extra_cols'] = array( );
            }

            // set up the 'pg_column' field values
            foreach ( $pdata['forder'] as $col => $value ) {
                $mat = array( );
                preg_match( '/forder_f(X?\d+)_c(X?\d+)_/', $value, $mat );
                assert( 3 == count( $mat ) );
                $field = $mat[2];
                if( isset( $pdata['fields'][$field] ) ){
                    $pdata['fields'][$field]['pg_column'] = $col;
                }

                // ignore "append" in first column
                if( ( $col == 0 ) && $pdata['fields'][$field]['append'] ){
                    $pdata['fields'][$field]['append'] = false;
                } 

            }

            $rating_scheme = new Ewz_Rating_Scheme( $pdata );
            $ret = $rating_scheme->save();  // always save a scheme immediately after creation from data, to get the fields correctly indexed
           
            echo $ret;

        } catch (Exception $e) {
            error_log("EWZ: ewz_scheme_changes_callback exception ". $e->getMessage());
            echo $e->getMessage();
        }
    } else {
        echo "Permissions invalid, may have expired.  Try reloading the page.";
        error_log( "EWZ: ewz_scheme_changes_callback, check_admin_referer failed" );
    }
    exit();
}
add_action( 'wp_ajax_ewz_scheme_changes', 'ewz_scheme_changes_callback' );


/**
 * Save the order of rating schemes 
 * Called from the Rating Schemes page via one of the "Save Order" buttons
 *
 */
function ewz_save_scheme_order_callback() {
    if ( check_admin_referer( 'ewzadmin', 'ewznonce' ) ) {
        try {
             $input = new Ewz_Rating_Scheme_Set_Input( $_POST );
             $num_updated = Ewz_Rating_Scheme::save_scheme_order( $input->get_input_data() );
        } catch (Exception $e) {
            error_log("EWZ: ewz_save_scheme_order_callback exception " . $e->getMessage());
            echo $e->getMessage();
        }
        echo "$num_updated rating schemes updated";
    } else {
        error_log( "EWZ: ewz_save_scheme_order_callback, check_admin_referer failed" );
        echo "Permissions invalid, may have expired.  Try reloading the page.";
    }
    exit();
}
add_action( 'wp_ajax_ewz_save_scheme_order', 'ewz_save_scheme_order_callback' );

/**
 * Save the order of rating forms 
 * Called from the Rating Forms page via one of the "Save Order" buttons
 **/
function ewz_save_rating_form_order_callback() {
    if ( check_admin_referer( 'ewzadmin', 'ewznonce' ) ) {
        try {
             $input = new Ewz_Rating_Form_Set_Input( $_POST );
             $num_updated = Ewz_Rating_Form::save_rating_form_order( $input->get_input_data() );
        } catch (Exception $e) {
            error_log("EWZ: ewz_save_rating_form_order_callback exception " . $e->getMessage());
        }
        echo "$num_updated rating forms updated";
    } else {
        echo "Permissions invalid, may have expired.  Try reloading the page.";
        error_log( "EWZ: ewz_save_rating_form_order_callback, check_admin_referer failed" );
    }
    exit();
}
add_action( 'wp_ajax_ewz_save_rating_form_order', 'ewz_save_rating_form_order_callback' );


/**
 * Output to stdout a .csv summary of the rating form data
 * Has to be hooked earlier than other stuff to avoid any output before it.
 * Echoes a header followed by the data to stdout, which forces a download dialog
 * Called from the Rating Forms page via the "Download ...." button
 *
 * @return int 0 if bad data or permissions, otherwise 1
 **/
function ewz_echo_rating_data() {
    // Rest is only run if we are in the right mode
    // But note this check is run even if we are doing ajax calls
    if ( isset( $_POST["ewzmode"] ) && 'rspread' == $_POST["ewzmode"] ) {
        if ( check_admin_referer( 'ewzadmin', 'ewznonce' ) ) {
            try {
                ewz_wipe_buffers();
                // validate
                $input = new Ewz_Rating_Spreadsheet_Input( $_POST );
                $data = $input->get_input_data();
                $ratingform = new Ewz_Rating_Form( $data['rating_form_id'] );
                echo $ratingform->download_spreadsheet( $data['ss_style'] );
            } catch (Exception $e) {
                echo $e->getMessage();
                error_log("EWZ: ewz_echo_rating_data exception ". $e->getMessage());
            }
        } else {
            echo "Permissions invalid, may have expired.  Try reloading the page.";
            error_log( "EWZ: ewz_echo_rating_data, check_admin_referer failed" );
        }
        exit();
    }
}
// need to make sure global constants are defined first
add_action( 'init', 'ewz_echo_rating_data', 30 );
