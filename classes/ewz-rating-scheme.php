<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/ewz-exception.php" );
require_once( EWZ_PLUGIN_DIR . "classes/ewz-base.php" );
require_once( EWZ_RATING_DIR . "classes/ewz-rating-field.php" );
require_once( EWZ_RATING_DIR . "classes/ewz-rating-permission.php" );
require_once( EWZ_CUSTOM_DIR . "ewz-custom-data.php" );

/********************************************************************************************
 * Interaction with the EWZ_RATING_SCHEME table.
 *
 * Determines the appearance of a rating form. Contains the title, any
 *      restrictions ( which are stored as serialized arrays ) and any extra information columns
 *      ( which are for the spreadsheet only, and are also stored  as serialized arrays ).
 *
 * Is associated with a unique layout, and may be used only for items that were uploaded via
 *       a webform using that layout.
 *
 * Each Ewz_Rating_Scheme also "contains" several Ewz_Rating_Fields, which correspond to the EWZ_RATING_FIELD table.
 *    -- these are the fields a judge must fill out, or that are displayed read-only to the judge
 *
 * Each Ewz_Rating_Field contains a rating_scheme_id which specifies which Ewz_Rating_Scheme it belongs to.
 *
 ********************************************************************************************/

class Ewz_Rating_Scheme extends Ewz_Base
{

    const DELETE_FORMS = 1;
    const FAIL_IF_FORMS = 0;

    // key
    public $rating_scheme_id;

    // data stored on db
    public $item_layout_id;       // id of the associated layout
    public $scheme_name;          // name for display 
    public $restrictions;         // any restrictions on combinations of field values that are allowed
    public $extra_cols;           // columns for the spreadsheet generated from WP member data and other tables
    public $scheme_order;         // to specify display order in the set of all rating schemes
    public $settings;             // serialized data containing image sizes, background colour, etc

    // other data generated
    public $item_layout;          // Ewz_Layout object
    public $fields;               // array of Ewz_Rating_Fields
    public $n_rating_forms;       // number of rating_forms using the rating_scheme - for warning on the rating_form edit page
    public $n_item_ratings;       // number of items rated using the rating_scheme


    // non-key data with php type
    public static $varlist = array(
        'item_layout_id'  => 'integer',
        'scheme_name'   => 'string',
        'restrictions'  => 'array',
        'extra_cols'    => 'array',
        'scheme_order'  => 'integer',
        'settings'      => 'array',
   );


    /********************  Section: "Extra" Data -- for spreadsheet display only ********************/

    // items selectable for display in spreadsheet or read-only to judges
    // a value containing '>' is interpreted so that origin='object', value='property>key' becomes 'object->property[key]'
    // a value containing '|' is interpreted so that origin='object', value='property|key' where property is an array 
    //          becomes the concatenation of all object->property[key] values in the array
    //          e.g. item_data is an array of data whose keys are the rating_field_ids
    //             item_data|pexcerpt becomes the concatenation of all uploaded excerpts for all image fields in the item
    // other values are interpreted so that origin='object', value='property' becomes 'object->property'

    protected static $display_data_ewz =
        array(
              'att' => array(  'header' => 'Attached To',   'dobject' => 'item', 'origin' => 'EWZ Item',    'value' => 'item_data>attachedto' ),
              'aat' => array(  'header' => 'Added Title',   'dobject' => 'item', 'origin' => 'EWZ Item',    'value' => 'item_data|ptitle' ),
              'aae' => array(  'header' => 'Added Caption', 'dobject' => 'item', 'origin' => 'EWZ Item',    'value' => 'item_data|pexcerpt' ),
              'aac' => array(  'header' => 'Added Description', 'dobject' => 'item', 'origin' => 'EWZ Item', 'value' => 'item_data|pcontent' ),
              'add' => array(  'header' => 'Added Item Data','dobject'=> 'item', 'origin' => 'EWZ Item',    'value' => 'item_data>admin_data' ),
              'dlc' => array(  'header' => 'Last Changed',  'dobject' => 'item', 'origin' => 'EWZ Item',    'value' => 'last_change' ),
              'dtu' => array(  'header' => 'Upload Date',   'dobject' => 'item', 'origin' => 'EWZ Item',    'value' => 'upload_date' ),
              'iid' => array(  'header' => 'WP Item ID',    'dobject' => 'item', 'origin' => 'EWZ Item',    'value' => 'item_id' ),
              'wft' => array(  'header' => 'Webform Title', 'dobject' => 'wform','origin' => 'EWZ Webform', 'value' => 'webform_title' ),
              'wid' => array(  'header' => 'WP Webform ID', 'dobject' => 'wform','origin' => 'EWZ Webform', 'value' => 'webform_id' ),
              'wfm' => array(  'header' => 'Webform Ident', 'dobject' => 'wform','origin' => 'EWZ Webform', 'value' => 'webform_ident' ),
              'nam' => array(  'header' => 'Full Name',  'dobject' => 'user', 'origin' => 'WP User', 'value' => array('first_name',' ','last_name') ),
              'fnm' => array(  'header' => 'First Name',   'dobject' => 'user', 'origin' => 'WP User',   'value' => 'first_name' ),
              'lnm' => array(  'header' => 'Last Name',    'dobject' => 'user', 'origin' => 'WP User',   'value' => 'last_name' ),
              'mnm' => array(  'header' => 'Display Name', 'dobject' => 'user', 'origin' => 'WP User',   'value' => 'display_name' ),
              'mem' => array(  'header' => 'Email',        'dobject' => 'user', 'origin' => 'WP User',   'value' => 'user_email' ),
              'mid' => array(  'header' => 'WP User ID',   'dobject' => 'user', 'origin' => 'WP User',   'value' => 'ID' ),
              'mli' => array(  'header' => 'User Login',   'dobject' => 'user', 'origin' => 'WP User',   'value' => 'user_login' ),
              );

    /*
     * Return an array of all the headers for the spreadsheet or item list
     */
    public static function get_all_display_headers(){

        $data = self::$display_data_ewz;

        foreach( Ewz_Custom_Data::$data as $customN => $header ){
            $data[$customN] = array( 'header'  => $header,
                                     'dobject' => 'custom',
                                     'origin'  => 'Custom',
                                    );
        }
        return $data;
    }

    /*
     * Return all the data for the spreadsheet or item list
     */
    public static function get_all_display_data(){
        $data = self::$display_data_ewz;

        foreach( Ewz_Custom_Data::$data as $customN => $header ){
            $data[$customN]  = array( 'header'  => $header,
                                      'dobject' => 'custom',
                                      'origin'  => 'Custom',
                                      'value'   => $customN,
                                    );
        }
        return $data;
    }

    /**
     * Get an extra data item ( user, item, webform or custom info ) for display
     *
     * @param  $dobj       data source object: Ewz_Webform, Ewz_Item, Ewz_Custom or WP_User
     * @param  $keyholder  array with keys 'dtu',  'nam', ... -- extra data required for display
     *
     */
    public static function get_extra_data_item( $dobj, $keyholder ){
        assert( is_object( $dobj ) );
        assert( is_array( $keyholder ) || is_string( $keyholder ) );
        // if this is a user object, return blank if current user doesnt have permission to list
        if( isset( $dobj->user_login ) && !current_user_can( 'list_users' ) ){
            return '';
        }
        $value = '';
        if( is_array( $keyholder ) ){
            // may be used for custom data arrays
            foreach( $keyholder as $key ){
                if( isset( $dobj->$key ) ){
                    $value .= $dobj->$key;
                } else {
                    $value .= $key;
                }
            }
        } else {
            if( strpos(  $keyholder, '>' ) !== false ){
                $xx = explode( '>', $keyholder );
                if( isset( $dobj->{$xx[0]}[$xx[1]] ) ){
                    $value = $dobj->{$xx[0]}[$xx[1]];
                }  
            } elseif(  strpos(  $keyholder, '|' ) !== false ){
                $xx = explode( '|', $keyholder );
                if( is_array( $dobj->{$xx[0]} ) ){
                    $value = '';
                    foreach(  $dobj->{$xx[0]} as $field_id=>$fielddata ){
                        if( isset( $fielddata[$xx[1]] ) ){
                            $value .= $fielddata[$xx[1]];
                        }
                    }
                }
            } else {
                $value = $dobj->$keyholder;
            }
        }
        if( isset($value )){
            return (string)$value;
        }
        return '';
      }

    /********************  Section:  Other Static Functions****************************/

    /**
     * Background colour options for main image window
     *
     * @return array of ( 'value'=>... , 'display'=>... , 'selected'=>... )
     **/
    public static function get_bcolor_opts($bcol = ''){
        assert( is_string( $bcol ) );
        if(!$bcol ){
            $bcol = "#000000";
        }
        return array( 
            array( 'value'=>"#FFFFFF", 'display'=>'White',      'selected'=>( "$bcol" == "#FFFFFF" ) ),
            array( 'value'=>"#F5F5F5", 'display'=>'White Smoke','selected'=>( "$bcol" == "#FFFFFF" ) ),
            array( 'value'=>"#D3D3D3", 'display'=>'Light Gray', 'selected'=>( "$bcol" == "#D3D3D3" ) ),
            array( 'value'=>"#C0C0C0", 'display'=>'Silver',     'selected'=>( "$bcol" == "#C0C0C0" ) ),
            array( 'value'=>"#A9A9A9", 'display'=>'Dark Gray',  'selected'=>( "$bcol" == "#A9A9A9" ) ),
            array( 'value'=>"#808080", 'display'=>'Gray',       'selected'=>( "$bcol" == "#808080" ) ),
            array( 'value'=>"#696969", 'display'=>'Dim Gray',   'selected'=>( "$bcol" == "#333333" ) ),
            array( 'value'=>"#333333", 'display'=>'Gray20',     'selected'=>( "$bcol" == "#333333" ) ),
            array( 'value'=>"#000000", 'display'=>'Black',      'selected'=>( "$bcol" == "#000000" ) ),
        );
    }

    /**
     * Foreground colour options for main image window
     *
     * @return array of ( 'value'=>... , 'display'=>... , 'selected'=>... )
     **/
    public static function get_fcolor_opts ($fcol='' ){
        assert( is_string( $fcol ) );
        if(!$fcol ){
            $fcol = "#FFFFFF";
        }
        return array( 
            array( 'value'=>"#FFFFFF", 'display'=>'White',      'selected'=>( "$fcol" == "#FFFFFF" ) ),
            array( 'value'=>"#D3D3D3", 'display'=>'Light Gray', 'selected'=>( "$fcol" == "#D3D3D3" ) ),
            array( 'value'=>"#C0C0C0", 'display'=>'Silver',     'selected'=>( "$fcol" == "#C0C0C0" ) ),
            array( 'value'=>"#A9A9A9", 'display'=>'Gray',       'selected'=>( "$fcol" == "#A9A9A9" ) ),
            array( 'value'=>"#808080", 'display'=>'Dark Gray',  'selected'=>( "$fcol" == "#808080" ) ),
            array( 'value'=>"#000000", 'display'=>'Black',      'selected'=>( "$fcol" == "#000000" ) ),
        );
    }

    /**
     * Return the layout ( used for uploaded items ) the scheme is set up for
     *
     * @param $rating_scheme_id   id of this rating scheme
     * @return int  the layout_id
     **/
    public static function get_item_layout_id( $rating_scheme_id ){
        global $wpdb;
        assert( Ewz_Base::is_nn_int( $rating_scheme_id ) );
        return (int)$wpdb->get_var( $wpdb->prepare( "SELECT item_layout_id  FROM " . EWZ_RATING_SCHEME_TABLE . 
                                               " WHERE  rating_scheme_id = %d", $rating_scheme_id ));
    }


    /**
     * Return an array of all defined rating_schemes satisfying a filter
     *
     * @param   callback    $filter        Filter function that must return true for the rating_scheme_id
     *                                            ( used to check permissions )
     * @return  array of all defined rating_schemes satisfying the filter
     */
    public static function get_all_rating_schemes( $filter = 'truefunc' )
    {
        global $wpdb;
        assert(is_string( $filter ) );
        
        $list = $wpdb->get_col( "SELECT rating_scheme_id  FROM " . EWZ_RATING_SCHEME_TABLE . " ORDER BY scheme_order" );  // no tainted data
        $rating_schemes = array();
        foreach ( $list as $rating_scheme_id ) {
            $rating_scheme_id = (int)$rating_scheme_id;
            if ( call_user_func( array( 'Ewz_Rating_Permission',  $filter ), $rating_scheme_id ) ) {
                $rating_scheme = new Ewz_Rating_Scheme( $rating_scheme_id );
                $rating_schemes[$rating_scheme_id] = $rating_scheme;
            }
        }
        return $rating_schemes;
    }

    /**
     * Get all rating_scheme_id, item_layout_id pairs
     *
     * @return  object consisting of all rating_scheme_id, item_layout_id pairs
     */
    public static function get_all_scheme_layout_ids( )
    {
        global $wpdb;
        
        // order by not really needed, but safer to have it predictable
        $list = $wpdb->get_results( "SELECT rating_scheme_id, item_layout_id  FROM " . EWZ_RATING_SCHEME_TABLE .  // no tainted data
                                    " ORDER BY rating_scheme_id, item_layout_id", OBJECT );
        return $list;
    }
        
    /**
     * Action taken just before a layout is deleted in EntryWizard
     *
     * Raise an exception if a rating scheme is associated with the layout
     *
     * @param  $del_layout_id   id of the layout to be deleted
     * @return  none
     **/
    public static function drop_layout( $del_layout_id ){
        global $wpdb;
        assert(Ewz_Base::is_pos_int( $del_layout_id ) );
        
        $n = (int)$wpdb->get_var( $wpdb->prepare( "SELECT count(*) FROM " . EWZ_RATING_SCHEME_TABLE . 
                                                  " WHERE item_layout_id = %d ",  $del_layout_id  ) );
        if( $n > 0 ){
            throw new Ewz_Exception( "Attempt to delete a layout which has an associated rating_scheme.");
        }
    }

    /**
     * Return the data required for a drop-down list of rating schemes 
     * 
     * @param   callback  $filter        Permissions filter function that must return true for the scheme's rating_scheme_id
     * @param   integer   $selected_id   id of selected item, 0 for none selected
     * @return  array of ( value, display, selected ) to use as input for ewz_option_list
     */
    public static function get_rating_scheme_opt_array( $filter = 'truefunc', $selected_id = 0 )
    {
        global $wpdb;
        assert( Ewz_Base::is_nn_int( $selected_id ) );
        assert(is_string( $filter ) );

        $options = array();
        $rating_schemes = $wpdb->get_results( "SELECT rating_scheme_id, scheme_name  FROM " . EWZ_RATING_SCHEME_TABLE .  // no tainted data
                                              " ORDER BY scheme_order", OBJECT );
        foreach ( $rating_schemes as $rating_scheme_pair ) {
            $rating_scheme_pair->rating_scheme_id = (int)$rating_scheme_pair->rating_scheme_id;
            if ( call_user_func( array( 'Ewz_Rating_Permission',  $filter ), $rating_scheme_pair->rating_scheme_id ) ) {
                if ( $rating_scheme_pair->rating_scheme_id == $selected_id ) {
                    $is_sel = true;
                } else {
                    $is_sel = false;
                }
                array_push( $options, array( 'value' => $rating_scheme_pair->rating_scheme_id ,
                                             'display' => $rating_scheme_pair->scheme_name,
                                             'selected' => $is_sel ) );
            }
        }
        return $options;
    }


    /*
     * Used as a default filter when none is specified
     */
    public static function truefunc()
    {
        return true;
    }

    /**
     * Make sure the input rating_scheme_id is a valid one
     *
     * @param    int      $rating_scheme_id
     * @return   boolean  true if $rating_scheme_id is the key for a EWZ_RATING_SCHEME_TABLE row, otherwise false
     */
    public static function is_valid_rating_scheme( $rating_scheme_id )
    {
        global $wpdb;
        assert( Ewz_Base::is_nn_int( $rating_scheme_id ) );
        $count = (int)$wpdb->get_var( $wpdb->prepare( "SELECT count(*)  FROM " .
                                                      EWZ_RATING_SCHEME_TABLE . " WHERE  rating_scheme_id = %d",
                                                      $rating_scheme_id ) );
        return ( 1 == $count );
    }

     /**
      * Renumber the subsequent rating_schemes when one is deleted
      * @param  integer $order  order of deleted scheme
      * @return none
      */
     private static function renumber_rating_schemes( $order ) {
         global $wpdb;
         assert( Ewz_Base::is_nn_int( $order ) );
         $wpdb->query($wpdb->prepare("UPDATE " . EWZ_RATING_SCHEME_TABLE . " wf " .
                                     "   SET scheme_order = scheme_order - 1 WHERE  scheme_order > %d " , $order ));  
     }
 
     /* Save the order of the rating_schemes
      *
      * @param   s_order  array of ( $rating_scheme_id => $order ) pairs.
      * @return  number of rows updated
      */
     public static function save_scheme_order( $s_orders ) {
         global $wpdb;
         assert( is_array($s_orders['scorder']) );
         $n = 0;
         foreach( $s_orders['scorder'] as $rating_scheme_id => $order ){
             $n = $n + $wpdb->query($wpdb->prepare("UPDATE " . EWZ_RATING_SCHEME_TABLE . " wf " .
                                                   "   SET scheme_order = %d WHERE rating_scheme_id = %d ", $order, $rating_scheme_id ));  
         }
         return $n;
     }
 
 
    /********************  Section: Construction ****************************/
    /**
     * Assign the object variables from an array
     *
     * Calls parent::base_set_data with the list of variables and the data structure
     * Sets a default value for "settings" if there is none.
     *
     * @param  array   $data input data array.
     * @return none
     */
    protected function set_data( $data )
    {
        assert( is_array( $data ) );
        parent::base_set_data( array_merge(  array('rating_scheme_id' => 'integer'),  self::$varlist ),
                               $data );
        if( $data['item_layout_id'] ){
            try{
                $this->item_layout = new Ewz_Layout($data['item_layout_id']);
            } catch( Exception $e ) {
                $this->item_layout = new stdClass();
            }
        } elseif( $data['item_layout_id'] == 0 ) {
            $this->item_layout = new stdClass();
        } else {
            throw new EWZ_Exception( 'Missing item layout for rating form' );            
        }
        if( !isset( $this->settings ) ){
            $this->settings = array( 
                'maxw' => 1024,
                'maxh' => 768,
                'bcol' => '#000000',             // large image background
                'fcol' => '#FFFFFF',             // large image text
                'bg_main' => '#FFFFFF',          // main table background
                'bg_curr' => '#EEEEEE',          // main table current row
                'new_border' => '#000000',       // main table border for unsaved rows
                'img_pad' => 150,
                'summary' => 1 ,      // display the summary
                'finished' => 1,      // display the 'finished' dialog
                'jhelp' => 1,         // display the judge help dialog
                'testimg' => '',
            );
        }
        if( !isset( $this->settings['testimg'] ) ){
            $this->settings['testimg'] = '';
        }
        if( !isset( $this->settings['bcol'] ) ){
            $this->settings['bcol'] = '#000000';
        }
        if( !isset( $this->settings['fcol'] ) ){
            $this->settings['fcol'] = '#FFFFFF';
        }
        if( !isset( $this->settings['bg_main'] ) ){
            $this->settings['bg_main'] = '#FFFFFF';
        }
        if( !isset( $this->settings['bg_curr'] ) ){
            $this->settings['bg_curr'] = '#EEEEEE';
        }
        if( !isset( $this->settings['new_border'] ) ){
            $this->settings['new_border'] = '#000000';
        }
        if( !isset( $this->settings['jhelp'] ) ){
            $this->settings['jhelp'] = 1;
        }
    }

    /**
     * Constructor
     *
     * @param  mixed  $init  rating_scheme_id or array of data
     * @return none
     */
    public function __construct( $init )
    {
        // no assert
        if ( is_numeric( $init ) ) {
            $this->create_from_id( $init );
        } elseif ( is_array( $init ) ) {
            if ( isset( $init['rating_scheme_id'] ) && $init['rating_scheme_id'] ) {
               $this->update_from_data( $init );
            } else {
                if( isset( $init['scheme_name'] ) ){
                    $this->create_from_data( $init );
                } else {
                    throw new EWZ_Exception( 'Bad init data for rating scheme', $init );
                }
            }
        }
    }

    /**
     * Create a new rating_scheme from the rating_scheme_id by getting the data from the database
     *
     * Creates the fields array from the database
     *
     * @param  int  $id  the rating_scheme id
     * @return none
     */
    protected function create_from_id( $id )
    {
        global $wpdb;
        assert( Ewz_Base::is_pos_int( $id ) );
        $dbrating_scheme = $wpdb->get_row( $wpdb->prepare(
                "SELECT rating_scheme_id, " . implode( ',', array_keys( self::$varlist ) ) .
                " FROM " . EWZ_RATING_SCHEME_TABLE .
                " WHERE rating_scheme_id=%d", $id ), ARRAY_A );
        if ( !$dbrating_scheme ) {
            throw new EWZ_Exception( 'Unable to find rating_scheme', $id );
        }
        $this->set_data( $dbrating_scheme );
        $this->fields = Ewz_Rating_Field::get_rating_fields_for_rating_scheme( $this->rating_scheme_id, 'pg_column' );
        $this->update_usage_counts();
        $this->check_errors();
    }

    /**
     * Create a rating_scheme object from $data, which contains a "rating_scheme_id" key
     *
     * Error if  $data['rating_scheme_id'] does not exist on database
     *
     * @param  array  $data
     * @return none
     */
    protected function update_from_data( $data )
    {
        global $wpdb;
        assert( is_array( $data ) );
        $ok = (int)$wpdb->get_var( $wpdb->prepare( "SELECT count(*)  FROM " . EWZ_RATING_SCHEME_TABLE .
                                                   " WHERE rating_scheme_id = %d", $data['rating_scheme_id'] ) );
        if ( $ok != 1 ) {
            throw new EWZ_Exception( 'Unable to find rating_scheme', $data['rating_scheme_id'] );
        }

        $this->set_data( $data );
        $this->set_field_data( $data );
        $this->update_usage_counts();
    }

    /**
     * Create a new  rating_scheme object from $data, which has no "rating_scheme_id" key
     *
     * Set the new object's rating_scheme_id to 0 and it's usage counts to 0
     * Called either when creating a new scheme from the admin screen, or creating an empty one for passing to javascript
     *
     * @param array $data
     * @return none
     */
    protected function create_from_data( $data )
    {
        assert( is_array( $data ) );
        assert( empty( $data['rating_scheme_id'] ) );
        assert( isset( $data['item_layout_id'] ) );     // may be 0 if this is the "empty scheme" for passing to javascript
        $this->rating_scheme_id = 0;
        $this->n_rating_forms = 0;
        $this->n_item_ratings = 0;

        $this->set_data( $data );
        $data['item_layout_id'] = intval( $data['item_layout_id'], 10 );
        
        $this->set_field_data( $data );
        $this->check_errors();
    }

    /**
     * Set the "fields" array from $data
     *
     * Creates a new Ewz_Rating_Field object from each element of $data['fields']
     *
     * @param  array  $data
     * @return none
     */
    protected function set_field_data( $data )
    {
        assert( is_array( $data ) );
        $this->fields = array();
        if( isset($data['fields']) ){
            // index $num is field_id for existing fields, 'X' + field position for new ones
            foreach ( $data['fields'] as $num => $field_data ) {
                $field_data['rating_scheme_id'] = $this->rating_scheme_id;
                $field = new Ewz_Rating_Field( $field_data );
                $this->fields[$num] = $field;  // NB: $num will still contain 'X' for new fields
            }
        }
    }

    /**
     * Set "n_rating_forms" and "n_item_ratings" to the counts of matching rating_forms/item_ratings from the database
     *
     * @return  none
     */
    protected function update_usage_counts()
    {       
        $this->n_rating_forms = Ewz_Rating_Form::get_count_for_scheme( $this->rating_scheme_id );

        $this->n_item_ratings = Ewz_Item_Rating::get_scheme_count( $this->rating_scheme_id );
    }

    /********************  Section: Object Functions ****************************/

    /**
     * Return true if any field has the "divide" flag set
     *
     * @return  boolean
     */
    public function has_divide(){
        foreach( $this->fields as $field ){
            if( $field->divide ){
                return true;
            }
        }
        return false;
    }


    /********************  Section: Validation  ****************/

    /**
     * Check for various error conditions
     *
     * @param  none
     * @return none
     */
    protected function check_errors()
    {
        foreach ( self::$varlist as $key => $type ) {
            settype( $this->$key, $type );
        }

        global $wpdb;

        // check for duplicate keys
        $used = (int)$wpdb->get_var( $wpdb->prepare( "SELECT count(*)  FROM " . EWZ_RATING_SCHEME_TABLE .
                                                     " WHERE scheme_name = %s AND rating_scheme_id != %d", $this->scheme_name,
                                                     $this->rating_scheme_id ) );
        if ( $used > 0 ) {
            throw new EWZ_Exception( "Name '$this->scheme_name' already in use for this rating_scheme"  );
        }
        if ( is_string( $this->settings ) ) {
            $this->settings = self::array_unserial( $this->settings, 'settings' );
        }
        if ( is_string( $this->restrictions ) ) {
            $this->restrictions = self::array_unserial( $this->restrictions, 'restrictions' );
        }
        if ( is_string( $this->extra_cols ) ) {
            $this->extra_cols = self::array_unserial( $this->extra_cols, 'extra_cols' );
        }
        // make sure restrictions apply to fields belonging to the rating_scheme
        foreach ( $this->restrictions as $restr ) {
            foreach ( array_keys( $restr ) as $key ) {
                if ( is_numeric( $key ) && !in_array( $key, array_keys( $this->fields ) ) ) {
                       throw new EWZ_Exception( "Invalid Restriction Rating_Field $key in restriction " .
                            $restr['msg'] );
                }
            }
        }

        // make sure pg_column is 1:1
        $sfields = $this->fields;
        uasort( $sfields, array('self', 'pgcol_sort' ) );
        $n = 0;
        foreach ( $sfields as $key => $field ) {
            if ( !( isset( $field->pg_column ) && ( $field->pg_column == $n ) ) ) {
                $this->rearrange_columns();
                break;
            }
            $n++;
        }
                
        // make sure ss_column is not the same in two different fields
        $seen2 = array();
        foreach ( $this->fields as $key => $field ) {
            if ( isset( $field->ss_column ) && ( $field->ss_column >= 0 ) ) {
                    if ( array_key_exists( $field->ss_column, $seen2 ) ) {
                    throw new EWZ_Exception( 'Two or more fields have the same spreadsheet column ' .
                            $field->ss_column );
                    } else {
                        $seen2[$field->ss_column] = true;
                    }
            }
        }
        return true;
    }

    /**
     * If some error has produced pg_column values that do not exist or are not consecutive integers,
     * at least set something so the user can see the data, even if the order is not what is expected.
     **/
    protected function rearrange_columns()
    {
        $col = 0;
        foreach( $this->fields as $field ){
            $field->pg_column = $col;
            ++$col;
        }
    }

    /**
     * Sort function for use in sorting the fields for the admin schemes page
     *
     * Sorts by the pg_column
     *
     * @param $a  an Ewz_Rating_Field object
     * @param $b  an Ewz_Rating_Field object
     * @return  int  -1 if $a sorts before $b, 0 if they are equal, 1 otherwise
     **/
    protected static function pgcol_sort( $a, $b ){
        assert( is_a( $a, 'Ewz_Rating_Field' ) );
        assert( is_a( $b, 'Ewz_Rating_Field' ) );
        if( empty($a->pg_column) ){
            return -1;
        }
        if( empty($b->pg_column) ){
            return 1;
        }
        if($a->pg_column == $b->pg_column){
            return 0;
        }
        return ($a->pg_column < $b->pg_column) ? -1 : 1;
    }

    /********************  Section: Database Updates **********************/

    /**
     * Save the rating_scheme to the database
     *
     * Check for permissions, then update or insert the rating_scheme data
     *                             update or insert the fields
     * @param none
     * @return none
     */
    public function save()
    {
        global $wpdb;
        if (  isset( $this->rating_scheme_id ) && $this->rating_scheme_id > 0 ){
            if ( !Ewz_Rating_Permission::can_edit_rating_scheme_obj( $this ) ) {
                throw new EWZ_Exception( 'Insufficient permissions to edit rating scheme.',
                "$this->rating_scheme_id" );
            }
        } else {
            if ( !Ewz_Rating_Permission::can_edit_all_schemes() ) {
                throw new EWZ_Exception( 'Insufficient permissions to create a new rating scheme.' );
            }
        }
            
        $this->check_errors();
        // NB: scheme_order is not saved here
        $data = stripslashes_deep( array(
            'item_layout_id' => $this->item_layout_id,                                 // %d
            'scheme_name' => $this->scheme_name,                                       // %s
            'restrictions' => serialize( stripslashes_deep( $this->restrictions ) ),   // %s
            'extra_cols' => serialize( stripslashes_deep( $this->extra_cols ) ),       // %s
            'settings'  => serialize( $this->settings ),                               // %s
        ) );
        $datatypes = array( '%d', // = item_layout_id
                            '%s', // = scheme_name
                            '%s', // = restrictions 
                            '%s', // = extra_cols 
                            '%s', // = settings 
                          );

        // update or insert the rating_scheme itself
        if ( isset( $this->rating_scheme_id ) && $this->rating_scheme_id > 0 ) {
            // updating -- order should already be set
            $rows = $wpdb->update( EWZ_RATING_SCHEME_TABLE,                          // no esc
                                   $data,        array('rating_scheme_id' => $this->rating_scheme_id), 
                                   $datatypes,   array('%d') 
                                 );
            if ( $rows > 1 ) {
                throw new EWZ_Exception( 'Problem with update of rating_scheme ' . $this->scheme_name . ' too many rows updated' );
            }
        } else {
            // inserting, set the order to be last
            $wpdb->insert( EWZ_RATING_SCHEME_TABLE, $data, $datatypes );           // no esc
            $this->rating_scheme_id = $wpdb->insert_id;

            if ( !$this->rating_scheme_id ) {
                throw new EWZ_Exception( 'Problem with creation of rating_scheme ' . $this->scheme_name,  $wpdb->last_error );
            }
            $nschemes = (int)$wpdb->get_var( "SELECT count(*)  FROM " . EWZ_RATING_SCHEME_TABLE  );   // no tainted data

            $wpdb->query($wpdb->prepare( "UPDATE " . EWZ_RATING_SCHEME_TABLE .       // no esc
                                         "   SET scheme_order = %d " .
                                         " WHERE  rating_scheme_id = %d ", 
                                         $nschemes, $this->rating_scheme_id ) );  
        }

        // save the field data and fix up any restrictions ( need field id to do that )
        $havenewfield = false;
        foreach ( $this->fields as $field ) {
            $field->rating_scheme_id = $this->rating_scheme_id;
            $fid = $field->save();
            if( $fid ){
                $havenewfield = true;
                foreach( $this->restrictions as $n => $restr ){
                    $this->restrictions[$n][$fid] = array( '~*~' );
                }
            }
        }
        $restrs = serialize( stripslashes_deep( $this->restrictions ) ); 
        // save the restrictions again
        if(  $havenewfield && $this->restrictions ){
            $rows = $wpdb->update( EWZ_RATING_SCHEME_TABLE,                        // no esc
                                   array( 'restrictions' => $restrs ),  array( 'rating_scheme_id' => $this->rating_scheme_id ),
                                   array( '%s' ),                       array( '%d' ) );
            if ( $rows != 1 ) {
                throw new EWZ_Exception( "$rows: Problem with update of restrictions for new fields  " . $this->scheme_name );
            }
        }      
        return true;
    }

    /**
     * Delete a specified field from the database
     *
     * @param  int   $rating_field_id   id of the rating_field to be deleted
     * @return none
     */
    public function delete_field( $rating_field_id )
    {
        assert( Ewz_Base::is_pos_int( $rating_field_id ) );
        if ( !Ewz_Rating_Permission::can_edit_rating_scheme_obj( $this ) ) {
            throw new EWZ_Exception( 'Insufficient permissions to edit rating scheme',
            "$this->rating_scheme_id" );
        }

        // make sure this is a valid field for this rating_scheme
        $field = null;
        foreach ( $this->fields as $test_field ) {
            if ( $test_field->rating_field_id == $rating_field_id ) {
                $field = $test_field;
            }
        }
        if ( $field !== null ) {
            foreach( $this->restrictions as $n => $restr ){
                unset( $this->restrictions[$n][$rating_field_id] );
            }
            $this->save();   
            return $field->delete();
        } else {
            throw new EWZ_Exception( 'Failed to find field to delete', $rating_field_id );
        }
    }

    /**
     * Delete the rating_scheme and all its fields from the database
     *
     * @param  int   $delete_forms = self::FAIL_IF_FORMS ( fail if any ratingforms use this rating_scheme )
     *                               or self::DELETE_FORMS (delete all ratingforms using this rating_scheme so long as they have no items)
     * @return none
     */
    public function delete( $delete_forms = self::FAIL_IF_FORMS )
    {
        if ( !Ewz_Rating_Permission::can_edit_rating_scheme_obj( $this ) ) {
            throw new EWZ_Exception( 'Insufficient permissions to edit rating scheme',
            "$this->rating_scheme_id" );
        }
        assert( $delete_forms == self::DELETE_FORMS || $delete_forms == self::FAIL_IF_FORMS );
        global $wpdb;

        $forms = Ewz_Rating_Form::get_rating_forms_for_rating_scheme( $this->rating_scheme_id );
        if( $delete_forms ==  self::DELETE_FORMS ){
            foreach( $forms as $rform ){
                // never delete forms containing item_ratings using this function
                // - force each form to be deleted separately
                $rform->delete( Ewz_Rating_Form::FAIL_IF_RATINGS );
            }
        } else {
            $n = count( $forms );
            if( ( $n > 0 ) ){
                throw new EWZ_Exception( "Attempt to delete rating_scheme with $n associated rating forms." );
            }
        }

        foreach ( $this->fields as $field ) {
            $field->delete();
        }

        // now delete the rating_scheme and renumber the scheme_order for the remaining ones
        $rowsaffected = $wpdb->query( $wpdb->prepare( "DELETE FROM " . EWZ_RATING_SCHEME_TABLE .
                " WHERE rating_scheme_id = %d", $this->rating_scheme_id ) );
        if ( 1 == $rowsaffected ) {
            self::renumber_rating_schemes($this->scheme_order);
        } else {
            throw new EWZ_Exception( "Problem deleting rating_scheme '$this->scheme_name '", $wpdb->last_error );
        }
    }

}

