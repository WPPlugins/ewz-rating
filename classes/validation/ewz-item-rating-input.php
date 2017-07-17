<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/ewz-layout.php");
require_once( EWZ_PLUGIN_DIR . "classes/validation/ewz-input.php");
require_once( EWZ_PLUGIN_DIR . "classes/validation/ewz-upload-input.php");

/* Validation for the Item Rating page */

class Ewz_Item_Rating_Input extends Ewz_Input
{

    function __construct( $form_data ) { 
        parent::__construct( $form_data );
        assert( is_array( $form_data ) );
        $rating_form = new Ewz_Rating_Form($form_data['rating_form_id']);

        // hacks to make the upload code re-usable
        // TODO: split out the upload validation stuff and make it usable by both
        $this->restrictions = $rating_form->rating_scheme->restrictions;
        $this->fields = $rating_form->rating_scheme->fields;
        foreach( $this->fields as $fid => $field ){
            $field->Xmaxnums = array();
            $this->fields[$fid]->field_id = $this->fields[$fid]->rating_field_id;
        }

        $this->rules = array(
                             'ewzratingnonce' => array( 'type' => 'rnonce',    'req' => true,  'val' => '' ),
                             'item_rating_id' => array( 'type' => 'to_seq',    'req' => false, 'val' => '' ),
                             'rating_form_id' => array( 'type' => 'to_seq',    'req' => true,  'val' => '' ),
                             'item_id'        => array( 'type' => 'to_seq',    'req' => true,  'val' => '' ),
                             'judge_id'       => array( 'type' => 'to_seq',    'req' => true,  'val' => '' ),
                             'rating'         => array( 'type' => 'v_rating',  'req' => true,  'val' => '' ),
                             'action'         => array( 'type' => 'fixed',     'req' => false, 'val' => 'ewz_save_rating' ),
                             'view'           => array( 'type' => 'v_view',    'req' => false, 'val' => '' ),
                             '_wp_http_referer' => array( 'type' => 'to_string', 'req' =>  false, 'val' => '' ),
                             );
        $this->validate();
    }

    function validate(){
        // rating is required, so do this before calling validate
        if ( !array_key_exists( 'rating', $this->input_data ) ){
            $this->input_data['rating'] = array();
        }
        foreach( $this->fields as $field ){
            if( $field->field_type == 'chk' || $field->field_type == 'rad' ){
                if ( !array_key_exists( $field->rating_field_id, $this->input_data['rating'] ) ) {
                    $this->input_data['rating'][$field->rating_field_id] = '0';    // v_rating fcn expects string, changes it to false
                }
            }
        }        
        parent::validate();
    }
               

    function rnonce( $value, $arg ) {
        assert( is_string( $value ) );
        assert( isset( $arg ) );
        return wp_verify_nonce( $value, 'ewzrating' );
    }

    function v_view( $value, $arg ){
        assert( is_string( $value ) );
        assert( isset( $arg ) );

        if( !is_string( $value ) && in_array( $value, array( 'read', 'secondary' ) ) ){
            throw new EWZ_Exception( "Invalid value for view" );
        }
        return true;
    }
            

    function v_rating( &$data, $arg ){
        assert( is_array( $data ) );
        assert( isset( $arg ) );

        // check input data is valid, and non-null if required
        foreach( $data as $rating_field_id => $val ){
            if ( !isset( $this->fields[$rating_field_id] ) ){
                throw new EWZ_Exception( "Invalid field"  );
            }

            if ( self::r_is_blank($val) && $this->fields[$rating_field_id]->required ) {
                throw new EWZ_Exception(  $this->fields[$rating_field_id]->field_header . " is a required field." );
            }

            switch ( $this->fields[$rating_field_id]->field_type ) {
            case 'str':
                    Ewz_Upload_Input::validate_str_data( $this->fields[$rating_field_id]->fdata, $data[$rating_field_id] );  // may change 2nd arg
                    // '~' and '|' are used as separators when generating the csv download
                    if( preg_match( '/~|\|/', $data[$rating_field_id] ) ){
                        $data[$rating_field_id] = preg_replace( '/~|\|/', '_', $data[$rating_field_id] );
                    }
                    break;                        
            case 'opt':
                    // cant validate the count limits here, so pass in 0 current count
                    Ewz_Upload_Input::validate_opt_data( $this->fields[$rating_field_id], $val, 0 );
                    break;
            /* case 'rad': */
            /*         $data[$rating_field_id] = Ewz_Upload_Input::validate_rad_data( $val, 0 );   */
            /*         break; */
            case 'chk':
                    $data[$rating_field_id] = Ewz_Upload_Input::validate_chk_data( $this->fields[$rating_field_id], $val, 0);
                    break;
            default:
                throw new EWZ_Exception( "Invalid field type " . $this->fields[$rating_field_id]->field_type );
            }
        }

        // restrictions are checked in ewz_rating_shortcode because non-input fields are needed
        // counts are checked only on page reload, because all items are needed

        return true;
    }

    /* same as entrywizard's is_blank in validation/ewz_input.php, to save requiring a new version */
    /* remove and use is_blank when required version has to be updated */
    function r_is_blank( $value ){
        assert( is_string( $value ) );
        if( !isset( $value ) ){
            return true;
        }
        if( $value === NULL || $value === '' ){
            return true;
        }
        return false;
    }
}