<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/ewz-exception.php");
require_once( EWZ_PLUGIN_DIR . "classes/ewz-base.php");
require_once( EWZ_PLUGIN_DIR . "classes/ewz-item.php");
require_once( EWZ_RATING_DIR . "classes/ewz-rating-form.php");


/***************************************************************/
/* Interaction with the EWZ_IEM_RATING table.                  */
/* Stores information about one judge's rating of one item     */
/***************************************************************/

class Ewz_Item_Rating extends Ewz_Base
{
    // key
    public $item_rating_id;

    // database
    public $rating_form_id;   
    public $item_id;         // the rated item
    public $judge_id;        // the user_id of the judge
    public $judge;           // serialized data containing whatever we decide may be needed
                             //  -- can't be only judge's user_id because we may want to delete the login 
    public $rating;          // serialized data containing anything filled out by judge in the rating form
    public $ratingdate;      // date of last change

   // other data generated
    public $item;            // Ewz_Item generated from $item_id
    public $custom;

    // keep list of db data names/types as a convenience for iteration and so we can easily add new ones.
    // Dont include item_rating_id here
    public static $varlist = array(
        'rating_form_id' => 'integer',
        'item_id' => 'integer',
        'judge_id' => 'integer',
        'judge' => 'array',
        'rating'  => 'array',
        'ratingdate' => 'string',
    );


    /********************  Section: Static Functions **************************/
      
    /**
     * Return the ratings count for the judge
     * 
     * @param    $judge_id
     * @param    $rating_form_id
     * @return   integer
     **/
    public static function get_judge_count( $judge_id, $rating_form_id )
    {
        global $wpdb;
        assert(Ewz_Base::is_pos_int( $judge_id ) );
        assert(Ewz_Base::is_pos_int( $rating_form_id ) );

        $stat = $wpdb->get_var( $wpdb->prepare( "SELECT count(*) FROM " . EWZ_ITEM_RATING_TABLE .
                                                " WHERE rating_form_id = %d " .
                                                "   AND judge_id = %d", $rating_form_id, $judge_id ) );
        return (int)$stat;
     }

    /**
     * Return the ratings count for a scheme
     * 
     * @param    $rating_scheme_id
     * @return   integer
     **/
    public static function get_scheme_count( $rating_scheme_id )
    {
        assert(Ewz_Base::is_pos_int( $rating_scheme_id ) );
        global $wpdb;
        return (int)$wpdb->get_var( $wpdb->prepare( "SELECT count(*)  FROM " .
                                               EWZ_ITEM_RATING_TABLE . " itm, " .  EWZ_RATING_FORM_TABLE . " frm " .
                                               " WHERE frm.rating_scheme_id = %d AND frm.rating_form_id = itm.rating_form_id",
                                               $rating_scheme_id ) );
    }

    /**
     * Return a list of all rating_ids for the form
     * 
     * @param    $rating_form_id
     * @return   integer
     **/
    public static function get_rating_ids_for_form( $rating_form_id ){
        assert( Ewz_Base::is_nn_int( $rating_form_id ) );
        global $wpdb;
        return $wpdb->get_col( $wpdb->prepare( 'SELECT item_rating_id FROM ' . EWZ_ITEM_RATING_TABLE . 
                                               ' WHERE rating_form_id = %d ' .
                                               ' ORDER BY item_rating_id ',
                                               $rating_form_id ) );  
    }


    /**
     * Return a list of all rating forms and ratings using the input rating_scheme_id 
     * Used for checking if a rating exists with data for a given rating_field, before deleting the rating field
     *
     * @param    $rating_scheme_id 
     * @return   array of strings ( serialized data )
     **/
    public static function get_ratings_for_scheme( $rating_scheme_id ){
        assert( Ewz_Base::is_nn_int( $rating_scheme_id ) );
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( "SELECT i.rating, frm.rating_form_title " .
                                                   "  FROM " . EWZ_RATING_FORM_TABLE . " frm, " . EWZ_ITEM_RATING_TABLE . " i " .
                                                   " WHERE  frm.rating_scheme_id = %d " . 
                                                   "   AND i.rating_form_id = frm.rating_form_id" , $rating_scheme_id  ) ); // for check
    }

    /**
     * Get the id's of judges with saved ratings for the rating_form
     *
     * @param  int    $rating_form_id  id of the rating form
     * @return array of judge_ids
     **/
    public static function get_judges_with_ratings( $rating_form_id ){
        assert( Ewz_Base::is_nn_int( $rating_form_id ) );
        global $wpdb;
        $result = $wpdb->get_col(  $wpdb->prepare( "SELECT DISTINCT judge_id " . " FROM " .  EWZ_ITEM_RATING_TABLE . 
                                                 " WHERE rating_form_id = %d", $rating_form_id ) );  // is ordered
        return array_map( 'intval', $result );
    }

    /**
     * Called before an item is deleted in EntryWizard -- delete any ratings for it
     * 
     * @param int $del_item_id   the id of the item about to be deleted
     **/
    public static function drop_item_ratings( $del_item_id ){
        global $wpdb;
        assert( Ewz_Base::is_nn_int( $del_item_id ) );

        $ratings = $wpdb->get_col( $wpdb->prepare('SELECT item_rating_id FROM ' . EWZ_ITEM_RATING_TABLE .
                                                  ' WHERE item_id = %d',  $del_item_id  ) );   // for deletion
        $errmsg = '';
        foreach ( $ratings as $item_rating_id ) {
            try {
                $item_rating = new Ewz_Item_Rating( (int)$item_rating_id );
                $item_rating->delete();
            } catch( EWZ_Exception $e ) {
                $msg = $e->getMessage();
                if( strpos( $msg, 'permission' ) ){
                    $errmsg .= "\nAttempt to delete an item with existing ratings.\nPlease contact your administrator.";
                } else {
                    $errmsg .=  $msg;
                } 
            }
        }
        if( $errmsg ){
            throw new EWZ_Exception( "Error deleting rating: $errmsg" );
        }      
      }
  
    /**
     * Delete all the ratings created by the judge using the rating form
     *
     * @param int  $rating_form_id  id of the rating form
     * @param int  $judge_id  id of the judge
     **/
    public static function delete_ratings_for_judge( $rating_form_id, $judge_id ){
          assert( Ewz_Base::is_nn_int( $rating_form_id ) );
          assert( Ewz_Base::is_nn_int( $judge_id ) );

          global $wpdb;
          if( Ewz_Rating_Permission::can_edit_rating_form( $rating_form_id  ) ){
              $list = $wpdb->get_results( $wpdb->prepare(
                  "SELECT item_rating_id  FROM " . EWZ_ITEM_RATING_TABLE .         
                  " WHERE  rating_form_id = %d AND judge_id = %d " , 
                  $rating_form_id, $judge_id ), OBJECT );                // for deletion
              foreach ( $list as $itm ) {
                  $newItemRating = new Ewz_Item_Rating( $itm->item_rating_id );
                  $newItemRating->delete();
              }
              wp_cache_flush();
          } else {
               throw new EWZ_Exception( 'Insufficient permissions to delete ratings', $this->rating_form_id );
          }
      }


    /********************  Section: Selecting Ratings **************************/
    /**
     * Generate the sql for selecting all the items and their ratings for the rating form.
     *
     * Most general form of final sql is: 
     *    SELECT DISTINCT i.item_id, ir.item_rating_id, ir.judge, ir.rating  
     *      FROM  ewz_item_table i  
     *      LEFT OUTER JOIN ewz_item_rating_table  ir   
     *                   ON ( ir.item_id = i.item_id AND ir.rating_form_id = rating-form-id  
     *                        AND  ir.judge_id = current-user-id )          
     *      WHERE i.webform_id IN ( list-of-webform-ids) 
     *        AND i.user_id = current-user-id 
     *      ORDER BY concat( i.item_id mod 10, i.item_id div 10 ); 
     *
     * @param  boolean  $all   if true ( as when run by admin ) , get all the ratings, otherwise 
     *                         only those created by the current user
     * @return string   sql for selecting items and their ratings
     **/
    public static function get_items_sql( $usr, $rating_form, $all ){
        assert( Ewz_Base::is_nn_int( $usr ) );
        assert( is_a( $rating_form, 'Ewz_Rating_Form' ) );
        assert( is_bool( $all ) );
        $only_owner = '';

        // cant use wpdb->prepare on this sql, so do our best to check inputs
        $wids = isset( $rating_form->item_selection['webform_ids'] ) ? $rating_form->item_selection['webform_ids'] : array();
        $itms = isset(  $rating_form->item_selection['itemlist'] ) ? $rating_form->item_selection['itemlist'] : '';
        if( !( is_numeric( $usr ) && is_bool( $all ) && is_int($rating_form->rating_form_id ) && is_array($wids) && preg_match('/^[0-9,]*$/', $itms  )  ) ){
            throw new EWZ_Exception( 'Invalid input for get_items_sql');
        }
        foreach( $wids as $wid ){
            if( !is_numeric( $wid ) ){
                throw new EWZ_Exception( 'Invalid webform id in get_items_sql');
            }
        }

        if( !$all && !empty( $rating_form->item_selection['own'] ) ){
            
            $only_owner = "AND i.user_id = $usr ";
        } 

        $judge_is_user = '';
        if( !$all ){
            $judge_is_user = "   AND   ir.judge_id = $usr ";
        }

        $orderby = 'i.item_id';
        if( $rating_form->shuffle ){
            $orderby = 'concat( i.item_id mod 10, i.item_id div 10 )';
        }
        $orderby .= ", ir.judge_id";

        // Get all items and their ratings for the webforms in item_selection['webform_ids'], 
        // restricted by owner if required
        $sql =  "SELECT DISTINCT i.item_id, ir.item_rating_id, ir.judge,ir.judge_id, ir.rating " .
                "  FROM " . EWZ_ITEM_TABLE . " i " .
                "  LEFT OUTER JOIN " . EWZ_ITEM_RATING_TABLE . " ir " . 
                "               ON ( ir.item_id = i.item_id AND ir.rating_form_id = $rating_form->rating_form_id $judge_is_user )";  // is ordered
        $sql .= " WHERE i.webform_id IN (" . join( ',', $rating_form->item_selection['webform_ids'] ) . ") ";
        $sql .=   $only_owner;

        if( isset( $rating_form->item_selection['itemlist'] ) && $rating_form->item_selection['itemlist'] ){
            $sql .= ' AND i.item_id IN ( ' . $rating_form->item_selection['itemlist']  .') ';
        }
        
        $sql .= " ORDER BY " . $orderby;
        return $sql;
     } 

    /**
     * Return all the item_ratings for the rating form, including blank ones for unrated items.
     * Returns one row per *item*, for use in the "by item" spreadsheet style and the 'read' view.
     * 
     * @return  an array of arrays of item_ratings, indexed on item_id.
     */
    public static function get_ratings_by_item( $rating_form ){
        assert( is_a( $rating_form, 'Ewz_Rating_Form' ) );
        global $wpdb;
        $wpdb->query( "SET SESSION group_concat_max_len = 65536" );   // 2^16   no tainted data


        $orderby = 'item_id';
        if( $rating_form->shuffle ){
            $orderby = 'concat( i.item_id mod 10, i.item_id div 10 )';
        }

        $only_owner =  !empty( $rating_form->item_selection['own'] ) ?  "AND i.user_id = $usr " : '';
       
        $sql =    "SELECT i.item_id, ";
        $sql .=    "GROUP_CONCAT( ir.item_rating_id,'|', ir.judge_id,'|', ir.rating ORDER BY ir.judge_id SEPARATOR '~') as judge_rating " . 
                   " FROM " . EWZ_ITEM_TABLE . " i  LEFT OUTER JOIN " . EWZ_ITEM_RATING_TABLE . " ir " . 
                             " ON ( ir.item_id = i.item_id AND ir.rating_form_id =  $rating_form->rating_form_id $only_owner ) ";  // is ordered
        if( count( $rating_form->item_selection['webform_ids'] ) > 0 ){
         $sql .=  " WHERE i.webform_id IN (" . join( ',', $rating_form->item_selection['webform_ids'] ) . ") ";
        }

        if( isset( $rating_form->item_selection['itemlist'] ) && $rating_form->item_selection['itemlist'] ){
            $sql .= ' AND i.item_id IN ( ' . $rating_form->item_selection['itemlist']  .') ';
        }

        $sql .=   " GROUP BY i.item_id";
        $sql .=   " ORDER BY  $orderby";   
        $data = $wpdb->get_results( $sql );  // is ordered  no tainted data

        $ratings_for_item = array();
        foreach( $data as $d ){
            $d->item_id = (int)$d->item_id;
            $ratings = explode('~', $d->judge_rating);
            foreach( $ratings as $rating_string ){               
                $r = explode('|', $rating_string );
                // If the rating exists
                $rating = null;
                if( $r[0]  ){
                    $rating =  new Ewz_Item_Rating( $r[0] );
                    $rating->complete = true;
                } else {
                    // create an item_rating for the item if there wasnt one already
                    // ( will only get saved if something is entered )
                    $rating = new  Ewz_Item_Rating( array(  'item_id' => $d->item_id,                                                  
                                                            'rating_form_id' => $rating_form->rating_form_id
                                                  ) );
                    $rating->complete = false;
                }
                // Weed out those not meeting the other selection criteria
                if( $rating_form->check_criteria( $rating ) ){
                    if(!isset($ratings_for_item[$d->item_id] ) ){
                       $ratings_for_item[$d->item_id] = array();
                    }
                    array_push( $ratings_for_item[$d->item_id], $rating );
                }
            }
        }
        return $ratings_for_item;
    }

    /********************  Section: Construction **************************/

    /**
     * Assign the object variables from an array
     *
     * Calls parent::base_set_data with the list of variables and the data structure
     *
     * @param  array  $data input data
     * @return none
     **/
    public function set_data( $data )
    {
        assert( is_array( $data ) );
        // first arg is list of valid elements - all of varlist plus item_rating_id
        parent::base_set_data( array_merge( self::$varlist,
                                            array('item_rating_id' => 'integer') ), $data );
        $this->judge_id = $this->judge['user_id'];

        $this->item = new Ewz_Item( $this->item_id );  
        $this->custom = new Ewz_Custom_Data( $this->item->user_id );
    }

    /**
     * Constructor
     *
     * @param  mixed    $init    item_rating_id or array of data
     * @return none
     **/
    public function __construct( $init )
    {
        assert( Ewz_Base::is_pos_int( $init ) || is_array( $init ) );
        if ( Ewz_Base::is_pos_int( $init ) ) {
            $this->create_from_id( $init );
        }  else {
            $this->create_from_data( $init );
        }
    }
    /**
     * Create a new item_rating from the item_rating_id by getting the data from the database
     *
     * @param  int  $id the item_rating id
     * @return none
     **/
    protected function create_from_id( $id )
    {
        global $wpdb;
        assert( Ewz_Base::is_pos_int( $id ) );

        if( !$dbitem_rating = wp_cache_get( $id, 'ewz_item_rating' ) ){
            $varstring = implode( ',', array_keys( self::$varlist ) );
 
            $dbitem_rating = $wpdb->get_row( $wpdb->prepare(  "SELECT item_rating_id, $varstring FROM " . EWZ_ITEM_RATING_TABLE .
                                                              " WHERE item_rating_id=%d", $id ), ARRAY_A );
            if ( !$dbitem_rating ) {
                throw new EWZ_Exception( 'Unable to find matching item_rating', $id );
            }
            wp_cache_set( $id, $dbitem_rating, 'ewz_item_rating');
        }
        $this->set_data( $dbitem_rating );
    }

    /**
     * Create a item_rating object from $data
     *
     * @param  array  $data
     * @return none
     **/
    protected function create_from_data( $data )
    {
        assert( is_array( $data ) );
        global $current_user;
        wp_get_current_user();
        $data['judge'] = array( 'user_id' => $current_user->ID,
                                'name' => $current_user->user_firstname .' '. $current_user->user_lastname,
                                'email' =>  $current_user->user_email 
                              );
        if ( !array_key_exists( 'item_rating_id', $data ) || !$data['item_rating_id'] ) {
            $data['item_rating_id'] = 0;
            $this->set_data( $data );
        } else {
            // in case this is coming from a shortcode with some columns read-only, don't overwrite any
            // columns we don't already have.
            $this->create_from_id( $data['item_rating_id'] );
            $this->rating = array_replace( $this->rating, $data['rating'] );
        }
        $this->check_errors();
    }


    /********************  Validation  *******************************/

    /**
     * Check for various error conditions, and raise an exception when one is found
     *
     * @param  none
     * @return none
     **/
    protected function check_errors()
    {
        if ( is_string( $this->rating ) ) {
            $this->rating = unserialize( $this->rating );
            if( !is_array( $this->rating ) ){
                $this->rating = array();
                error_log("EWZ: failed to unserialize rating for item_rating $this->item_rating_id") ; 
            }            
        }
        if ( is_string( $this->judge ) ) {
            $this->judge = unserialize( $this->judge );
            if( !is_array( $this->judge ) ){
                $this->judge = array();
                error_log("EWZ: failed to unserialize judge for item_rating $this->item_rating_id") ; 
            }            
        }
        foreach ( self::$varlist as $key => $type ) {
            settype( $this->$key, $type );
        }
    }

    /********************  Section: Object Functions **************************/

    /**
     * Read-only display of the value of the input field for this rating
     * 
     * @param    Ewz_Rating_Form    $rating_form the rating form in which to display it
     * @param    Ewz_Rating_Field   $rating_field the field whose value is to be displayed
     * @param    Ewz_Item           $item   the item being rated
     * @param    Ewz_Item_Rating    $rating the rating_item
     * @return   array (  value string, display string )   
     **/
    public static function rating_field_display( $rating_form, $rating_field, $item, $rating ) {
        assert( is_a( $rating_form,  'Ewz_Rating_Form' ) );
        assert( is_a( $rating_field, 'Ewz_Rating_Field' ) );
        assert( is_a( $rating, 'Ewz_Item_Rating') );
        assert( is_a( $item, 'Ewz_Item') );
        $value ='';
        $usr_display ='';
        // NB: the variables 
        //       $wform, $user, $item, $custom  
        // must have exactly those names, since they are the values of $display[$xtr]['dobject']
        // *** IDE's may think they are unused, but that is not the case
        $wform = $rating_form->webforms[$item->webform_id];
        $user = get_userdata( $item->user_id );
        $custom = $rating->custom;

        $display = Ewz_Rating_Scheme::get_all_display_data();  
        // get_all_display_data returns an array of form 
        //     (  'att' => array(  'header' => 'Attached To',   'dobject' => 'item', 'origin' => 'EWZ Item',    'value' => 'item_data>attachedto' ),
        //       ....
        //        'wfm' => array(  'header' => 'Webform Ident', 'dobject' => 'wform','origin' => 'EWZ Webform', 'value' => 'webform_ident' ),
        //       ...
        //        'mid' => array(  'header' => 'WP User ID',   'dobject' => 'user', 'origin' => 'WP User',   'value' => 'ID' ),
        //       ...
        //      );
        switch ( $rating_field->field_type ) {
        case 'fix':
            // return the uploaded data in text form
            $field_id = $rating_field->fdata['field_id'];
            if( isset( $item->item_files[$field_id]['thumb_url'] ) ){ 
                // for an image, return the thumb url
                $usr_display = $item->item_files[$field_id]['thumb_url'];
                $value = $usr_display;
            } else {
                // otherwise return a text value 
                if( isset( $item->item_data[$field_id]['value'] ) ){
                    $usr_display = ewz_display_item( $rating_field->field, $item->item_data[$field_id]['value'] );
                    $value = $item->item_data[$field_id]['value'];
                } else {
                    $usr_display = '';
                    $value = '';
                }
            }
            break;
        case 'xtr':
            // return the "extra data" item
            $xtr = $rating_field->fdata['dkey'];

            // $$display[$xtr]['dobject'] is either $wform or $user or $item or $custom
            $usr_display =  (string)Ewz_Rating_Scheme::get_extra_data_item( $$display[$xtr]['dobject'], $display[$xtr]['value'] );
            $value = $usr_display;
            break;
        case 'lab':
            // return the label
            $usr_display =  $rating_field->fdata['label'];
            $value = $usr_display;
            break;
        case 'str':
            // return the value input by the judge, if it exists
            if( isset( $rating->rating[$rating_field->rating_field_id] ) ){
                $usr_display = $rating->rating[$rating_field->rating_field_id];
                $value = $usr_display;
            } else {
                $usr_display = '';
                $value = '';
            }
            break;
        case 'chk':
            // return the value input by the judge, if it exists
            if( isset( $rating->rating[$rating_field->rating_field_id] ) ){
                $value = $rating->rating[$rating_field->rating_field_id];
                $usr_display = $value ? $rating_field->fdata['chklabel'] : ' - ';
            } else {
                $usr_display = ' - ';
                $value = '';
            }
            break;
        case 'opt':
            // return the value input by the judge, if it exists
            if( isset( $rating->rating[$rating_field->rating_field_id] ) ){
                $value = $rating->rating[$rating_field->rating_field_id];
                foreach ( $rating_field->fdata['options'] as $dat ) {
                    if( $value == $dat['value'] ){
                        $usr_display = $dat['label'];
                    }
                }
            } else {
                $usr_display = '';
                $value = '';
            }
            break;
        default:
        }
        // quotes necessary to have empty strings seen as strings and not null
        return array( "$value", "$usr_display" );
    }


    /********************  Section: Database Updates ******************/

    /**
     * Save the item_rating to the database
     *
     * Check for permissions, then update or insert the data
     * Return the item_rating id if item_rating is new -- needed for adding item_rating to restrictions
     *
     * @param none
     * @return item_rating id if this is a new item_rating, otherwise 0  
     */
    public function save()
    {
        global $wpdb;

        if ( !( $this->judge['user_id'] == get_current_user_id()   // judge can edit own data 
                ||
                Ewz_Rating_Permission::can_edit_rating_form( $this->rating_form_id ) )   // admin can edit rating form
        ) {
            throw new EWZ_Exception( 'Insufficient permissions to edit a rating',
                    "item $this->item_id ( in webform $this->item->webform_id" );
        }
        $this->check_errors();

        wp_cache_delete( $this->item_rating_id, 'ewz_item_rating');

        $returnval = '';
        // save ratingdate in current timezone option, then set timezone back to what it was
        $curr_tz = date_default_timezone_get();
        $tz_opt = get_option('timezone_string'); 
 
        //**NB:  for safety, stripslashes *before* serialize as well, otherwise character counts may be wrong
        //       ( currently should  not be needed in this case because of the item_rating data restrictions )

        $data = stripslashes_deep( array(
                                         'rating_form_id' => $this->rating_form_id,        // %d
                                         'item_id' => $this->item_id,            // %d
                                         'judge_id' => $this->judge_id,            // %d
                                         'judge' => serialize( stripslashes_deep( $this->judge ) ),   // %s
                                         'rating' => serialize( stripslashes_deep( $this->rating ) ),   // %s
                ) );
        $datatypes = array( '%d',  // = rating_form_id
                            '%d',  // = item_id
                            '%d',  // = judge_id
                            '%s',  // = judge
                            '%s'   // = rating
                           );
        if ( $this->item_rating_id > 0 ) {
            // update an existing rating
            $rows = $wpdb->update( EWZ_ITEM_RATING_TABLE,                                            // no esc
                                   $data,        array( 'item_rating_id' => $this->item_rating_id ), 
                                   $datatypes,   array( '%d' ) );
            if(false ===  $rows){
                 throw new EWZ_Exception( 'ERROR updating item_rating', $this->item_rating_id . ' ' . $wpdb->last_error  );
            }
            if ( $rows > 1 ) {
                throw new EWZ_Exception( 'Failed to update item_rating', $this->item_rating_id );
            }
            if( $rows == 1 ){
                
                $rows = $wpdb->update( EWZ_ITEM_RATING_TABLE,                                     // no esc
                                   array( 'ratingdate' => current_time( 'mysql' ) ),  array( 'item_rating_id' => $this->item_rating_id ),
                                   array( '%s' ),                                     array( '%d' ) );
            }
            $returnval =  0;
        } else {
            // create a new one.  Here we need to update the rating-form counts
            $data['ratingdate'] = current_time( 'mysql' );
            array_push( $datatypes, '%s' );
  
            $wpdb->insert( EWZ_ITEM_RATING_TABLE, $data, $datatypes );                             // no esc   
            $this->item_rating_id = $wpdb->insert_id;
            if ( !$this->item_rating_id ) {
                throw new EWZ_Exception( 'Failed to create new item_rating', $this->item_rating_id . ' '. $wpdb->last_error );
            }
            $returnval =  $this->item_rating_id;
        }

        if( $tz_opt ){
            date_default_timezone_set( $curr_tz );
        }
        return $returnval;
    }

    /**
     * Delete the item_rating  from the database
     *
     * @param  none
     * @return none
     */
    public function delete()
    {
        global $wpdb;
        if ( !( $this->judge['user_id'] == get_current_user_id()   // judge can edit own data 
                ||
                Ewz_Rating_Permission::can_edit_rating_form( $this->rating_form_id ) )   // admin can edit rating form
        ) {
            throw new EWZ_Exception( 'Insufficient permissions to delete rating',
            "item " . $this->item_id . " ( in webform " . $this->item->webform_id . " ) " );
        }
        
        wp_cache_delete( $this->item_rating_id, 'ewz_item_rating');
        $rowsaffected = $wpdb->query( $wpdb->prepare( "DELETE FROM " . EWZ_ITEM_RATING_TABLE . " WHERE item_rating_id = %d",
                                               $this->item_rating_id ) );
        if ( $rowsaffected != 1 ) {
            throw new EWZ_Exception( 'Failed to delete item_rating', $this->item_rating_id );
        }
    }
}