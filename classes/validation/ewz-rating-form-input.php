<?php

defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_RATING_DIR . "classes/ewz-rating-scheme.php" );
require_once( EWZ_RATING_DIR . "classes/validation/ewz-rating-field-input.php" );
require_once( EWZ_PLUGIN_DIR . "classes/validation/ewz-input.php" );

/* Validation for the Admin Ratings page */

class Ewz_Rating_Form_Input extends Ewz_Input {

    function __construct( $form_data ) {
        parent::__construct( $form_data );
        assert( is_array( $form_data ) );
        
        $this->rules = array(
            'rating_form_id'    => array( 'type' => 'to_seq',      'req' => false, 'val' => '' ),
            'rating_form_title' => array( 'type' => 'to_string',   'req' => true,  'val' => '' ),
            'rating_form_ident' => array( 'type' => 'to_string',   'req' => true,  'val' => '' ),
            'rating_scheme_id'  => array( 'type' => 'to_seq',      'req' => false, 'val' => '' ),
            'page'              => array( 'type' => 'limited',     'req' => false, 'val' => array('ewzratingformadmin')),
            'webform_ids'       => array( 'type' => 'v_wfids',     'req' => true,  'val' => '' ),
            'own'               => array( 'type' => 'to_bool',     'req' => false, 'val' => '' ),
            'fopt'              => array( 'type' => 'v_fopts',     'req' => false, 'val' => '' ),
            'ewzmode'           => array( 'type' => 'limited',     'req' => true,  'val' => array('ratingform' )),
            'ewznonce'          => array( 'type' => 'anonce',      'req' => true,  'val' => '' ),
            '_wp_http_referer'  => array( 'type' => 'to_string',   'req' => false, 'val' => '' ),
            'rating_open'       => array( 'type' => 'to_bool',     'req' => false, 'val' => '' ),
            'shuffle'           => array( 'type' => 'to_bool',     'req' => false, 'val' => '' ),
            'judges'            => array( 'type' => 'v_users',     'req' => false, 'val' => '' ),
            'ss_style'          => array( 'type' => 'limited',     'req' => false, 'val' => array('R','I' ) ),
        );

        $this->validate();
    }

   function validate(){

        parent::validate();

        if ( !array_key_exists( 'rating_open', $this->input_data ) ) {
            $this->input_data['rating_open'] = 0;    // own column, use integer 1,0
        }
        if ( !array_key_exists( 'shuffle', $this->input_data ) ) {
            $this->input_data['shuffle'] = 0;        // own column, use integer 1,0
        }
        if ( !array_key_exists( 'own', $this->input_data ) ) {
            $this->input_data['own'] = false;        // stored serialized in item_selection as boolean
        }

        if ( !array_key_exists( 'fopt', $this->input_data ) ) {
            $this->input_data['fopt'] = array();
        }
        if ( !array_key_exists( 'copt', $this->input_data ) ) {
            $this->input_data['copt'] = array();
        }
        return true;
   }

    //****** All v_.... functions must return true or raise an exception **************/


   function v_users( $value, $arg ){
         assert( is_array( $value ) || empty( $value ) );
         assert( $arg == '' );
         if( !is_array( $value ) ){
             throw new EWZ_Exception( 'Bad input for user' );
         }
         foreach( $value as $key => $uid ){
             if( !self::to_int1( $value[$key], $arg ) ){   // to_int1 potentially changes first arg
                 throw new EWZ_Exception( "Bad value '$uid' for user" );
             }
         }
         return true;
     }

    // selected values for fields
     static function v_fopts( $value, $arg ){
         assert( is_array( $value ) || empty( $value ) );
         assert( isset( $arg ) );
         if( !is_array( $value ) ){
             throw new EWZ_Exception( 'Invalid fopt value' );
         }
         if( count( $value ) > 50 ){
             throw new EWZ_Exception( 'Invalid fopt value' );
         }
         foreach( $value as $key => $val ){
             if( !preg_match( self::REGEX_SEQ, $key ) && !preg_match('/^custom[0-9]+/', $key  ) ){
                 throw new EWZ_Exception( "Invalid key for field option: '$key' " );
             }  
             if( is_array( $val ) ){
                 foreach( $val as $n => $valn ){
                     if ( !( is_string( $valn ) &&
                     preg_match( '/^[_a-zA-Z0-9\-\~\+\*\-]*$/', $valn ) ) ){
                         throw new EWZ_Exception( "Invalid value for field option: '$val' ");
                     }
                 }
             }
             elseif ( !( is_string( $val ) &&
                     preg_match( '/^[_a-zA-Z0-9\-\~\+\*\-]*$/', $val ) ) ){
                 throw new EWZ_Exception( "Invalid value for field option: '$val' ");
             }
         }  
         return true;
     }

     static function v_wfids( $value, $arg ){
         assert( is_array( $value ) || empty( $value ) );
         assert( isset( $arg ) );
         if( !is_array( $value ) ){
             throw new EWZ_Exception( 'Invalid  value for webform ids' );
         }
         if( count( $value ) > 50 ){
             throw new EWZ_Exception( 'Too many webforms' );
         }
          if( count( $value ) < 1 ){
             throw new EWZ_Exception( 'You must select at least one webform' );
         }        
         foreach( $value as $key => $wfid ){
             if( !self::to_seq( $value[$key], $arg ) ){   // seq potentially changes first arg
                 throw new EWZ_Exception( "Bad value '$wfid' for webform id" );
             }
         }
         return true;
     }
}