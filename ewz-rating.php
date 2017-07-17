<?php
/*
Plugin Name: EWZ-Rating
Plugin URI: http:
Description:  Add-On plugin for EntryWizard.  Allows uploaded images to be reviewed and judged.
Version: 1.0.11
Author: Josie Stauffer
Author URI:
License: GPL2
*/
/*
  Copyright 2012  Josie Stauffer  (email : )

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly
define( 'EWZ_RATING_VERSION', '1.0.11' );
define( 'EWZ_VERSION_MIN', '1.2.25' );

define( 'EWZ_RATING_DIR', plugin_dir_path( __FILE__ ) );

require_once( EWZ_RATING_DIR . 'includes/ewz-admin-rating-help.php' );
require_once( EWZ_RATING_DIR . 'classes/ewz-rating-setup.php' );

/*********** SECTION: Constants, Shortcodes and Activation ****************/
// run the table creation code from classes/ewz-rating-setup.php when the plugin is activated
register_activation_hook( __FILE__,   array( 'Ewz_Rating_Setup', 'setup_rating_tables' ) );

if ( ! is_admin() ) { 
    // define the shortcode function ( includes/ewz-rating-shortcode.php ) so it can be included in a page or post
    add_shortcode( 'ewz_show_rating', 'ewz_show_rating' );
}

/*
 * When plugins all loaded:
 *    Set some global variables
 *    Require entrywizard.php and the shortcode function
 *    If is_admin, require the admin pages
 */
function ewz_init_rating_globals(){
    global $wpdb;
    define( 'EWZ_RATING_SCHEME_TABLE', $wpdb->prefix . 'ewz_rating_scheme' );
    define( 'EWZ_RATING_FIELD_TABLE',  $wpdb->prefix . 'ewz_rating_field' );
    define( 'EWZ_RATING_FORM_TABLE',   $wpdb->prefix . 'ewz_rating_form' );
    define( 'EWZ_ITEM_RATING_TABLE',   $wpdb->prefix . 'ewz_item_rating' );

    require_once( str_replace('ewz-rating', 'entrywizard', EWZ_RATING_DIR ) . "entrywizard.php" );
    require_once( EWZ_RATING_DIR . '/includes/ewz-rating-shortcode.php' );

    if ( is_admin() ) { 
        require_once( EWZ_RATING_DIR . 'includes/ewz-admin-rating.php' );
        require_once( EWZ_RATING_DIR . '/includes/ewz-admin-rating-schemes.php' );
        require_once( EWZ_RATING_DIR . '/includes/ewz-admin-rating-forms.php' );
    }
}
add_action( 'plugins_loaded', 'ewz_init_rating_globals', 11 );   // after ewz_init_globals

/* needed if entrywizard not installed or version too old */
function rating_deactivate() {
    deactivate_plugins( plugin_basename( __FILE__ ) );
}

if( !defined('EWZ_CURRENT_VERSION' ) ){
      add_action( 'admin_init', 'rating_deactivate' );
      add_action( 'admin_notices', 'rating_entrywizard_notice' );

      function rating_entrywizard_notice() {
           echo '<div class="error"><p>The EntryWizard plugin is required for EWZ-Rating.</p>';
           echo '<p>Please install and activate EntryWizard before activating Ewz-Rating.</p></div>';
           if ( isset( $_GET['activate'] ) ){
                unset( $_GET['activate'] );
           }
      } 
} elseif( version_compare( EWZ_VERSION_MIN, EWZ_CURRENT_VERSION, '>' ) ){
      add_action( 'admin_init', 'rating_deactivate' );
      add_action( 'admin_notices', 'rating_version_notice' );

      function rating_version_notice() {
           echo '<div class="error"><p>At least version ' . EWZ_VERSION_MIN . ' of EntryWizard is required for EWZ-Rating.</p>';
           echo '<p>Please update EntryWizard before activating Ewz-Rating.</p></div>';
           if ( isset( $_GET['activate'] ) ){
                unset( $_GET['activate'] );
           }
      } 
}      

/*********** SECTION: EntryWizard Hooks ****************/
add_action( 'ewz_before_delete_webform', array('Ewz_Rating_Form', 'drop_webform' ) );
add_action( 'ewz_before_delete_layout', array('Ewz_Rating_Scheme', 'drop_layout' ) );
add_action( 'ewz_before_delete_field', array('Ewz_Rating_Field', 'drop_field' ) );
add_action( 'ewz_before_delete_item', array('Ewz_Item_Rating', 'drop_item_ratings' ) );
add_action( 'ewz_after_help', 'ewz_rating_help' );
add_action( 'ewz_before_help', 'ewz_rating_help_links' );

function ewz_rating_settings( $defaults ){
    assert(is_array($defaults));
    $defaults['admin_delete_rating'] =  
        array( 'type'   => 'boolean',  
               'params' => array( ),
               'def'    => false,
               'desc'   => 'Affects the admin "Display as judge" view of the rating shortcode.<br>The "Save All" button in this view never actually does anything, but if this option is checked, the "Clear Item" button will really delete the rating.<br>To avoid accidental deletions, it is probably advisable to check this <u>only when it is really needed</u>, and to uncheck it again afterwards.'
             );
     $defaults['max_unsaved'] =  
        array( 'type'   => 'select',  
               'params' => array( 'opts' => array('1'=>'1','2'=>'2','3'=>'3','4'=>'4','5'=>'5','6'=>'6','7'=>'7','8'=>'8','9'=>'9','10'=>'10',
                                                  '15'=>'15','20'=>'20','25'=>'25','30'=>'30','50'=>'50','75'=>'75','100'=>'100' )),
               'def'    => '5',
               'desc'   => 'Number of unsaved ratings after which the judge gets a warning to save. Should be smaller on hosts with lower memory and cpu limits.'
             );
    return $defaults;
}

function ewz_rating_settings_input( $rules ){
    assert(is_array($rules));
    $rules['max_unsaved'] =  array( 'type' => 'limited',   'req' => false, 'val' => array_map('strval', range(1,100) ) );
    $rules['admin_delete_rating'] =  array( 'type' => 'to_bool',   'req' => false, 'val' => '' );
    return $rules;
}

add_filter( 'ewz_after_settings_input', 'ewz_rating_settings_input', 10, 1);
add_filter( 'ewz_settings_defaults', 'ewz_rating_settings' );


/*********** SECTION: TinyMCE Shortcodes Menu ****************/

/* Set up the EWZ_R_Shortcodes menu in tinyMCE */
function r_shortcode_menu($screen) {
    // no assert
    // only hook up these filters if we are in the admin panel, editing a post/page/event, and the current user has 
    // permission to edit posts and pages and has some entrywizard permissions
    if ( current_user_can( 'edit_posts' ) && current_user_can( 'edit_pages' ) && 
           Ewz_Rating_Permission::can_see_rating_form_page()  &&  $screen->post_type ) {
        add_filter( 'mce_buttons_2', 'ewz_mce_r_button' );
        add_filter( 'mce_external_plugins', 'ewz_mce_r_plugin' );

        foreach( array('post.php','post-new.php') as $hook ){
            add_action( "admin_head-$hook", 'ewz_admin_r_head' );
        }
    }
}
//add_action( 'admin_init',  'r_shortcode_menu' );
add_action( 'current_screen', 'r_shortcode_menu', 10 ,1 );

add_action( 'delete_user_form', array('Ewz_Rating_Form', 'warn_deleting_judge') ,10, 2  );  // warn before confirming deletion
add_action( 'deleted_user', array('Ewz_Rating_Form', 'delete_user_as_judge' ) );  // delete userid from judge list


/* pass the data to javascript */
function ewz_admin_r_head(){
    $ewzrdata = array();
    $ewzrdata['ratingforms'] = array_values(array_filter( Ewz_Rating_Form::get_all_rating_forms( 'can_see_rating_form_page' )));
     ?>
<script type='text/javascript'>
     var EWZrdata =  <?php echo json_encode( $ewzrdata ); ?>;
</script>
    <?php
}  

/* pass the button data to mce_buttons filter */
function ewz_mce_r_button( $buttons ) {
    assert( is_array( $buttons ) );
    array_push( $buttons,'r_shortcodes');
    return $buttons;
}
 
/* pass the javascript file name to mce_external_plugins filter */
function ewz_mce_r_plugin( $plugins ) {
    assert( is_array( $plugins ) );
    $plugins['r_shortcodes'] = plugins_url() . '/ewz-rating/javascript/ewz-rating-shortcodes.js?ewzv=' . EWZ_RATING_VERSION;
    return $plugins;
}


/*********** SECTION: Register Scripts and Styles ****************/
/*
 * Register the Scripts and Styles  needed for the shortcode ( front end )
 * But dont actually call them until inside the shortcode function
 */
function ewz_add_rating_sheet(){
    // register the style so we can require it as a dependency for another style if needed
    wp_register_style( 'ewz-rating-style',    // name to use as a handle for the script
                       plugins_url( 'ewz-rating/styles/ewz-rating.css' ),  // source
                       array('jquery-ui-dialog' ),     // dependencies -- handles of other registered styles
                       EWZ_RATING_VERSION   // make sure old cached versions are not used
                     );
    wp_register_style( 'ewz-rating-user-style',    // name to use as a handle for the script
                       plugins_url( 'ewz-rating/styles/ewz-rating-user.css' ),  // source
                       array('jquery-ui-dialog' ),     // dependencies -- handles of other registered styles
                       EWZ_RATING_VERSION   // make sure old cached versions are not used
                     );

    wp_register_script( 'ewz-rating',                        // name to use as a handle for the script
                        plugins_url( 'ewz-rating/javascript/ewz-rating.js' ),   
                        array( 'jquery', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-dialog' ), 
                        EWZ_RATING_VERSION,                  // make sure old cached versions are not used
                        true                                 // in footer, so $ewzR has been defined
                      );
}
add_action( 'wp_enqueue_scripts', 'ewz_add_rating_sheet' );



/*********** SECTION: Updates ****************/
/*
 * check the version stored in the wp options table against the one in this file.
 * If out-of-date, update the db table structure and make any other required changes
 * then save the current version in the wp options table
 * Hook to admin-init so it runs on any admin page
 */
function ewz_rating_db_updates(){ 
    $ewz_rating_version = get_option( 'ewz_rating_version',  EWZ_RATING_VERSION );
    if ( $ewz_rating_version && version_compare( $ewz_rating_version, EWZ_RATING_VERSION,  '<' ) ){
       if ( version_compare( $ewz_rating_version, '1.0.0', '<' ) ){
           error_log( "EWZ: in update section, ewz_rating_version is $ewz_rating_version, EWZ_RATING_VERSION is " . EWZ_RATING_VERSION );            
           Ewz_Rating_Setup::setup_rating_tables();
       }
       update_option( 'ewz_rating_version', EWZ_RATING_VERSION );
       // lengthened scheme settings column 
       if ( version_compare( $ewz_rating_version, '1.0.5', '<' ) ){
           error_log( "EWZ: updating rating from  $ewz_rating_version to 1.0.5" );
           Ewz_Rating_Setup::setup_rating_tables();
       }
    }
    if( !$ewz_rating_version ){
       update_option( 'ewz_rating_version', EWZ_RATING_VERSION );
    }
}
add_action( 'admin_init', 'ewz_rating_db_updates', 9 );
