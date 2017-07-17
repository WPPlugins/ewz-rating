<?php

if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    error_log( 'EWZ: Attempt to uninstall without WP_UNINSTALL_PLUGIN defined' );
    exit();
}
if ( ! current_user_can( 'activate_plugins' ) ){
    exit();
}

global $wpdb;

$prefix = $wpdb->prefix;

define( 'EWZ_RATING_SCHEME_TABLE', $prefix . 'ewz_rating_scheme' );
define( 'EWZ_RATING_FIELD_TABLE',  $prefix . 'ewz_rating_field' );
define( 'EWZ_RATING_FORM_TABLE',   $prefix . 'ewz_rating_form' );
define( 'EWZ_ITEM_RATING_TABLE',   $prefix . 'ewz_item_rating' );

error_log( 'EWZ: ********  uninstalling Ewz-Rating *********' );

$item_rating_table = EWZ_ITEM_RATING_TABLE;
$rating_scheme_table = EWZ_RATING_SCHEME_TABLE;
$rating_field_table = EWZ_RATING_FIELD_TABLE;
$rating_form_table = EWZ_RATING_FORM_TABLE;


// Drop all the ewz tables

    error_log( 'EWZ: deleting all ewz-rating tables and data from database');

    if ( $wpdb->get_var( "SHOW TABLES LIKE '$item_rating_table'" ) == $item_rating_table ) {  // no tainted data
        error_log("EWZ: Dropping $item_rating_table" );
        $wpdb->query( "DROP TABLE $item_rating_table" );  // no tainted data
    }
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$rating_field_table'" ) == $rating_field_table ) {  // no tainted data
        error_log("EWZ: Dropping $rating_field_table" );
        $wpdb->query( "DROP TABLE $rating_field_table" );  // no tainted data
    }
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$rating_form_table'" ) == $rating_form_table ) {  // no tainted data
        error_log("EWZ: Dropping $rating_form_table" );
        $wpdb->query( "DROP TABLE  $rating_form_table" );   // no tainted data
    }
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$rating_scheme_table'" ) == $rating_scheme_table ) {  // no tainted data
        error_log("EWZ: Dropping $rating_scheme_table" );
        $wpdb->query( "DROP TABLE $rating_scheme_table" );  // no tainted data
    }

    if ( $wpdb->get_var( "SHOW TABLES LIKE '$item_rating_table'" ) == $item_rating_table ) {   // no tainted data
        error_log("EWZ: Failed to drop table  $item_rating_table" );
    }
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$rating_field_table'" ) == $rating_field_table ) {  // no tainted data
        error_log("EWZ: Failed to drop table  $rating_field_table" );
    }
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$rating_form_table'" ) == $rating_form_table ) {  // no tainted data
        error_log("EWZ: Failed to drop table  $rating_form_table" );
    }
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$rating_scheme_table'" ) == $rating_scheme_table ) {  // no tainted data
        error_log("EWZ: Failed to drop table  $rating_scheme_table" );
    }

    // remove the 'ewz_judge' role
    remove_role( 'ewz_judge' );

    // delete the ewz_rating_version option
    delete_option( 'ewz_rating_version');

