<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

/** ***************************************************
 * static functions that run during activation/deactivation
 * uninstall is done in uninstall.php
 * ************************************************** */

class Ewz_Rating_Setup
{

    // NB: so long as the rating code is being kept separate from the rest,
    // this function may run BEFORE Ewz_Setup::activate_or_install_ewz
    // so we cannot assume the other ewz tables exist and are up-to-date.
    public static function setup_rating_tables()
    {
        global $wpdb;

        if( !defined( 'EWZ_ITEM_RATING_TABLE' ) ){
            define( 'EWZ_RATING_SCHEME_TABLE', $wpdb->prefix . 'ewz_rating_scheme' );
            define( 'EWZ_RATING_FIELD_TABLE',  $wpdb->prefix . 'ewz_rating_field' );
            define( 'EWZ_RATING_FORM_TABLE',   $wpdb->prefix . 'ewz_rating_form' );
            define( 'EWZ_ITEM_RATING_TABLE',   $wpdb->prefix . 'ewz_item_rating' );
        }

        $curr_tz = date_default_timezone_get();
        $tz_opt = get_option('timezone_string');
        if( $tz_opt ){
            date_default_timezone_set( $tz_opt );
        }

        if( !get_role( 'ewz_judge' ) ){
            $result = add_role( 'ewz_judge', 'EntryWizard Judge',  array( 'read'  => true, 'ewz_rating' => true ) );
            if ( !$result )  {
                error_log('EWZ: failed to create the ewz_judge role.' );
            }
        }

        // will just update if they already exist
       self::create_db_tables();

       if ( $wpdb->get_var( "SHOW TABLES LIKE '" . EWZ_ITEM_RATING_TABLE . "'" ) != EWZ_ITEM_RATING_TABLE ) {   // no tainted data
           error_log("EWZ: Failed to create table " .  EWZ_ITEM_RATING_TABLE );
       }
       if ( $wpdb->get_var( "SHOW TABLES LIKE '" . EWZ_RATING_FORM_TABLE . "'" ) != EWZ_RATING_FORM_TABLE ) {  // no tainted data
           error_log("EWZ: Failed to create " . EWZ_RATING_FORM_TABLE );
       }
       if ( $wpdb->get_var( "SHOW TABLES LIKE '" . EWZ_RATING_FIELD_TABLE . "'" ) != EWZ_RATING_FIELD_TABLE ) {  // no tainted data
           error_log("EWZ: Failed to create " . EWZ_RATING_FIELD_TABLE );
       }
       if ( $wpdb->get_var( "SHOW TABLES LIKE '" . EWZ_RATING_SCHEME_TABLE . "'" ) != EWZ_RATING_SCHEME_TABLE ) {  // no tainted data
           error_log("EWZ: Failed to create " . EWZ_RATING_SCHEME_TABLE );
       }

        if( $tz_opt ){ 
            date_default_timezone_set( $curr_tz );
        }   
    }

   public static function create_db_tables()
    {
        // NB: This function calls the wordpress dbDelta function. To use it:
        //   You must put each field on its own line in your SQL statement.
        //   You must have two spaces between the words PRIMARY KEY and the definition of your primary key.
        //   You must use the key word KEY rather than its synonym INDEX and you must include at least one KEY.
        //   You must not use any apostrophes or backticks around field names.
        //   Field types must be all lowercase.
        //   SQL keywords, like CREATE TABLE and UPDATE, must be uppercase.
        //   You must specify the length of all field that accept a length parameter. int(11), for example.
        //   No space between parentheses and their contents
        //   No space after commas in key list
        //   Make sure your table name has a leading space before and trailing space after.
        //   one space between the words UNIQUE KEY and the definition of your key.
        //   NOTE: dbDelta will only update new fields or keys. If you remove any field or keys on your table
        //         dbDelta will not work.
        //   If your default value is too long, it will also cause that particular table query to fail
        //   Always specify the LENGTH for each field type that accepts a length.  For most fields use the default length.
        //   tinyint(4) smallint(6)   mediumint(9) int(11)
        //   varchar(1) to varchar(255), 255 is the default.
        //   Fields without lengths: longtext   mediumtext text
        //   Do not put spaces between the field type and the parens or the number in parens for the length.
        //   User lowercase for field name and the field type.
        //   Use uppercase for the NULL and NOT NULL specification.
        //   Do NOT have extra spaces between the field name, the field type, and any of the additional definition parameters.

        error_log( "EWZ: creating / updating rating tables " );


        $item_rating_table = EWZ_ITEM_RATING_TABLE;
        $rating_scheme_table = EWZ_RATING_SCHEME_TABLE;
        $rating_field_table = EWZ_RATING_FIELD_TABLE;
        $rating_form_table = EWZ_RATING_FORM_TABLE;

 $create_rating_scheme_sql = "CREATE TABLE $rating_scheme_table (
 rating_scheme_id mediumint(9) UNSIGNED NOT NULL AUTO_INCREMENT,
 item_layout_id mediumint(9) UNSIGNED NOT NULL,
 scheme_name char(60) NOT NULL UNIQUE,
 restrictions varchar(1000),
 extra_cols varchar(3000),
 scheme_order smallint(6) NOT NULL,
 settings varchar(3000),
 PRIMARY KEY  (rating_scheme_id)
 );";

 $create_rating_form_sql = "CREATE TABLE $rating_form_table (
 rating_form_id mediumint(9) UNSIGNED NOT NULL AUTO_INCREMENT,
 rating_scheme_id mediumint(9) UNSIGNED NOT NULL,
 rating_form_title varchar(100) NOT NULL UNIQUE,
 rating_form_ident char(15) NOT NULL UNIQUE,
 rating_form_order smallint(3) NOT NULL,
 item_selection varchar(3000) NOT NULL,
 shuffle tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
 judges varchar(3000) NOT NULL,
 rating_open tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
 rating_status varchar(1000) NOT NULL,
 PRIMARY KEY  (rating_form_id)
 );";

$create_rating_field_sql = "CREATE TABLE $rating_field_table (
 rating_field_id mediumint(9) UNSIGNED NOT NULL AUTO_INCREMENT,
 rating_scheme_id mediumint(9) UNSIGNED NOT NULL,
 field_type char(3) NOT NULL,
 field_header char(50) NOT NULL,
 field_ident char(15) NOT NULL,
 required tinyint(1) UNSIGNED NOT NULL,
 pg_column smallint(3) NOT NULL,
 ss_column smallint(3) NOT NULL,
 fdata varchar(10000) NOT NULL,
 append tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
 divide tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
 is_second tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
PRIMARY KEY  (rating_field_id),
 UNIQUE KEY uniq_rating_field (rating_scheme_id,field_ident)
 );";

 $create_item_rating_sql = "CREATE TABLE $item_rating_table (
 item_rating_id mediumint(9) UNSIGNED NOT NULL AUTO_INCREMENT,
 rating_form_id mediumint(9) UNSIGNED NOT NULL,
 item_id mediumint(9) UNSIGNED NOT NULL,
 judge_id bigint(20) NOT NULL default 0,
 judge char(255) NOT NULL,
 rating varchar(1000) NOT NULL,
 ratingdate datetime NOT NULL,
 PRIMARY KEY  (item_rating_id),
 UNIQUE KEY uniq_rating (rating_form_id,judge_id,item_id)
 );";

 // need this for the dbDelta function, not automatically loaded
 require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // create the tables if they don't already exist
        // update them if they are changed
        // -- https://codex.wordpress.org/Creating_Tables_with_Plugins
            dbDelta( $create_rating_scheme_sql );
            dbDelta( $create_rating_field_sql );
            dbDelta( $create_rating_form_sql );
            dbDelta( $create_item_rating_sql );
    

    }
}