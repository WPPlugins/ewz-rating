<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/ewz-exception.php");
require_once( EWZ_PLUGIN_DIR . "classes/ewz-base.php");
require_once( EWZ_PLUGIN_DIR . "classes/ewz-layout.php");
require_once( EWZ_PLUGIN_DIR . "classes/ewz-permission.php");


/*****************************************************************************/
/* Interaction with the EWZ_RATING_FIELD table.                              */
/*                                                                           */
/* Rating_fields are components of Rating_Schemes.                           */
/* A rating_field should only be edited/created/destroyed by a Rating_Scheme */
/*****************************************************************************/

// NB: a rating field type is either a regular field type, 'fix', or 'xtr'.  
// A 'fix' field is data uploaded with the original image
// An 'xtr' field is extra data not uploaded with the image ( like upload-date or skill-level )
// 'fix' and 'xtr' fields are displayed read-only to judges
// Other fields are inputs entered by the judges


class Ewz_Rating_Field extends Ewz_Base
{
    // key
    public $rating_field_id;

    // database
    public $rating_scheme_id;
    public $field_type;
    public $field_header;       // header for web page

    public $field_ident;        // slug, and header for spreadsheet

    public $required;           // item is required on web form
    public $pg_column;          // column on web page
    public $ss_column;          // column in spreadsheet
    public $append;             // append this field to the previous cell in the row 
    public $divide;             // divide this field among the judges -- ie only display it to one of them
    public $fdata;              // serialized data structure of parameters specific to the field type
                                //   -- TODO: make each type a sub-class of  Ewz_Rating_Field?
    public $is_second;          // item is only for display on a secondary view

    // keep list of db data names/types as a convenience for iteration and so we can easily add new ones.
    // Dont include rating_field_id here
    public static $varlist = array(
        'rating_scheme_id' => 'integer',
        'field_type'   => 'string',
        'field_header' => 'string',
        'field_ident'  => 'string',
        'required'     => 'boolean',
        'pg_column'    => 'integer',
        'ss_column'    => 'integer',
        'append'       => 'boolean',
        'divide'       => 'boolean',
        'fdata'        => 'array',
        'is_second'    => 'boolean',
    );

    // other data generated
    public static $typelist = array( "opt", "str", "rad", "chk", "fix", "xtr", "lab" );

    public $field;                 // Ewz_Field if type is 'fix'
    public static $col_max = 100;  // for validation only -- max number of columns allowed


    /******************** Section: Static Functions *********************/

   /**
     * Return an array of all the rating_fields attached to the input rating_scheme_id
     *
     * @param   int     $rating_scheme_id
     * @param   string  $orderby   column to sort by - either 'ss_column' or 'pg_column'
     * @return  array   of Ewz_Rating_Fields
     */
    public static function get_rating_fields_for_rating_scheme( $rating_scheme_id, $orderby )
    {
        global $wpdb;
        assert( Ewz_Base::is_pos_int( $rating_scheme_id ) );
        assert( 'ss_column' == $orderby || 'pg_column' == $orderby );

        $list = $wpdb->get_col( $wpdb->prepare( "SELECT rating_field_id  FROM " . EWZ_RATING_FIELD_TABLE . 
                                                " WHERE rating_scheme_id = %d  ORDER BY $orderby ",
                                               $rating_scheme_id ) );
        $rating_fields = array();
        foreach ( $list as $rating_field_id ) {
            $rating_fields[$rating_field_id] = new Ewz_Rating_Field( (int)$rating_field_id );
        }
        return $rating_fields;
    }

    /**
     * Action to be taken when an Ewz_Field is about to be deleted in EntryWizard
     * 
     * Throw an exception if the field is displayed to judges in a rating form
     * Otherwise, remove it from the item_selection's of any rating forms
     *
     * @param   int    $del_field_id    field_id of the Ewz_Field about to be deleted
     * @return none
     **/

    public static function drop_field( $del_field_id ){
        global $wpdb;
        assert( Ewz_Base::is_nn_int( $del_field_id ) );

        $list = $wpdb->get_results( $wpdb->prepare('SELECT r.rating_field_id, r.field_header rheader, r.fdata, f.field_header fheader ' . 
                                                   '  FROM ' .  EWZ_RATING_FIELD_TABLE . " r, " . EWZ_FIELD_TABLE . " f " .
                                                   ' WHERE f.field_id = %d ' .
                                                   '   AND r.fdata LIKE "%%field\_id_;i:%d;%%"',  $del_field_id, $del_field_id  ) ); // for check
        foreach ( $list as $rf ) {
            $fdata = unserialize( $rf->fdata );
            foreach( $fdata as $key => $id ){
                if( $key == 'field_id' && $id == $del_field_id ){
                    throw new EWZ_Exception( "Field '" . $rf->fheader . "' is displayed in a rating scheme as '" . 
                                             $rf->rheader . "'. If you really want to remove it, please delete it from the rating scheme first." );
                }
            }
        }
        Ewz_Rating_Form::drop_field( $del_field_id );       
    }

    /******************** Section: Construction **************************/

    /**
     * Assign the object variables from an array
     *
     * Calls parent::base_set_data with the list of variables and the data structure
     *
     * @param  array  $data input data
     * @return none
     */
    public function set_data( $data )
    {
        assert( is_array( $data ) );
        // first arg is list of valid elements - all of varlist plus rating_field_id
        parent::base_set_data( array_merge( self::$varlist,
                                            array('rating_field_id' => 'integer') ), $data );
    }

    /**
     * Constructor
     *
     * @param  mixed    $init    rating_field_id or array of data
     * @return none
     */
    public function __construct( $init )
    {
        // $init is rating_field_id or initializing data or scheme_id plus field_ident
        assert( Ewz_Base::is_pos_int( $init )
                || ( is_array( $init )
                     && isset( $init['rating_scheme_id'] )
                     && isset( $init['field_ident'] )
                     && isset( $init['field_header'] )
                     && isset( $init['field_type'] )
                     && isset( $init['fdata'] )
                     )
                || ( is_array( $init )
                     && isset( $init['rating_scheme_id'] )
                     && isset( $init['field_ident'] )
                     && ! isset( $init['field_header'] )
                     && ! isset( $init['field_type'] )
                     && ! isset( $init['fdata'] )
                     )
                );
        if ( Ewz_Base::is_pos_int( $init ) ) {
            $this->create_from_id( $init );
        } elseif ( is_array( $init ) ) {
            $this->create_from_data( $init );
        }
        if( 'fix' == $this->field_type ){
            $this->field = new Ewz_Field($this->fdata['field_id']);
        }
    }

    /**
     * Create a new rating_field from the rating_field_id by getting the data from the database
     *
     * @param  int  $id the rating_field id
     * @return none
     */
    protected function create_from_id( $id )
    {
        global $wpdb;
        assert( Ewz_Base::is_pos_int( $id ) );
        if( !$dbrating_field = wp_cache_get( $id, 'ewz_rating_field' ) ){
            $varstring = implode( ',', array_keys( self::$varlist ) );
            $dbrating_field = $wpdb->get_row( $wpdb->prepare( "SELECT rating_field_id, $varstring FROM " . EWZ_RATING_FIELD_TABLE .
                                                              " WHERE rating_field_id=%d", $id ), ARRAY_A );
            if ( !$dbrating_field ) {
                throw new EWZ_Exception( 'Unable to find matching rating_field', $id );
            }
            wp_cache_set( $id, $dbrating_field, 'ewz_rating_field');      
        }
        $this->set_data( $dbrating_field );
    }

    /**
     * Create a rating_field object from $data
     *
     * @param  array  $data
     * @return none
     */
    protected function create_from_data( $data )
    {
        assert( is_array( $data ) );
        if ( ! array_key_exists( 'rating_field_id', $data ) || 
             ! $data['rating_field_id'] || 
             preg_match( '/^X/', $data['rating_field_id'] ) ) {

            $data['rating_field_id'] = 0;
        }
        $this->set_data( $data );
        $this->check_errors();
    }

    /******************** Section: Object Functions **********************/
   /**
    * Return the list of possible values for a rating_field, including "blank", "not blank" or "any"
    *
    * The list is used for restrictions, and each element is an array of the form 
    *      ('value' => return value, 'display' => displayed value, 'selected' => boolean )
    * For 'option' rating_fields it contains all possible options plus "any", and, if the rating_field is not required, "blank" and "not blank"
    * For other non-required rating_fields it just contains "blank", "not blank" or "any"
    *
    * @param  array    $selected   list of values to be displayed as "selected"
    * @return $list  array
    */
    public function get_rating_field_opt_array( $selected = array() )
    {
        assert( is_array( $selected ) );
        if( !$selected ){
            $selected = array( '~*~' );
        }
        $list = array();

        $any_ok   =  array( 'value'=>'~*~', 'display' => 'Any',        'selected' => in_array( '~*~', $selected ) );
        $blank_ok =  array( 'value'=>'~-~', 'display' => 'Blank',      'selected' => in_array( '~-~', $selected ) );
        $isset_ok =  array( 'value'=>'~+~', 'display' => 'Not Blank',  'selected' => in_array( '~+~', $selected ) );
        $unchk_ok =  array( 'value'=>'~-~', 'display' => 'Not Checked','selected' => in_array( '~-~', $selected ) );
        $check_ok =  array( 'value'=>'~+~', 'display' => 'Checked',    'selected' => in_array( '~+~', $selected ) );

        if( $this->field_type == 'xtr' && 'custom' == $this->fdata['dobject'] && method_exists( 'Ewz_Custom_Data', 'selection_list' ) ){ 
            // Custom data. 
            // Only custom data with a defined selection_list function can be used for restrictions, or to select images for judging.
            // For option lists, return all possible values as defined in Ewz_Custom_Data::selection_list
          
            // here there is no possibility that the displayed value differs from the stored one
            $vals =  Ewz_Custom_Data::selection_list( $this->fdata['dkey'] );
            if( $vals ){
                array_push( $list,  $any_ok  );
                foreach ( $vals as $val ){
                    if( $val ){ array_push( $list, array( 'value' => $val, 'display' => $val, 'selected' => in_array( $val, $selected ) ) ); } 
                }
            }
        } elseif( $this->field_type == 'xtr' ) {
            // extra data, only add if "divided" and thus may or may not be blank
            array_push( $list, $any_ok );
            if ( $this->divide ){
                array_push( $list, $blank_ok );
                array_push( $list, $isset_ok );
            }
        } elseif( $this->field_type == 'fix' ) {
            // Data uploaded with the original image. Add if "divided" or orig field may be blank
            array_push( $list, $any_ok );
            if( in_array( $this->field->field_type, array( 'rad', 'chk' ) ) ){
                if( isset( $this->field->fdata['xchklabel'] ) ){
                    array_push( $list, array( 'value'   => $this->field->fdata['xchklabel'], 
                                              'display' => $this->field->fdata['xchklabel'],  
                                              'selected' => in_array( $this->field->fdata['xchklabel'], $selected )
                    ));
                } else {
                    array_push( $list,  $unchk_ok );
                }
                if( isset( $this->field->fdata['chklabel'] ) ){
                    array_push( $list, array( 'value'   => $this->field->fdata['chklabel'], 
                                              'display' => $this->field->fdata['chklabel'],  
                                              'selected' => in_array( $this->field->fdata['chklabel'], $selected )
                    ));
                } else {
                    array_push( $list,  $check_ok );
                }
            } else {
                if ( $this->divide || !$this->field->required ){
                    array_push( $list, $blank_ok );
                    array_push( $list, $isset_ok );
                }
            }
            if( isset( $this->field->fdata['options'] ) ){
                foreach ( $this->field->fdata['options'] as $dat ) {
                    // only the "label" will be visible for checking in javascript, so use that instead of "value"
                    array_push( $list, array( 'value'=> $dat['label'], 'display' => $dat['label'], 
                                              'selected' => in_array( $dat['label'], $selected ) ) ); 
                }
            }
        } elseif( $this->field_type == 'opt' ){
            // option lists, return all possible values plus 'Any' ( and 'Blank', 'Not Blank' if not required).
            array_push( $list,  $any_ok );
            if( $this->divide || !$this->required ){
                array_push( $list, $blank_ok );
                array_push( $list, $isset_ok );
            }
            foreach ( $this->fdata['options'] as $dat ) {
                array_push( $list, array( 'value'=>$dat['value'],  'display'=> $dat['label'],
                'selected' => in_array( $dat['value'],  $selected ) ) );
            }
        } elseif( in_array( $this->field_type, array( 'rad', 'chk' ) ) ){
            // checkboxes and radio buttons -- 'Any', 'Checked' or 'Not Checked'.  Cannot be required. 
            array_push( $list,  $any_ok );
            array_push( $list,  $unchk_ok );
            array_push( $list,  $check_ok );
        } elseif( $this->divide || !$this->required ){
            // 'txt' or 'lab'
            // required items cannot be blank unless they are "divided", so there are no options in that case
            array_push( $list, $any_ok );
            array_push( $list, $blank_ok );
            array_push( $list, $isset_ok );
        }
    
        if( count($list) > 1 ){
            return $list;
        } else {
            return array();
        }
    }

    /**
     * Should the field be allowed to be part of a restriction?
     *
     * Return true if the field is either possibly blank or may be an option list, otherwise false
     *
     * @return  boolean
     **/
    public function has_option_list()
    {
        switch( $this->field_type ){
        case 'opt':
            return true;
        case 'str':
            return true;
        case 'fix':
            return $this->divide || !$this->field->required || ( 'opt' == $this->field->field_type );
        case 'xtr':
            return  in_array( $this->fdata['dkey'], array(  'att', 'aat', 'aae', 'aac', 'add' ) ) || 
                ( 'custom' == substr( $this->fdata['dkey'], 0, 6 ) ) || $this->divide;
        case 'rad':
            return true;
        case 'chk':
            return true;
        case 'lab':
            return $this->divide;
        default:   return true;
        }
    }

    /******************** Section: Validation  *******************************/

    /**
     * Check for various error conditions, and raise an exception when one is found
     *
     * @param  none
     * @return none
     */
    protected function check_errors()
    {
        global $wpdb;
        if ( is_string( $this->fdata ) ) {
            $this->fdata = unserialize( $this->fdata );
            if( !is_array( $this->fdata ) ){
                $this->fdata = array();
                error_log("EWZ: failed to unserialize fdata for rating_field $this->rating_field_id") ; 
            }            
        }
        foreach ( self::$varlist as $key => $type ) {
            settype( $this->$key, $type );
        }
        if ( $this->rating_scheme_id && !Ewz_Rating_Scheme::is_valid_rating_scheme( $this->rating_scheme_id ) ) {
            throw new EWZ_Exception( 'Rating_Scheme is not a valid one', $this->rating_scheme_id );
        }

        // check for valid rating_field type, ident
        if ( !in_array( $this->field_type, self::$typelist ) ) {
            throw new EWZ_Exception( 'Invalid rating_field type ' . $this->field_type );
        }
        if ( !preg_match( '/^[0-9a-zA-Z_\-]+$/', $this->field_ident ) ) {
            throw new EWZ_Exception( 'Invalid rating_field identifier ' . $this->field_ident .
                                     ' The value may contain only letters, digits, dashes or underscores' );
        }
        if( $this->rating_field_id ){
            // check for change of rating_scheme ( should not happen ) 
            $db_rating_scheme_id = (int)$wpdb->get_var( $wpdb->prepare( "SELECT rating_scheme_id  FROM " . EWZ_RATING_FIELD_TABLE . 
                                                                        " WHERE rating_field_id = %d",
                                                                        $this->rating_field_id ) );
            if( $db_rating_scheme_id && ( $db_rating_scheme_id != $this->rating_scheme_id ) ){
                throw new EWZ_Exception( 'Invalid rating_scheme for rating_field ', 
                                         "rating_field ~{$this->rating_field_id}~ rating_scheme ~{$this->rating_scheme_id}~ uploaded by user " . 
                                         get_current_user_id() . " rating_scheme should be ~{$db_rating_scheme_id}~"   );
            }
        }
        // for 'fix' type, check for existing Ewz_Field
        if ( $this->field_type == 'fix' ){
            if( ! $wpdb->get_var( $wpdb->prepare( "SELECT count(*)  FROM " . EWZ_FIELD_TABLE . " WHERE field_id = %d",
                                                   $this->fdata['field_id'] ) ) ){
                throw new EWZ_Exception( 'Invalid field id ' . (int)$this->fdata['field_id'] . ' for rating field' );
            }
        }
            
            
            

        // check for key duplication        
        $existing = $wpdb->get_results( $wpdb->prepare( "SELECT field_header, field_ident  FROM " . EWZ_RATING_FIELD_TABLE .
                                                        " WHERE rating_scheme_id = %d AND rating_field_id != %d",
                                                        $this->rating_scheme_id, $this->rating_field_id ), ARRAY_A ); // for check
       foreach( $existing as $itm ){
            if( $itm['field_header'] == $this->field_header ){
                throw new EWZ_Exception( 'Rating_field name ' . $this->field_header . ' already in use for this rating_scheme' );
            } 
            if( $itm['field_ident'] == $this->field_ident ){
                throw new EWZ_Exception( 'Rating_field identifier ' . $this->field_ident . ' already in use for this rating_scheme' );
            }            
        }

        // -1 is essentially null for ss_column
        if ( $this->ss_column < -1 || $this->ss_column > self::$col_max ) {
            throw new EWZ_Exception( 'Invalid value ' . $this->ss_column . ' for spreadsheet column' );
        }
        if ( $this->pg_column < 0 || $this->pg_column > self::$col_max ) {
            throw new EWZ_Exception( 'Invalid value ' . $this->pg_column  . ' for web page column' );
        }

        // checkboxes and radio buttons cannot be required
        if( (  $this->field_type == 'rad' ) &&  $this->required ){
             throw new EWZ_Exception( 'Radio Button rating fields may not be "required"' );
        }
        // label must be required
        if( (  $this->field_type == 'lab' ) &&  !$this->required ){
             throw new EWZ_Exception( 'Label rating fields must be "required"' );
        }
    }

    /******************** Section: Database Updates ******************/

    /**
     * Save the rating_field to the database
     *
     * Check for permissions, then update or insert the data
     * Return the rating_field id if rating_field is new -- needed for adding rating_field to restrictions
     *
     * @param none
     * @return rating_field id if this is a new rating_field, otherwise 0  
     */
    public function save()
    {
        global $wpdb;
        if( is_int( $this->rating_scheme_id ) ){
           if ( !Ewz_Rating_Permission::can_edit_rating_scheme( $this->rating_scheme_id ) ) {
               throw new EWZ_Exception( 'Insufficient permissions to edit rating_scheme', $this->rating_scheme_id );
           }
        } else {
            if ( !Ewz_Rating_Permission::can_edit_all_schemes() ) {
                throw new EWZ_Exception( 'Insufficient permissions to create a new rating_scheme' );
            }
        }
        $this->check_errors();
        wp_cache_delete( $this->rating_field_id, 'ewz_rating_field' );

        //**NB:  for safety, stripslashes *before* serialize as well, otherwise character counts may be wrong
        //       ( currently should  not be needed in this case because of the rating_field data restrictions )
        $data = stripslashes_deep( array(
                                         'rating_scheme_id' => $this->rating_scheme_id,  // %d
                                         'field_type' => $this->field_type,          // %s  
                                         'field_header' => $this->field_header,      // %s
                                         'field_ident' => $this->field_ident,        // %s
                                         'required' => $this->required ? 1 : 0,      // %d
                                         'pg_column' => $this->pg_column,            // %d
                                         'ss_column' => ( '' === $this->ss_column ) ? '-1' : $this->ss_column,  // %d
                                         'append' => $this->append ? 1 : 0,          // %d
                                         'divide' => $this->divide ? 1 : 0,          // %d
                                         'is_second' => $this->is_second ? 1 : 0,          // %d
                                         'fdata' => serialize( stripslashes_deep( $this->fdata ) )   // %s
                ) );
         $datatypes = array( '%d',    // = rating_scheme_id
                             '%s',    // = field_type
                             '%s',    // = field_header
                             '%s',    // = field_ident
                             '%d',    // = required
                             '%d',    // = pg_column
                             '%d',    // = ss_column
                             '%d',    // = append
                             '%d',    // = divide
                             '%d',    // = is_second
                             '%s',    // = fdata
                            );

        if ( $this->rating_field_id ) {
            // have an id, update all the data
            $rows = $wpdb->update( EWZ_RATING_FIELD_TABLE,                                     // no esc
                                   $data,        array( 'rating_field_id' => $this->rating_field_id ), 
                                   $datatypes,   array( '%d' ) );
            if ( $rows > 1 ) {
                throw new EWZ_Exception( 'Failed to update rating_field', $this->rating_field_id );
            }
            return 0;
        } else {
            // new field, make sure scheme exists, then insert
            if ( Ewz_Rating_Scheme::is_valid_rating_scheme( $this->rating_scheme_id ) != 1 ) {
                throw new EWZ_Exception( 'Failed to find scheme for update of rating field', $this->rating_scheme_id );
            }

            $wpdb->insert( EWZ_RATING_FIELD_TABLE, $data, $datatypes );                                    // no esc
            $this->rating_field_id = $wpdb->insert_id;
            if ( !$this->rating_field_id ) {
                throw new EWZ_Exception( 'Failed to create new rating_field', $this->field_ident . ' '. $wpdb->last_error );
            }
            return $this->rating_field_id;
        }
    }

    /**
     * Delete the rating_field  from the database.  Raise an exception if there is an item_rating
     * with data in this field.
     *
     * @param  none
     * @return none
     */
    public function delete()
    {
        global $wpdb;
        if ( !Ewz_Rating_Permission::can_edit_rating_scheme( $this->rating_scheme_id ) ) {
            throw new EWZ_Exception( 'Insufficient permissions to edit the rating_scheme', $this->rating_scheme_id );
        }

        $order = (int)$wpdb->get_var( $wpdb->prepare( "SELECT pg_column  FROM " . EWZ_RATING_FIELD_TABLE . 
                                                         " WHERE rating_field_id = %d",
                                                         $this->rating_field_id ) ); 
        
        if( !in_array( $this->field_type, array('fix', 'xtr', 'lab' ) ) ){
            $ratinglist =  Ewz_Item_Rating::get_ratings_for_scheme( $this->rating_scheme_id );
            foreach( $ratinglist as $r ){
                $rating = unserialize($r->rating);
                if( isset( $rating[$this->rating_field_id] ) ){
                    throw new EWZ_Exception( "Attempt to delete rating-scheme field with data already saved in rating form " . $r->rating_form_title );
                }
            }
        }
                                                                          
        wp_cache_delete( $this->rating_field_id, 'ewz_rating_field' );

        $rowsaffected = $wpdb->query( $wpdb->prepare( "DELETE FROM " . EWZ_RATING_FIELD_TABLE . " WHERE rating_field_id = %d",
                                               $this->rating_field_id ) );
        if ( $rowsaffected != 1 ) {
            throw new EWZ_Exception( 'Failed to delete rating_field', $this->rating_field_id );
        }
        $rowsaffected = $wpdb->query( $wpdb->prepare( "UPDATE  " . EWZ_RATING_FIELD_TABLE . 
                                                      " SET pg_column = pg_column - 1 " .
                                                      " WHERE rating_scheme_id = %d AND  pg_column > %d",
                                                      $this->rating_scheme_id, $order ) );
    }
}
