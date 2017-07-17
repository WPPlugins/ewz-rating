<?php
require_once( EWZ_RATING_DIR . "classes/ewz-rating-form.php");
require_once( EWZ_PLUGIN_DIR . "classes/validation/ewz-input.php");

/* Validation for the forms on the Rating Forms page */

class Ewz_Rating_Form_Set_Input extends Ewz_Input
{

     function __construct( $form_data ) {
         parent::__construct( $form_data );
         assert( is_array( $form_data ) );
       
         $this->rules = array(

                  '_wp_http_referer' => array( 'type' => 'to_string', 'req' => false, 'val' => '' ),
                  'ewzmode'        =>  array( 'type' => 'fixed',      'req' => true,  'val' => 'rf_set' ),
                  'action'         => array( 'type' => 'fixed',       'req' => true, 'val' => 'ewz_save_rating_form_order' ),
                  'ewznonce'       =>  array( 'type' => 'anonce',     'req' => true,  'val' => '' ),
                  'rforder'        =>  array( 'type' => 'v_order',   'req' => true,  'val' => '' ),
                  );
        $this->validate();
     }


    function v_order( $value, $arg ) {
        assert( is_array( $value ) );
        assert( isset( $arg ) );
        if ( is_array( $value ) ) {
            $seen = array();
            $count = count($value);
            foreach ( $value as $key => $nm ) {
                if ( !preg_match( '/^\d+$/', $key ) ) {
                    throw new EWZ_Exception( "Invalid key $key" );
                }
                if ( !preg_match( '/^\d+$/', $nm ) ) {
                    throw new EWZ_Exception( "Invalid order $nm" );
                }
                if( $nm < 0 || $nm >= $count ){
                    throw new EWZ_Exception( "Invalid value for order $nm" );
                } 
                if( isset($seen[$nm]) ){
                    throw new EWZ_Exception( "Duplicate value for order $nm" );
                }
                $seen[$nm] = true;
            }   
        } else {
            throw new EWZ_Exception( "Bad input data for rating form order" );
        }
        return true;
     }
}
