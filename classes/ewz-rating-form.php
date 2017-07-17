<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/ewz-exception.php" );
require_once( EWZ_PLUGIN_DIR . "classes/ewz-base.php" );
require_once( EWZ_PLUGIN_DIR . "classes/ewz-field.php" );
require_once( EWZ_PLUGIN_DIR . "classes/ewz-item.php" );
require_once( EWZ_PLUGIN_DIR . 'includes/ewz-common.php' );
require_once( EWZ_RATING_DIR . "classes/ewz-rating-permission.php" );
require_once( EWZ_CUSTOM_DIR . "ewz-custom-data.php" );

/********************************************************************************************
 * Interaction with the EWZ_RATING_FORM table.
 *
 * Represents the form displayed to judges
 ********************************************************************************************/

class Ewz_Rating_Form extends Ewz_Base
{

    const DELETE_RATINGS = 1;
    const FAIL_IF_RATINGS = 0;

    // key
    public $rating_form_id;

    // data stored on db
    public $rating_scheme_id;      // key for the Ewz_Rating_Scheme that determines the columns and restrictions
    public $rating_form_title;     // title for display at the top of the form
    public $rating_form_ident;     // alpha_numeric code for use as shortcode parameter ( no spaces allowed )
    public $rating_form_order;     // to specify display order in the set of all rating forms
    public $item_selection;        // serialized data storing the parameters used to select the items
    public $shuffle;               // True if items are to be rearranged 

    public $judges;                // array of user_ids of the judges allowed access. 

    public $rating_open;           // True if the form is open to the judges
    public $rating_status;         // array of serialized data containing number completed, number in progress, etc
                                   // keys are  'own' ( bool ), 'count' and judge_ids

    // non-key data stored on db
    // type is used in set_data to cast or unserialize if required
    public static $varlist = array(
        'rating_scheme_id'  => 'integer',
        'rating_form_title' => 'string',
        'rating_form_ident' => 'string',
        'rating_form_order' => 'integer',
        'item_selection'    => 'array',
        'shuffle'           => 'boolean',
        'judges'            => 'array',
        'rating_open'       => 'boolean',
        'rating_status'     => 'array',
    );

    // other data generated
    public $rating_scheme;         // Ewz_Rating_Scheme object generated from $rating_scheme_id
    public $webforms;              // array of Ewz_Webforms whose items may be used 
    public $can_download;          // Current user has permission to download the spreadsheet from this rating form
    public $can_edit_rating_form;  // Current user has permission to edit this rating form
    public $actual_judges;         // All judges with at least one rating on the form ( including any subsequently denied access )
    public $disabled_judges;       // Judges denied access, with at least one rating on the form 
    public $show_judge;            // If divide is set for a field, $show_judge[item_id][rating_field_id] is the judge_id 
                                   //    of the judge who sees the field for the item.


   /********************  Section: Static Functions **************************/
                
    /**
     * for use with Permissions
     *
     * @return an array of all rating_form_id, item_layout_id pairs
     */
    public static function get_all_form_layout_ids( )
    {
        global $wpdb;
        $list = $wpdb->get_results( "SELECT f.rating_form_id, s.item_layout_id FROM " . EWZ_RATING_FORM_TABLE .   // no tainted data
                                              " f, " .  EWZ_RATING_SCHEME_TABLE . " s " .
                                    " WHERE s.rating_scheme_id = f.rating_scheme_id ",  OBJECT );  // for permissions
        return $list;
    }

   /**
     * Return the count of all rating forms using the input rating scheme
     *
     * @param   int  $rating_scheme_id   id of the rating scheme
     * @return  int
     */
    public static function get_count_for_scheme( $rating_scheme_id ){
        assert( Ewz_Base::is_pos_int( $rating_scheme_id ) );
        global $wpdb;
        return (int)$wpdb->get_var( $wpdb->prepare( "SELECT count(*)  FROM " .EWZ_RATING_FORM_TABLE .
                                                    " WHERE rating_scheme_id = %d", $rating_scheme_id ) );
    }

       
   /**
     * Return an array of all rating forms using the input rating scheme
     *
     * @param   int  $rating_scheme_id   id of the rating scheme
     * @return  array of all Ewz_Webforms using $rating_scheme_id 
     */
    public static function get_rating_forms_for_rating_scheme( $rating_scheme_id  ) {
        assert( Ewz_Base::is_pos_int( $rating_scheme_id ) );
        global $wpdb;

        $list = $wpdb->get_col( $wpdb->prepare( "SELECT rating_form_id  FROM " . EWZ_RATING_FORM_TABLE .
                                                " WHERE rating_scheme_id = %d " .
                                                " ORDER BY rating_form_order", $rating_scheme_id ) );
        $ratingforms = array( );
        foreach ( $list as $rating_form_id ) {
            $ratingform = new Ewz_Rating_Form( (int)$rating_form_id );
            array_push( $ratingforms, $ratingform );
        }
        return $ratingforms;
    }

   /**
     * Return an array of all defined rating forms satisfying the filter function 
     *
     * @param   callback    $filter  Filter function that must return true for the rating_form_id ( permissions )
     * @return  array of all defined rating_forms visible to current user
     */
    public static function get_all_rating_forms( $filter = 'truefunc' )
    {
        global $wpdb;
        assert(is_string( $filter ) );

        $list = $wpdb->get_col( "SELECT rating_form_id  FROM " . EWZ_RATING_FORM_TABLE . " ORDER BY rating_form_order" );  // no tainted data
        $rating_forms = array();
        foreach ( $list as $rating_form_id ) {
            $rating_form = new Ewz_Rating_Form( (int)$rating_form_id );
            if ( call_user_func( array( 'Ewz_Rating_Permission',  $filter ), $rating_form ) ) {
                array_push( $rating_forms, $rating_form );
            }
        }
        return $rating_forms;
    }

   /**
     * Return an array of all rating form idents
     *
     * @param   none
     * @return  array of all rating form identifiers
     */
    public static function get_all_idents() {
        global $wpdb;
        $list = $wpdb->get_col( "SELECT rating_form_ident  FROM " . EWZ_RATING_FORM_TABLE . ' ORDER BY rating_form_order' );  // no tainted data
        return $list;
    }

    /** Save the order of the rating_forms
      *
      * @param   f_orders  array of ( $rating_form_id => $order ) pairs.
      * @return  number of rows updated
      */
     public static function save_rating_form_order( $f_orders ) {
         global $wpdb;
         assert( is_array( $f_orders['rforder'] ) );
         $n = 0;
         foreach( $f_orders['rforder'] as $rating_form_id => $order ){
             $n = $n + $wpdb->query($wpdb->prepare("UPDATE " . EWZ_RATING_FORM_TABLE . " wf " .
                                                   "   SET rating_form_order = %d WHERE rating_form_id = %d ", $order, $rating_form_id ));  
         }
         return $n;
     }

     /**
     * Renumber the subsequent rating forms when one is deleted
     * @param  integer $order  order of deleted rating form
     */
    private static function renumber_rating_forms( $order ) {
        assert( Ewz_Base::is_nn_int( $order ) );
        global $wpdb;
        $wpdb->query($wpdb->prepare( "UPDATE " . EWZ_RATING_FORM_TABLE . " wf " .
                                     "   SET rating_form_order = rating_form_order - 1 WHERE  rating_form_order > %d " , $order ) );  
    }

    /**
     * Action to be taken when an Ewz_Field is about to be deleted in EntryWizard
     * 
     * Remove it from the item_selection's of any rating forms
     *
     * @param   int    $del_field_id    field_id of the Ewz_Field about to be deleted
     * @return none
     **/
    public static function drop_field( $del_field_id ){
        assert( Ewz_Base::is_nn_int( $del_field_id ) );
        global $wpdb;
        $list = $wpdb->get_col( $wpdb->prepare('SELECT rating_form_id ' . 
                                               '  FROM ' .  EWZ_RATING_FORM_TABLE .
                                               ' WHERE item_selection LIKE "%%fopts%%i:%d%%"',  $del_field_id  ) ); // for update

        foreach ( $list as $rating_form_id ) {
            $rform = new Ewz_Rating_Form( (int)$rating_form_id );

            foreach( $rform->item_selection['fopts'] as $key => $id ){
                if( $key == $del_field_id ){
                    unset( $rform->item_selection['fopts'][$key]);
                }
            }
            $rform->save();
        }
    }

    /**
     * Called when a user has been deleted
     * 
     * Remove the deleted user from all judge lists
     * 
     * @param   user_id   the user id
     * @return none
     */
    public static function delete_user_as_judge( $user_id ){
        global $wpdb;
        assert( Ewz_Base::is_nn_int($user_id ) );
        $sql = 'SELECT rating_form_id, judges ' . 
               '  FROM ' .  EWZ_RATING_FORM_TABLE .
               ' ORDER BY rating_form_id ';
        $sql = $wpdb->prepare( 'SELECT rating_form_id, rating_form_title, judges, rating_open, rating_status ' . 
        '  FROM ' .  EWZ_RATING_FORM_TABLE .
        ' WHERE judges like "%%%s%%" ' .
        ' ORDER BY rating_form_id ', $user_id );

        $data = $wpdb->get_results( $sql, OBJECT ); // is ordered   no tainted data
        if( $data ){
            foreach ( $data as $rform_data ){
                $rating_form = new Ewz_Rating_Form( (int)$rform_data->rating_form_id );

                // delete this judge's ratings 
                $rating_form->del_judge_ratings( $user_id );

                // delete user_id as a value in the judges array
                if(($key = array_search( $user_id,  $rating_form->judges )) !== false) {
                    unset($rating_form->judges[$key]);
                }
                $rating_form->judges = array_values($rating_form->judges);
                // delete user_id as a key in rating_status
                unset( $rating_form->rating_status[$user_id] );
                $rating_form->save();
            } 
        }       
    }

    /**
     * Action to be taken when users are about to be deleted ( before the confirmation )
     * 
     * Warn of any possible consequences.  
     *
     * @param   int          $admin_user  id of current user
     * @param   int array    $userids     ids of users about to be deleted
     * @return none
     **/
    public static function warn_deleting_judge( $admin_user, $userids ){
        global $wpdb;
        assert( is_a( $admin_user, 'WP_User' ) );
        assert( is_array( $userids ) );
        foreach( $userids as $user_id ){ 
            $sql = $wpdb->prepare( 'SELECT rating_form_id, rating_form_title, judges, rating_open, rating_status ' . 
                   '  FROM ' .  EWZ_RATING_FORM_TABLE .
                   ' WHERE judges like "%%%s%%" ' .
                   ' ORDER BY rating_form_id ', $user_id );

            $data = $wpdb->get_results( $sql, OBJECT ); // is ordered   no tainted data
            $msg = '';
            if( $data ){
                foreach ( $data as $rform_data ){
                    $rating_form = new Ewz_Rating_Form( (int)$rform_data->rating_form_id );
                    foreach( $rating_form->judges as $judge_id_str ){
                        $judge_id = (int)$judge_id_str; 
                        if( in_array( $judge_id,  $userids ) ){
                            $judge = get_userdata( $judge_id );
                            if( $msg ){
                                $msg .= "\n<br> ";
                            } else {
                                $msg .= "<b>EntryWizard WARNING:</b>\n<br>";
                            }
                            $msg .= "&nbsp; &nbsp; <u>" . $judge->display_name . " is a judge for " . $rform_data->rating_form_title . ".</u> ";
                            if( isset( $rating_form->rating_status[$judge_id] ) &&
                            ( $rating_form->rating_status[$judge_id] > 0 ) || strpos( $rating_form->rating_status[$judge_id], 'Complete' ) !== false ){
                                $msg .= " &nbsp; &nbsp; There are ratings for this judge, which will be deleted.\n<br> ";
                            }
                            if( $rating_form->rating_scheme->has_divide() ){
                                $msg .= " &nbsp; &nbsp; This form's scheme has a field that is divided between judges.\n<br> ";
                                $msg .= " &nbsp; &nbsp; Removing a judge will completely change the allocation of the field.\n<br>";
                            }
                        }
                    }
                }
            }
        }
            if( $msg ){
                echo $msg;
            }
    }


    /**
     * Called before a webform is deleted in EntryWizard.  
     * Raise an exception if a rating form is set to rate items from the webform.
     *
     * @param int $del_webform_id  id of the webform about to be deleted
     */
    public static function drop_webform( $del_webform_id ){
        global $wpdb;
        assert( Ewz_Base::is_nn_int( $del_webform_id ) );

        $list = $wpdb->get_results( $wpdb->prepare('SELECT rating_form_id, rating_form_title, item_selection  FROM ' . EWZ_RATING_FORM_TABLE . 
                                                   ' WHERE item_selection LIKE "%%%d%%"', $del_webform_id   ) );  // for check

        foreach ( $list as $rf ) {
            $selection = self::array_unserial( $rf->item_selection, "item_selection" );
            foreach( $selection['webform_ids'] as $key => $id ){
                if( (int)$id == $del_webform_id ){
                    throw new EWZ_Exception( 'Webform items are selected in the rating form  "' . $rf->rating_form_title . '". Please remove the webform from the item-selection list first.' );
                }
            }
        }   
    }

   /********************  Section: Construction **************************/

    /**
     * Assign the object variables from an array
     *
     * Calls parent::base_set_data with the list of variables and the data structure
     *
     * @param  array  $data input data.
     * @return none
     */
    public function set_data( $data ) {
        assert( is_array( $data ) );
        parent::base_set_data( array_merge( self::$varlist, array( 'rating_form_id' => 'integer' ) ), $data );
        $this->judges = array_map( 'intval', $this->judges );    // some earlier code saved judge_id's as strings

        // remove -1 ( no selection -- 0 is used for All ) from judge list 
        if(($key = array_search( -1, $this->judges)) !== false) {
            unset($this->judges[$key]);
        }
        // create the scheme
        if( isset( $data['rating_scheme_id'] ) && $data['rating_scheme_id'] ){
            // from rating_scheme_id
            $this->rating_scheme = new Ewz_Rating_Scheme($data['rating_scheme_id']);
        } else {
            throw new EWZ_Exception( 'No scheme id for new rating-form' );
        }
        $this->can_download = Ewz_Rating_Permission::can_download_rating( $this->rating_scheme->item_layout_id );
        $this->can_edit_rating_form = Ewz_Rating_Permission::can_edit_rating_form_obj( $this );
        $this->webforms = array();
        foreach( $this->item_selection['webform_ids'] as $wfid ){
            try{
                // ignore if the webform has been deleted by uninstalling EntryWizard and re-installing
                $this->webforms[$wfid] = new Ewz_Webform( $wfid, $this->rating_scheme->item_layout );
            } catch( Exception $e ) {}
        }
        // allow for admins disabling access for a judge who has already created ratings

        $judges_with_ratings =  Ewz_Item_Rating::get_judges_with_ratings( $this->rating_form_id );
        $this->actual_judges = array_unique( array_merge( $this->judges, $judges_with_ratings ) ) ;
        $this->disabled_judges = array_values(array_diff( array_values($this->actual_judges), array_values($this->judges) )) ;
    }

    /**
     * Constructor
     *
     * @param  mixed  $init  rating_form_id or array of data
     * @return none
     */
    public function __construct( $init, $item_ids='' ) {
        assert( Ewz_Base::is_pos_int( $init ) || is_string( $init ) || is_array( $init ) );
        assert( is_string( $item_ids ) );

        if ( is_numeric( $init ) ){
            $this->create_from_id( $init );
        } elseif ( is_string( $init ) ) {
            $this->create_from_ident( $init, $item_ids );
        } elseif ( is_array( $init ) ) {
            $this->create_from_data( $init );
        } else {
            throw new EWZ_Exception( 'Invalid rating_form constructor' );
        }
    }

    /**
     * Create a new rating_form object from the rating_form_id by getting the data from the database
     *
     * @param  int  $id the rating_form id
     * @return none
     */
    protected function create_from_id( $id ) {
        global $wpdb;
        assert( Ewz_Base::is_pos_int( $id ) );
        $dbrating_form = $wpdb->get_row( $wpdb->prepare( "SELECT rating_form_id, " .
                                                                 implode( ',', array_keys( self::$varlist ) ) .
                                                          " FROM " .  EWZ_RATING_FORM_TABLE . 
                                                         " WHERE rating_form_id=%d", $id ),   
                                         ARRAY_A );
        if ( !$dbrating_form ) {
            throw new EWZ_Exception( 'Unable to find matching rating_form for id', $id );
        }
        $this->set_data( $dbrating_form );
    }

   /**
     * Create a new rating_form object from the rating_form ident by getting the data from the database
     *
     * @param  string   $ident      the rating_form ident
     * @param  string   $item_ids   comma-separated list of item_ids to display, assume all if empty
     *                                             'last1', 'last2' ( all but last 1 or 2 columns read_only )
     * @return none
     */
    protected function create_from_ident( $ident, $item_ids ) {
        global $wpdb;
        assert( is_string( $ident ) );
        assert( is_string( $item_ids ) );
        $dbrating_form = $wpdb->get_row( $wpdb->prepare( "SELECT rating_form_id, " .
                                                                 implode( ',', array_keys( self::$varlist ) ) .
                                                          " FROM " .  EWZ_RATING_FORM_TABLE . 
                                                         " WHERE rating_form_ident=%s", $ident ), 
                                         ARRAY_A );
        if ( !$dbrating_form ) {
            throw new EWZ_Exception( 'Unable to find matching rating_form for identifier', $ident );
        }
        $this->set_data( $dbrating_form );
        $this->item_selection['itemlist'] = $item_ids;
    }

   /**
     * Create a rating_form object from $data
     *
     * @param  array  $data
     * @return none
     */
    protected function create_from_data( $data ) {
        assert( is_array( $data ) );
        
        if ( !array_key_exists( 'rating_form_id', $data ) ) {
            $data['rating_form_id'] = 0;
        } else {
            $data['rating_form_id'] = (int)$data['rating_form_id'];
        }    
        $data['rating_scheme_id'] = (int)$data['rating_scheme_id'];
        
        // create the item_selection structure
        $data['item_selection'] = array( 'webform_ids' => $data['webform_ids'],
                                                 'own' => $data['own'],
                                               'fopts' => $data['fopt'],
                                        );
        $this->set_data( $data );
        $this->check_errors();
    }


   /********************  Section: Validation  *******************************/

    /**
     * Check limits on numbers of selected dropdown options or checked checkboxes
     *  
     * 
     * @param   array of Ewz_Item_Ratings $ratings
     * @return  none
     */
    public function check_count_limits( $ratingset ){
       assert( is_array( $ratingset ) );
      $optcounts = array();
      $chkcount = array();
      $rating_fields = $this->rating_scheme->fields;
      $bad = array();
      foreach ( $ratingset as $rownum => $ratings ) {
          foreach ( $ratings as $rating ){
              foreach ( $rating->rating as $rating_field_id => $val ) {
                  switch ( $rating_fields[$rating_field_id]->field_type ) {
                  case 'xtr':
                  case 'fix':
                  case 'str':
                      break;
                  case 'opt':
                      if( !isset( $optcounts[$rating_field_id][$val] ) ){
                          $optcounts[$rating_field_id][$val] = 0;
                      }                        
                      self::validate_opt_counts( $rating_fields[$rating_field_id], $val, $optcounts[$rating_field_id][$val] );
                      ++$optcounts[$rating_field_id][$val];     
                      break;
                  /* case 'rad': */
                  /*     if( !isset( $chkcount[$rating_field_id] ) ){ */
                  /*         $chkcount[$rating_field_id] = 0; */
                  /*     } */
                  /*     $val = self::validate_rad_data( $val, $chkcount[$rating_field_id] );   */
                  /*     if( $val ){ */
                  /*         ++$chkcount[$rating_field_id]; */
                  /*     } */
                  /*     break; */
                  case 'chk':
                      if( !isset( $chkcount[$rating_field_id] ) ){
                          $chkcount[$rating_field_id] = 0;
                      }
                      $field = $rating_fields[$rating_field_id];
                      if( self::validate_chk_data( $field,  $val, $chkcount[$rating_field_id] ) ){
                          if( $val ){
                              ++$chkcount[$rating_field_id];
                          }
                      } else {
                          // number of checked checkboxes for this rating form and judge exceeds the limit.
                          // should not have allowed this situation, but checking counts for all item-ratings on each item-rating save would 
                          // be too much load on the server for catching a rare case -- normally this should be caught on the client.
                          $rating->rating[$rating_field_id] = false;
                          $rating->save();
                          if( !isset( $bad[$rating_field_id] ) ){
                               $bad[$rating_field_id] = true;
                          }
                      }
                      break;
                  default:
                      throw new EWZ_Exception( "Invalid field type " . $rating_fields[$rating_field_id]->field_type );
                  }
              }
          }
      }
      if( count($bad) > 0 ){
          $msg = '';
          foreach( $bad as $rating_field_id => $count ){
              $field = $rating_fields[$rating_field_id];
              $msg .= "Too many items were checked for " . $field->field_header . ", only the first " . $field->fdata['chkmax'] . 
                      " were allowed, the rest were unchecked.<br>";
          }
          throw new EWZ_Exception( "Error in checkbox counts: $msg" );
      }

    }

   /**
     * Check limits on numbers of selected dropdown options 
     *  
     * 
     * @param   Ewz_Rating_Field $field
     * @param   string    $val
     * @param   int       $optcount    -- max number of items with value $val allowed for $field
     * @return  none
     */
    public static function validate_opt_counts( $field, $val, $optcount )
    {
        assert( is_string( $val ) );
        assert( is_a( $field, 'Ewz_Rating_Field' ) );
        assert( Ewz_Base::is_nn_int( $optcount ) );
        assert( Ewz_Base::is_pos_int( $field->rating_field_id ) );

        if ( isset( $field->Xmaxnums ) &&  array_key_exists( $val, $field->Xmaxnums ) && $field->Xmaxnums[$val] ) {
            if ( (int)$field->Xmaxnums[$val] <= $optcount ) {
                throw new EWZ_Exception( "Too many '$val' values for $field->field_header."  );
            }
        }
    }

  /**
     * Check limits on numbers of checked checkboxes
     *  
     * 
     * @param   Ewz_Field $field
     * @param   boolean   $val      true if checkbox is checked
     * @param   int       $count    current count of checked checkboxes 
     * @return  boolean   true if current count is less than the max allowed
     */
   public static function validate_chk_data( $field, $val, $count )
    {
        assert( is_a( $field, 'Ewz_Rating_Field' ) );
        assert( is_bool( $val ) || $val === 1 || $val === 0 );
        assert( Ewz_Base::is_nn_int( $count ) );
        if (  $val && isset( $field->fdata['chkmax'] ) && ( $field->fdata['chkmax'] > 0 ) ) {
            if ( (int)$field->fdata['chkmax'] <= $count ) {                
                return false;

            }
        }     
        return true;
    }

   /* public static function validate_rad_data(  $val, $count ) */
   /*  { */
   /*      assert( is_string( $val ) ); */
   /*      assert( Ewz_Base::is_nn_int( $count ) ); */
 
   /*      if( $val && ( 1 <= $count ) ){  // this was checked and so was a previous item */
   /*          throw new EWZ_Exception( "More than one radiobutton checked:  $val" ); */
   /*      }  */
   /*      return $val; */
   /*  } */

    /**
     * Convenience function for checking that unserialize worked correctly for an array
     *
     * @param  string  $string   the string to unserialize
     * @param  string  $desc     a descriptive string to id which item failed to unserialize
     * @return array             the unserialized array
     */
   protected static function array_unserial( $string, $desc ) {
       assert( is_string( $string ) );
       assert( is_string( $desc ) );
        $arr = unserialize( $string );
        if( !is_array( $arr ) ){
            throw new EWZ_Exception( "Failed to unserialize $desc" );
        }
        return $arr;
   }

    /**
     * Check for various error conditions and throw an exception when one is found
     *
     * @param  none
     * @return none
     */
    protected function check_errors() {
        global $wpdb;
        
        if ( is_string( $this->judges ) ) {
            $this->judges = array_map( 'intval', self::array_unserial( $this->judges, 'judges' ) );
        }
        if ( is_string( $this->item_selection ) ) {
            $this->item_selection = self::array_unserial( $this->item_selection, 'item_selection' );
        }
        if ( is_string( $this->rating_status ) ) {
            $this->rating_status = self::array_unserial( $this->rating_status, "rating_status" );
        }
        foreach ( self::$varlist as $key => $type ) {
            settype( $this->$key, $type );
        }
        if ( !isset( $this->rating_form_title ) ) {
            throw new EWZ_Exception( 'A rating_form must have a title' );
        }
        if ( !isset( $this->rating_form_ident ) ) {
            throw new EWZ_Exception( 'A rating_form must have an identifier' );
        }
        $used1 = (int)$wpdb->get_var( $wpdb->prepare( "SELECT count(*)  FROM " . EWZ_RATING_FORM_TABLE .
                                                      " WHERE rating_form_title = %s AND rating_form_id != %d", 
                                                      $this->rating_form_title, $this->rating_form_id ) );
        if ( $used1 > 0 ) {
            throw new EWZ_Exception( "Rating_form title  '$this->rating_form_title' is already in use" );
        }
        $used2 = (int)$wpdb->get_var( $wpdb->prepare( "SELECT count(*)  FROM " . EWZ_RATING_FORM_TABLE .
                                                      " WHERE rating_form_ident = %s AND rating_form_id != %d", 
                                                      $this->rating_form_ident, $this->rating_form_id ) );
        if ( $used2 > 0 ) {
            throw new EWZ_Exception( 'Rating_form identifier ' . $this->rating_form_ident . ' is already in use' );
        }
        if( $this->rating_form_id ){
            $this->recalculate();
        }

        if( isset( $this->rating_status['own'] ) && ( $this->rating_status['own'] != $this->item_selection['own'] ) ){
            error_log( 'EWZ: Rating status says "own" is ' . $this->rating_status['own'] . 
                                     ", item selection says " .  $this->item_selection['own'] );
        }            
                
        if( count( $this->judges ) <= 0 ) {
            $this->judges = array( 1 );    // make the admin a judge if no others are selected
        }

        foreach( $this->judges as $judge_id ){
            if( ( $judge_id > 0 ) && !isset( $this->rating_status[$judge_id] ) ){
                $this->rating_status[$judge_id] = 0;
            }
        }

        return true;
    }

    /********************  Section: Misc Object Functions  **********************/

    /**
     * Return a list of judges with names, with input $sel_id selected, for use with ewz_option_list
     * If $this->judges includes 0, then if $include_all is true, return a list of all users.
     * If $this->judges includes 0 and $include_all is false, return the list of judges other than 0.
     * 
     **/
    public function get_judges( $sel_id, $include_all ){
        assert( Ewz_Base::is_nn_int( $sel_id ) );
        assert( is_bool( $include_all ) );
        if( !current_user_can( 'list_users' ) ){
            return array();
        }
        $options = array();
        if( $include_all && in_array( 0, $this->judges) ){
            $users = get_users();
            foreach ( $users as $user ) {
                $display =  $user->display_name . ' ( ' . $user->user_login . ', ID=' .  $user->ID . ' )';
                array_push( $options, array(  'value' => $user->ID, 'display' => $display, 'selected'=>($sel_id == $user->ID) ) );
            }
        } else {
            foreach ( $this->judges as $judge_id ) {
                if( $judge_id > 0 ){
                    $user = get_userdata( $judge_id );
                    $display =  $user->display_name . ' ( ' . $user->user_login . ', ID=' .  $user->ID . ' )';
                    array_push( $options, array(  'value' => $user->ID, 'display' => $display, 'selected'=>($sel_id == $user->ID) ) );
                }
            }
        }
        return $options;
    }

    /**
     * Recalculate and save the rating_status stored on the database
     * Required in case judges or items are added or removed
     **/
    public function recalculate()
    {
        $this->get_all_items_and_ratings();    // re-calculates the rating_status
        $this->save_rating_status();
    }

    /**
     * Update the judge status to "Complete" and disable their access to the rating form
     *       ( first running "check_count_limits" because its hard to do in the validation section )
     * @param    $judge_id
     * @return   '1' if successful, otherwise throws an exception
     **/
    public function finished( $judge_id ){
        assert(  Ewz_Base::is_pos_int( $judge_id ) );

        // are we repeating ourselves here?  Do we need both?
        $this->get_all_items_and_ratings();  // re-calculates the rating_status
        $ratings = $this->get_user_ratings_by_item( $judge_id );

        $this->check_count_limits( $ratings );
        if( strpos( $this->rating_status[$judge_id], 'Complete' ) === false ){
            $this->rating_status[$judge_id] .= ' Complete';       
        }
        $this->save_rating_status();
         
        return '1';
    }

    /**
     * Re-open the rating form for the judge 
     * ( in case they have clicked "finished" and the admin wants changes )
     *
     * @param    $judge_id
     * @return   none
     **/
    public function reopen_for( $judge_id ){
        assert(  Ewz_Base::is_pos_int( $judge_id ) );
        if( strpos( $this->rating_status[$judge_id], 'Complete' ) === false ) {
            throw new Ewz_Exception( 'ERROR: Attempt to reopen rating form for judge who was not finished.' );
        }
        $this->rating_status[$judge_id] = trim(str_replace( 'Complete', '',  $this->rating_status[$judge_id] ));
        $this->save_rating_status();
    }

    /**
     * Delete all ratings on this form for the judge 
     *
     * @param    int   $judge_id
     * @return   none
     **/
    public function del_judge_ratings( $judge_id ){
        assert(  Ewz_Base::is_pos_int( $judge_id ) );
        // have to remove the "Complete" flag before status will be redone
        $this->rating_status[$judge_id] = trim(str_replace( 'Complete', '',  $this->rating_status[$judge_id] ));
        Ewz_Item_Rating::delete_ratings_for_judge( $this->rating_form_id, $judge_id );
        $this->recalculate();   // full recalculation of counts
        $this->save_rating_status();     
    }

    /**
     * Get the list of judges who should be able to see the form  
     * Exclude any judges who are "finished"
     * 
     * @return  array of wordpress user-ids
     **/
    public function get_active_judge_ids(){
        $ids = array();
        foreach( $this->judges as $judge_id ){
            if( !isset( $this->rating_status[ $judge_id ] ) ||
                strpos( $this->rating_status[$judge_id], 'Complete' ) === false ) {
                array_push(  $ids, $judge_id );
            } 
        }
        return $ids;
    }               
   

    /********************  Section: Get Item Ratings *****************/  
    /**
     * Return all the items for the rating form, with any ratings that exist.
     * 
     * Returns one row per item_rating, and a single row if there is no rating for the item.
     * Also re-calculates the rating_status.
     * This is the version for the admin area.
     *
     * @return  array of item_ratings, including blank ones for items with no rating.
     **/
     public function get_all_items_and_ratings(){
         assert( is_admin() );
         global $wpdb;

         if( !(isset( $this->item_selection['webform_ids']) && count( $this->item_selection['webform_ids'] ) > 0 ) ){
             // must select at least one webform
             throw new EWZ_Exception( "At least one webform must be selected");
         }

         $sql = Ewz_Item_Rating::get_items_sql( get_current_user_id(), $this, true );
         $data = $wpdb->get_results( $sql, OBJECT );  // is ordered, get_items_sql returns no tainted data
         $iratings = array();
         $items = array();
         $unselected_ratings = array();
         $rating = null;
         $done = array();

         foreach( $this->judges as $judge_num => $jid ){
             $done[$jid] = 0;
         }
         foreach( $data as  $d ){
             // If the rating exists
             if( isset( $d->item_rating_id ) ){
                 $rating =  new Ewz_Item_Rating( $d->item_rating_id );
                 $rating->complete = true;
             } else {
                 // create an item_rating for the item if there wasnt one already
                 // ( will only get saved if something is entered )
                 $rating = new  Ewz_Item_Rating( array( 
                     'item_id' => $d->item_id,                                                  
                     'rating_form_id' => $this->rating_form_id
                 ) );
                 $rating->complete = false;
             }

             // Weed out those not meeting the other selection criteria
             if( $this->check_criteria( $rating ) ){
                 if($rating->complete ){
                     if( isset( $done[$d->judge_id] ) ){
                         ++$done[$d->judge_id];
                     } else {
                         $done[$d->judge_id] = 1;   // needed for in_array( 0, $this->judges ) case
                     }
                 }
                
                 array_push( $iratings, $rating );
                 if( !isset ( $items[$d->item_id] ) ){
                     $items[$d->item_id] = 1;
                 }
                 // set which judge sees the fields with the divide flag set
                 $this->set_divide_flags($d->item_id);

             } else {
                 if( $rating->complete ){
                     array_push( $unselected_ratings, $rating  );
                 }
             }
         }
         // set the status
         $this->rating_status["count"] = count( $items );
         if( in_array( 0, $this->judges ) ){
             $this->rating_status[0] =  count( $done );
         } else {
             foreach( $done as $judge_id => $jcount ){ 
                 // note  === required when comparing string and numeric string
                 if( !isset( $this->rating_status[$judge_id] ) ||
                     strpos( $this->rating_status[$judge_id], 'Complete' ) === false ) {
                     // TODO: if requirements change after a judge has completed, this may be wrong.
                     $this->rating_status[$judge_id] = $jcount;
                 }
             }
         }
         return $iratings;               
     }


   /**
     * Return all the items for the rating form, with any ratings.
     * 
     * Returns one row per item, with rating elements by different judges concatenated, 
     * and a single row if there is no rating for the item. 
     * Used for read-only views.
     *
     * @return  array of item_ratings, indexed by item_id, including blank ones for items with no rating.
     **/
     public function get_ratings_by_item_ro(){
        $data = Ewz_Item_Rating::get_ratings_by_item( $this );
        foreach( $data as $item_id => $d ){
            $this->set_divide_flags($item_id);
        }
        if ( count( $data ) < 1 ) {
            throw new EWZ_Exception( "No matching items found." );
        }
        return $data;
     }


    /**
     * Return all the items for the rating form, with any ratings that were created by the current user.
     * 
     * Returns one row per item_rating, and a single row if there is no rating for the item.
     * Returns only those ratings created by the current user as judge, plus blank ones for those not rated.  
     *
     * @param   int   $judge_id  ( should be same as current user unless admin is using front-end "view as judge" facility )
     * @return  array of item_ratings, including blank ones for items with no rating.
     **/
     public function get_user_ratings_by_item( $judge_id ){
        // current user should be either an admin using the front-end view or the actual judge
        assert( ( $judge_id == get_current_user_id() ) ||  Ewz_Rating_Permission::can_edit_rating_form_obj($this));

        if( !in_array( 0, $this->judges ) && !in_array( $judge_id , $this->judges ) ){
            return array();
        }
        global $wpdb;

        if( !(isset( $this->item_selection['webform_ids']) && count( $this->item_selection['webform_ids'] ) > 0 ) ){
            // must select at least one webform
            throw new EWZ_Exception( "At least one webform must be selected");
        }

        $sql = Ewz_Item_Rating::get_items_sql( $judge_id, $this, false );
        $data = $wpdb->get_results( $sql , OBJECT );  // is ordered, get_items_sql returns no tainted data

        $iratings = array();
        $rating = null;

        $judge_num = 1;
        if( !in_array( 0, $this->judges ) ){
            $judge_num = array_keys( $this->judges, $judge_id  )[0];
        }
        $rating_count = 0;
        $done_count = 0;
        foreach( $data as  $d ){
            // If the rating exists
            if( isset( $d->item_rating_id ) ){
                $rating =  new Ewz_Item_Rating( $d->item_rating_id );
                $rating->complete = true;
            } else {
                // create an item_rating for the item if there wasnt one already
                // ( will only get saved if something is entered )
                $rating = new  Ewz_Item_Rating( array( 
                    'item_id' => $d->item_id,                                                  
                    'rating_form_id' => $this->rating_form_id
                ) );
                $rating->complete = false;
            }

 
            // Weed out those not meeting the other selection criteria
            if( $this->check_criteria( $rating ) ){
                // make it an array here, even tho only one rating, so it can be processed along with
                // views that show all ratings for the item
                $iratings[$d->item_id] = array($rating);
                ++$rating_count;
                if( $rating->complete ){
                    ++$done_count;
                }
                $this->set_divide_flags($d->item_id);
            }
        }
        $this->rating_status['count'] = $rating_count;
        if( !isset( $this->rating_status[$judge_id] ) || strpos( $this->rating_status[$judge_id], 'Complete' ) === false ) {
            $this->rating_status[$judge_id] = $done_count;
        }
        return $iratings;       
    }

    /**
     * Set  the show_judge flag for the item
     **/
     public function set_divide_flags( $item_id ){
         assert( Ewz_Base::is_nn_int( $item_id ) );
         $njudges = count( $this->judges );

         if( !in_array( 0, $this->judges ) ){
             $this->show_judge[$item_id] = 0;
             foreach( $this->judges as $judge_num => $jid ){
                 if( $item_id%$njudges == $judge_num ){
                     $this->show_judge[$item_id] = $jid;
                 }
             }
             assert( $this->show_judge[$item_id] > 0 ); 
         } 
     }

    /**
     * Return true if the input rating meets the item_selection criteria for this rating form
     *
     * @param  Ewz_Rating  $rating
     * @return boolean
     **/
     public function check_criteria( $rating ){
        assert( is_a( $rating, 'Ewz_Item_Rating' ) );
        $ok = true;
        if( isset( $this->item_selection['fopts'] ) ){
            foreach( $this->item_selection['fopts'] as $field_id=>$sel_value ){
                if( is_int( $field_id )  && isset( $rating->item->item_data[$field_id] ) ){ 
                    $itm_value = $rating->item->item_data[$field_id]['value'];
                } elseif( isset( $rating->custom->$field_id ) ) {
                    $itm_value = $rating->custom->$field_id;
                }
                $choiceok = false;
                foreach( $sel_value as $choice ){   
                    switch( $choice ){
                    case '~+~': 
                        $choiceok = !empty( $itm_value );
                        break;
                    case '~-~':
                        $choiceok = empty( $itm_value );
                        break;
                    case '~*~':
                        $choiceok = true;
                        break;
                    default:
                        $choiceok = ( $choice == $itm_value );
                        break;
                    }
                    if( $choiceok ){
                        break; // out of foreach $sel_value
                    }
                }
                $ok = $ok && $choiceok;
            }
        }
        return $ok;
    }

   /********************  Section: Spreadsheet Functions  **********************/
    /**
     * Download a spreadsheet of items and ratings  in a specified style
     * 
     * @param   string   $style   I = per-item,  R = per-rating
     * @return  outputs the spreadsheet to stdout
     */
    public function download_spreadsheet( $style ){
        assert( is_string( $style ) );
        if( !$this->can_download ){
             throw new EWZ_Exception( "Sorry, you do not have permission to do this." );
        }
        $rows = array();
        switch( $style ){
        case 'I': $rows = $this->download_spreadsheet_I();
            break;
        case 'R': $rows = $this->download_spreadsheet_R();
            break;
        default:  throw new EWZ_Exception( "Invalid spreadsheet style" );
        }
        
        $filename = $this->rating_form_ident . "_rating.csv";
        header( "Content-Disposition: attachment; filename=\"$filename\"" );
        header( "Content-Type: text/csv" );
        header( "Cache-Control: no-cache" );

        $out = fopen( "php://output", 'w' );  // write directly to php output, not to a file
        if( !$out ){
            throw new EWZ_Exception( "Failed to open php output for writing");
        }
        foreach( $rows as $row ){
            // initial test version, just show id's
            fputcsv( $out, $row, "," );
        }
        
        fclose( $out );
        exit();
    }

    /**
     * Download a spreadsheet of items and ratings styled one line per-item
     * 
     * @return array of csv, one per item 
     */
    public function download_spreadsheet_I(){
        $data = Ewz_Item_Rating::get_ratings_by_item( $this );
        if ( count( $data ) < 1 ) {
            throw new EWZ_Exception( "No matching items found." );
        }
        $maxcol = 50;
        $judge_cols = array();
        $extra_cols = $this->rating_scheme->extra_cols;
        $rating_fields = Ewz_Rating_Field::get_rating_fields_for_rating_scheme( $this->rating_scheme_id, 'ss_column' );
        $rows = array( );

        $j = 0;
        foreach ( $data as $item_id => $item_ratings ) {
            $this->set_divide_flags($item_id);
            foreach( $item_ratings as $item_rating ){
                if( $item_rating->rating ){
                    if( !isset( $judge_cols[ $item_rating->judge_id ] ) ){
                        $judge_cols[ $item_rating->judge_id ] = $j;
                        ++$j;
                    }
                }
            }
        }
        $rows[0] = $this->get_headers_for_ss( $rating_fields, $extra_cols, $judge_cols );
        $n = 1;

        foreach ( $data as $item_id => $item_ratings ) {
            $ratingrow = array_fill( 0, $maxcol + 1, '' );
            $item = new Ewz_Item( $item_id );
            $custom = new Ewz_Custom_Data( $item->user_id );
            foreach ( $extra_cols as $xcol_ident => $sscol ) {
                $ratingrow[$sscol] = $this->get_extra_value( $xcol_ident, $sscol, $item, $custom );
            }

            foreach ( $rating_fields as $rfield_id => $rfield ) {
                if( $rfield->ss_column >= 0 ){
                    switch( $rfield->field_type ){
                    case 'fix':
                        $field_id = $rfield->fdata['field_id'];
                        if( isset( $item->item_files[$field_id]['fname'] ) ){
                            // image filename 
                            $ratingrow[$rfield->ss_column] = $this->ss_display( $item_id, $rfield, basename( $item->item_files[$field_id]['fname'] ) );
                        } else {
                            $ifield = new Ewz_Field( $field_id );
                            $ratingrow[$rfield->ss_column] = $this->ss_display(  $item_id, $rfield, 
                                                                                 ewz_display_item( $ifield, $item->item_data[$field_id]['value'] ) );
                        }
                        break;
                    case 'xtr':
                        $ratingrow[$rfield->ss_column] = $this->ss_display(  $item_id, $rfield, 
                                                                             $this->get_extra_value( $rfield->fdata['dkey'], $rfield->ss_column, $item, $custom )
                                                                          );
                        break;
                    case 'lab':
                        $ratingrow[$rfield->ss_column] = $this->ss_display( $item_id, $rfield, $rfield->fdata['label'] );                    
                        break;
                    default:
                        foreach( $item_ratings as $item_rating ){
                            if( isset( $item_rating->rating[$rfield_id] ) ){
                                $col_incr = $judge_cols[$item_rating->judge_id];
                                $ratingrow[$rfield->ss_column + $col_incr] = ewz_display_item( $rfield, $item_rating->rating[$rfield_id] );
                            }
                        }
                    } 
                }               
            }
            for ( $col = 0; $col <= $maxcol; ++$col ) {
                if ( $ratingrow[$col] ) {
                    $rows[$n][$col] = $ratingrow[$col];
                }  else {
                    $rows[$n][$col] = '';
                }
            }
            ++$n;
        }
        return $rows;
    }

    /**
     * Return the string to be displayed in the spreadsheet for the input item, field and value
     *
     * @param  int                $item_id    id of the Ewz_Item 
     * @param  Ewz_Rating_Field   $rfield     the Ewz_Rating_Field to be displayed
     * @param  string or boolean  $string     the value associated with the field
     **/
    protected function ss_display( $item_id, $rfield, $string ){
        assert( Ewz_Base::is_nn_int( $item_id ) );
        assert( is_a( $rfield, 'Ewz_Rating_Field' ) );
        assert( is_string( $string ) || is_bool( $string ) ||  $string == '' );

        if( is_bool($string) ){
            $string = $string ? '1' : '0';
        }
        if( !in_array( 0, $this->judges ) && $rfield->divide ){
            $show = '(' . $this->show_judge[$item_id] . ')';
            return sprintf( "%-5s %s", $show, $string ) ;
        } else {
            return $string;
        }
    }

    /**
     * Download a spreadsheet of items and ratings styled one line per-rating
     * 
     * @return array of csv
     **/
    public function download_spreadsheet_R(){
        $ratings = $this->get_all_items_and_ratings();   // re-calculates the rating status
        if ( count( $ratings ) < 1 ) {
           throw new EWZ_Exception( "No matching items found." );
        }
        $rating_fields = Ewz_Rating_Field::get_rating_fields_for_rating_scheme( $this->rating_scheme_id, 'ss_column' );
        $extra_cols = $this->rating_scheme->extra_cols;   
        $rows = array( );
        $rows[0] = $this->get_headers_for_ss( $rating_fields, $extra_cols );
        $maxcol = max( array_keys( $rows[0] ) );
        $n = 1;
                          
        foreach ( $ratings as $rating ) {
            // data for the input fields set in the rating scheme
            $ratingdata = $this->get_rating_data_for_ss( $rating_fields, $rating, $maxcol );
            if( $rating->complete ){
               $ratingdata[$maxcol] = $rating->judge_id;
            }
            // additional  data set in the rating scheme
            $extradata = array_fill( 0, $maxcol + 1, '' );

            foreach ( $extra_cols as $xcol_ident => $sscol ) {
                $extradata[$sscol] = $this->get_extra_value( $xcol_ident, $sscol, $rating->item, $rating->custom );
            }

            for ( $col = 0; $col <= $maxcol; ++$col ) {
                if ( $ratingdata[$col] ) {
                    $rows[$n][$col] = $ratingdata[$col];
                } elseif ( $extradata[$col] ) {
                    $rows[$n][$col] = $extradata[$col];
                } else {
                    $rows[$n][$col] = '';
                }
            }
            ++$n;
        }
        return $rows;
    }

     /**
     * Return an "extra" value for display in spreadsheet
     * 
     * @param   $xcol_ident   identifier for the column
     * @param   $sscol        if this is < 0, return ''
     * @param   $item         Ewz_Item object 
     * @param   $custom       Ewz_Custom_Data for the item owner
     * @return  string        the extra data required
     **/
   public function get_extra_value( $xcol_ident, $sscol, $item, $custom ){
      assert( is_string( $xcol_ident ) );
      assert( is_numeric( $sscol ) );
      assert( is_a( $item, 'Ewz_Item' ) );
      assert( is_a( $custom, 'Ewz_Custom_Data' ) || ( null == $custom ) );
      $display = Ewz_Rating_Scheme::get_all_display_data();
      if ( $sscol >= 0 ) {
          $datasource = '';
          // dont crash on undefined custom data
          if( isset( $display[$xcol_ident] ) ){
              switch ( $display[$xcol_ident]['dobject'] ) {
                case 'wform':
                    $datasource = $this->webforms[ $item->webform_id ];
                    break;
                case 'user':
                    $datasource = get_userdata( $item->user_id );
                    break;
                case 'item':
                    $datasource = $item;
                    break;
                case 'custom':
                    $datasource =  $custom;
                    break;
                default:
                    throw new EWZ_Exception( 'Invalid data source ' . $display[$xcol_ident]['dobject'] );
              }
              return Ewz_Rating_Scheme::get_extra_data_item( $datasource, $display[$xcol_ident]['value'] );
          }
      }
      return '';
   }

   /**
    * Return an array of data for display in a spreadsheet
    * 
    * @param   $rating_fields  array of Ewz_Rating_Fields 
    * @param   $rating         the Ewz_Item_Rating entered by the judge
    * @param   $maxcol         maximum length of row
    * @return  array of data entered by judge, for display in spreadsheet
    **/
   private function get_rating_data_for_ss( $rating_fields, $rating, $maxcol ) {
        assert( is_array( $rating_fields ) );
        assert( is_a( $rating, 'Ewz_Item_Rating' ) );
        assert( is_int( $maxcol ) );
        $ratingrow = array_fill( 0, $maxcol + 1, '' );
        $item = $rating->item;
        foreach ( $rating_fields as $rfield_id => $rfield ) {
            if( $rfield->ss_column >= 0 ){
                if( $rfield->field_type == 'fix' ){ 
                    $field_id = $rfield->fdata['field_id'];
                    if( isset( $item->item_files[$field_id]['fname'] ) ){  
                        $ratingrow[$rfield->ss_column] = $this->ss_display( $item->item_id, $rfield, basename( $item->item_files[$field_id]['fname']) ); 
                    } else {
                        $field = new Ewz_Field( $field_id );
                        $ratingrow[$rfield->ss_column] =  $this->ss_display( $item->item_id, $rfield, 
                                                                             ewz_display_item( $field, $item->item_data[$field_id]['value']) );
                    }
                } elseif( $rfield->field_type == 'xtr' ){ 
                    $ratingrow[$rfield->ss_column] = $this->ss_display( $item->item_id, $rfield,
                                                                        $this->get_extra_value( $rfield->fdata['dkey'], $rfield->ss_column, $item, 
                                                                        $rating->custom )
                                                                      );
                } elseif( $rfield->field_type == 'lab' ){
                        $ratingrow[$rfield->ss_column] = $this->ss_display(  $item->item_id, $rfield, $rfield->fdata['label'] );
                } elseif( isset( $rating->rating[$rfield_id] ) ){
                    // a judge-entered rating field
                    $ratingrow[$rfield->ss_column] = $this->ss_display( $item->item_id, $rfield, 
                                                                        ewz_display_item( $rfield, $rating->rating[$rfield_id] ));
                }
            }
        }
        return $ratingrow;
    }

  /**
   * Set a value in an array, but raise an exception if it already holds a value
   *
   * @param   array $arr
   * @param   int   $col  the index to be set
   * @param   mixed $value  the value to set $arr[$col] to
   * @return  array    the new $arr
   */
   protected function set_column( $arr, $col, $value ){
       assert( is_array( $arr ) );
       assert( is_int( $col ) );
       assert( is_int( $value ) || is_array( $value ) || is_bool( $value ) ||  is_string( $value ) );
       if( isset( $arr[$col] ) ){
           $arr[$col] .= " *[" . $value . "]*";
       } else {
           $arr[$col] = $value;
       }
       return $arr;
   }

  /**
   * Return the header row for the spreadsheet
   *
   * @param   array of Ewz_Rating_Field     $fields     fields array from scheme - data input via the webform
   * @param   array of (string=>int) $extra_cols extra columns array from scheme - other data for display
   * @param   array of (string=>int) $judge_cols columns reserved for 2nd, 3rd, etc judges
   * @return  array of headers
   */
   protected function get_headers_for_ss( $rating_fields, $extra_cols, $judge_cols = array() ) {
        assert( is_array( $rating_fields ) );
        assert( is_array( $extra_cols ) );
        assert( is_array( $judge_cols ) );
        $hrow = array();
        foreach ( $rating_fields as $rating_field ) {
            $txt_data = array( 'ss_col_fmt' => 'Formatted ' . $rating_field->field_ident );
            if (  $rating_field->ss_column >= 0 ){
                if( $rating_field->field_type != 'fix' &&
                    $rating_field->field_type != 'xtr' &&
                    $rating_field->field_type != 'lab' &&
                    $judge_cols ) {
                    foreach( $judge_cols as $judge_id => $c ){
                        $hrow  = $this->set_column( $hrow, $rating_field->ss_column + $c, $rating_field->field_header . " Judge $judge_id" );
                    }
                } else {
                    $hrow  = $this->set_column( $hrow, $rating_field->ss_column, $rating_field->field_header );
                }   
            }
            // str type potentially has an extra "formatted text" column
            if ( 'str' == $rating_field->field_type ) {
                foreach ( $txt_data as $ss_txt_col => $ss_txt_header ) {
                    $sstxtcol = $rating_field->fdata[$ss_txt_col];
                    if ( $sstxtcol >= 0 ) {
                        $hrow  = $this->set_column( $hrow, $sstxtcol,  $ss_txt_header );
                    }
                }
            }
        }
        if( !$hrow ){
            throw new EWZ_Exception( "No spreadsheet columns defined" );
        }
            
        $dheads = Ewz_Rating_Scheme::get_all_display_headers();
        foreach ( $extra_cols as $xcol => $sscol ) {
            if ( $sscol >= 0 && isset( $dheads[$xcol] ) ) {
                $hrow  = $this->set_column( $hrow, $sscol, $dheads[$xcol]['header'] );
            }
        }
        $maxk = max( array_keys( $hrow ) );
        if( !$judge_cols ){
            ++$maxk;
            $hrow[$maxk] = 'Judge ID';
        }
        for ( $i = 0; $i <= $maxk; ++$i ) {
            if ( !isset( $hrow[$i] ) ) {
                $hrow[$i]= 'Blank ';
                $j = $i%26;
                $n = $i/26;
                if( $n >= 1 ){      
                    $hrow[$i] .= chr(65 + $n - 1);
                }
                $hrow[$i] .= chr(65 + $j);
            }
        }
        ksort($hrow);
        return $hrow;
    }

    
   /********************  Section: Database Updates **********************/

   /**
     * Save the rating_status to the database
     * Static to save the overhead of creating the object.
     *
     * @param  integer $rating_form_id  -- id of rating form
     * @param  array   $status          -- status array to save
     * @return none
     */
   public static function save_status( $rating_form_id, $status ) {
       assert( Ewz_Base::is_pos_int( $rating_form_id ) );
       assert( is_array( $status ) );
       global $wpdb;
        if ( $rating_form_id > 0 ) {
            $data = stripslashes_deep( array( 'rating_status' => serialize( $status )    // %s
                                             ) );
            $datatypes = array(
                                '%s',   // = status
                              );

            $rows = $wpdb->update( EWZ_RATING_FORM_TABLE,                              // no esc
                                   $data,       array( 'rating_form_id' => $rating_form_id ), 
                                   $datatypes,  array( '%d' ) );
            if ( $rows > 1 ) {
                throw new EWZ_Exception( "Problem updating the status for  rating_form $rating_form_id" );
            }
        }
    }


    /**
     * Save the rating status to the database
     * This is the object function, saving it's own rating_status.
     *
     * @param none
     * @return none
     **/
     public function save_rating_status(){
         self::save_status( $this->rating_form_id, $this->rating_status );
     }


    /**
     * Save the rating_form to the database
     *
     * Check for permissions, then update or insert the rating_form data
     *
     * @param none
     * @return none
     */
    public function save() {
        global $wpdb;
        if ( $this->rating_form_id ) {
            if ( !$this->can_edit_rating_form && !defined( 'DOING_CRON' )) {
                throw new EWZ_Exception( "No changes saved. Insufficient permissions to change rating_form '$this->rating_form_title' )" );
            }
        } else {
            if ( !Ewz_Rating_Permission::can_edit_all_rating_forms() ) {
                throw new EWZ_Exception( 'No changes saved. Insufficient permissions to create a rating_form' );
            }
        }
        // ok, we have all the permissions, go ahead
            
        $this->check_errors();

        // NB: rating_form_order is not updated here
        $data = stripslashes_deep( array(
                                         'rating_scheme_id' => $this->rating_scheme_id,          // %d
                                         'rating_form_title' => $this->rating_form_title,        // %s
                                         'rating_form_ident' => $this->rating_form_ident,        // %s
                                         'item_selection' => serialize( $this->item_selection ), // %s
                                         'shuffle' => $this->shuffle ? 1 : 0,                    // %d
                                         'judges' => serialize( $this->judges ),                 // %s
                                         'rating_open' => $this->rating_open ? 1 : 0,            // %d
                                         'rating_status' => serialize( $this->rating_status ),   // %s
                                         ) );
        $datatypes = array( '%d',   // = rating_scheme_id
                            '%s',   // = rating_form_title
                            '%s',   // = rating_form_ident
                            '%s',   // = item_selection
                            '%d',   // = shuffle
                            '%s',   // = judges
                            '%d',   // = rating_open
                            '%s',   // = rating_status
                           );

        if ( $this->rating_form_id ) {
            // updating -- order should already be set
            $rows = $wpdb->update( EWZ_RATING_FORM_TABLE,                            // no esc
                                   $data,       array( 'rating_form_id' => $this->rating_form_id ), 
                                   $datatypes,  array( '%d' ) );
            if ( $rows > 1 ) {
                throw new EWZ_Exception( "Problem updating the rating_form '$this->rating_form_title'" );
            }
        } else {
            // inserting, set the order to be last
            $wpdb->insert( EWZ_RATING_FORM_TABLE, $data, $datatypes );        // no esc
            $err = $wpdb->last_error;
            $this->rating_form_id = $wpdb->insert_id;
            if ( !$this->rating_form_id ) {
                throw new EWZ_Exception( "Problem creating the rating_form '$this->rating_form_title' ", $err );
            }
            $n_rating_forms = (int)$wpdb->get_var( "SELECT count(*)  FROM " . EWZ_RATING_FORM_TABLE  );  // no tainted data

            $wpdb->query($wpdb->prepare( "UPDATE " . EWZ_RATING_FORM_TABLE . 
                                         "   SET rating_form_order = %d " .
                                         " WHERE  rating_form_id = %d ", 
                                         $n_rating_forms, $this->rating_form_id ) ); 

            $this->recalculate();   // to get the counts
            $this->save_rating_status();  // make sure we have a value for count stored
        }
    }

   /**
     * Delete the rating_form from the database
     *
     * @param  none
     * @return "1 rating_form deleted."  if successful, otherwise raises an exception
     */
    public function delete( $delete_ratings = self::FAIL_IF_RATINGS ) {
        assert( $delete_ratings == self::DELETE_RATINGS || 
                $delete_ratings == self::FAIL_IF_RATINGS );

        global $wpdb;
        if ( $this->rating_form_id ) {

            if ( !$this->can_edit_rating_form && !defined( 'DOING_CRON' )) {
                throw new EWZ_Exception( "No changes saved. Insufficient permissions to change rating_form '$this->rating_form_title' )" );
            }
            $all_ratings = Ewz_Item_Rating::get_rating_ids_for_form( $this->rating_form_id );
            $errmsg = '';

            if ( $delete_ratings == self::DELETE_RATINGS ) {
                foreach ( $all_ratings as $item_rating_id ) {
                    try {
                        $item_rating = new Ewz_Item_Rating( (int)$item_rating_id );
                        $item_rating->delete();
                    } catch( EWZ_Exception $e ) {
                        $errmsg .= $e->getMessage();
                    }
                }
                if( $errmsg ){
                    throw new EWZ_Exception( "Error deleting: " . $errmsg );
                }
            } else {
                $n = count( $all_ratings  );
                if ( $n > 0 ) {
                    throw new EWZ_Exception( "Rating_form has $n item_ratings attached." );
                }
            }

            // now delete the rating_form and renumber the rating_form_order for the remaining ones
            $rowsaffected = $wpdb->query( $wpdb->prepare( "DELETE FROM " . EWZ_RATING_FORM_TABLE . 
                                                          " WHERE rating_form_id = %d", $this->rating_form_id ) );
            if ( 1 == $rowsaffected ) {
                self::renumber_rating_forms($this->rating_form_order);
                return "1 rating_form deleted.";
            } else {
                throw new EWZ_Exception( "Problem deleting rating_form '$this->rating_form_title '", $wpdb->last_error );
            }
        }
    }
}

