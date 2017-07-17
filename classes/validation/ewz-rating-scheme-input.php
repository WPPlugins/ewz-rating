<?php

defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/ewz-layout.php" );
require_once( EWZ_RATING_DIR . "classes/validation/ewz-rating-field-input.php" );
require_once( EWZ_PLUGIN_DIR . "classes/validation/ewz-input.php" );

/* Validation for the Admin Ratings page */

class Ewz_Rating_Scheme_Input extends Ewz_Input {

    function __construct( $form_data ) { 
        parent::__construct( $form_data );
        assert( is_array( $form_data ) );
        $customvars = array_keys( Ewz_Custom_Data::$data );
        $xcols = array_merge( array( 'att','aat','aae','aac', 'add','dlc', 'dtu', 'iid', 'wft',  'wid', 'wfm',
        'nam', 'fnm', 'lnm', 'mnm', 'mem', 'mid', 'mli' ),
                             $customvars );
        $setting_vals = array( 'maxw', 'maxh', 'bcol', 'fcol', 'img_pad', 'summary', 'finished', 'testimg', 'jhelp', 'bg_main', 'bg_curr', 'new_border' );
        
        $this->rules = array(
            'item_layout_id'   => array( 'type' => 'to_seq',         'req' => true, 'val' => '' ),
            'rating_scheme_id' => array( 'type' => 'to_seq',         'req' => false, 'val' => '' ),
            'fields'           => array( 'type' => 'v_fields',       'req' => true,  'val' => '' ),
            'forder'           => array( 'type' => 'v_forder',       'req' => true,  'val' => '' ),
            'scheme_name'      => array( 'type' => 'to_string',      'req' => true,  'val' => '' ),
            'restrictions'     => array( 'type' => 'v_restrictions', 'req' => false, 'val' => $form_data['fields'] ),
            'extra_cols'       => array( 'type' => 'v_extra_cols',   'req' => false, 'val' => $xcols ),
            'settings'         => array( 'type' => 'v_settings',     'req' => false, 'val' => $setting_vals ),
            'ewzmode'          => array( 'type' => 'fixed',          'req' => true,  'val' => 'ratingscheme' ),
            'ewznonce'         => array( 'type' => 'anonce',         'req' => true,  'val' => '' ),  
            '_wp_http_referer' => array( 'type' => 'to_string',      'req' => false, 'val' => '' ),
            'action'           => array( 'type' => 'fixed',          'req' => false, 'val' => 'ewz_scheme_changes' )
        );
        $this->validate();
    }

    function validate( ){
         parent::validate();

        // an unchecked checkbox does not create any matching value in $_POST
        if ( !array_key_exists( 'summary', $this->input_data['settings'] ) ) {
            $this->input_data['settings']['summary'] = false;
        }
        if ( !array_key_exists( 'finished', $this->input_data['settings'] ) ) {
            $this->input_data['settings']['finished'] = false;
        }
        if ( !array_key_exists( 'jhelp', $this->input_data['settings'] ) ) {
            $this->input_data['settings']['jhelp'] = false;
        }
    }

    //****** All v_.... functions must return true or raise an exception **************/

   function v_settings( &$value, $arg ){
        assert( is_array( $value ) );
        assert( is_array( $arg ) );
        if ( !is_array( $value ) ) {
            throw new EWZ_Exception( "Invalid format for settings" );
        }
        foreach ( array_keys( $value ) as $key ) {
            if ( !in_array( $key, $arg ) ) {
                throw new EWZ_Exception( "Invalid setting type '$key'" );
            }
        }
        foreach ( $value as $key => $val ) {
            switch( $key ){
            case 'maxw':
            case 'maxh':
                if ( !( is_string( $value[$key] ) &&
                preg_match( '/^\-?\d+$/', $value[$key] ) &&
                ( int ) $value[$key] >= -1 &&
                ( int ) $value[$key] < 10000 ) ) {
                    throw new EWZ_Exception( 'Invalid value for ' . $value[$key] );
                }
                $value[$key] = (int)$value[$key];
                break;

            case 'img_pad': 
                if ( !( is_string( $value[$key] ) &&
                preg_match( '/^\-?\d+$/', $value[$key] ) &&
                ( int ) $value[$key] >= -1 &&
                ( int ) $value[$key] < 1000 ) ) {
                    throw new EWZ_Exception( 'Invalid value for ' . $value[$key] );
                }
                $value[$key] = (int)$value[$key];
                break;
                
            case 'bcol':
            case 'fcol':
            case 'bg_main':
            case 'bg_curr':
            case 'new_border':
                if ( !( preg_match( '/^#[A-F0-9]{6}/i', $value[$key] ) ) ){
                     throw new EWZ_Exception( 'Invalid value for ' . $value[$key] );
                }
                break;

            case 'summary':
            case 'finished':
            case 'jhelp':
                if( !self::to_bool( $value[$key] , '' ) ){              // changes  to boolean
                    throw new EWZ_Exception( "Invalid value $val for checkbox input" );
                }
                break;
            case 'testimg': 
                if ( !( is_string( $value[$key] ) ) ) {
                    throw new EWZ_Exception( "Invalid value for test image input" );
                }
                if( !empty( $value[$key] ) ){
                    if( validate_file( $value[$key] ) != 0 ){
                        throw new EWZ_Exception( "Invalid  file path for test image" );
                    } 
                    if ( !preg_match( '/(\.jpg|\.gif|\.png)$/', $value[$key] ) ) {
                        throw new EWZ_Exception( "Invalid image file format for test image" );
                    }
                    $f = wp_upload_dir()['basedir'] . '/'. $value[$key];
                    if( !is_file( "$f" ) ){
                        throw new EWZ_Exception( "No such file $f for test-image input" );
                    } 
                }
                break;                
            default: throw new EWZ_Exception( "Invalid value $val for $key" );
            }
        }
       return true;
   }


    function v_extra_cols( &$value, $arg ) {
        assert( is_array( $value ) );
        assert( is_array( $arg ) );

        if ( !is_array( $value ) ) {
            throw new EWZ_Exception( "Invalid format for extra columns" );
        }
        foreach ( array_keys( $value ) as $key ) {
            if ( !in_array( $key, $arg ) ) {
                throw new EWZ_Exception( "Invalid spreadsheet column type '$key'" );
            }
        }
        $used_cols = array();
        foreach ( $arg as $key ) {
            if ( isset( $value[$key] ) ) {
                if ( !( is_string( $value[$key] ) &&
                        preg_match( '/^\-?\d+$/', $value[$key] ) &&
                        ( int ) $value[$key] >= -1 &&
                        ( int ) $value[$key] <= 1000 ) ) {
                    throw new EWZ_Exception( 'Invalid spreadsheet column ' . $value[$key] );
                }
                $value[$key] = (int)$value[$key];

                // make sure each ss column ( except -1 )  only assigned once
                if( $value[$key] >= 0  && isset( $used_cols[$value[$key]] ) ){
                    throw new EWZ_Exception( 'Spreadsheet column used twice: ' . ( $value[$key] + 1 ) );
                } 
            }
            $used_cols[$value[$key]] = 1;
        }
        return true;
    }

    function v_forder( $value, $arg ) {
        assert( is_array( $value ) );
        assert( isset( $arg ) );
        if ( is_array( $value ) ) {
            foreach ( $value as $key => $nm ) {
                if ( !preg_match( '/^\d+$/', $key ) ) {
                    throw new EWZ_Exception( "Invalid key $key" );
                }
                if ( !( is_string( $nm ) &&
                        preg_match( '/^forder_fX?(\d)+_cX?(\d)+/', $nm ) ) ) {
                    throw new EWZ_Exception( "Invalid field sort order $key for $nm" );
                }
            }
        } else {
            throw new EWZ_Exception( "Bad input data for 'forder'" );
        }
        return true;
    }

    function v_fields( &$value, $arg ) {
        assert( is_array( $value ) );
        assert( isset( $arg ) );
        if ( is_array( $value ) ) {
            if( count( $value ) == 0 ){
                throw new EWZ_Exception( 'A Layout must have at least one field' );
            }    
            foreach ( $value as $key => $fld ) {

                $f = new Ewz_Rating_Field_Input( $fld );
                $value[$key] = $f->get_input_data();  // changing, cant use $fld on left
            }
        } else {
            throw new EWZ_Exception( 'Invalid value for field array' );
        }
        return true;
    }

    function v_restrictions( &$restrictions, $arg ) {
        assert( is_array( $restrictions ) );
        assert( is_array( $arg ) );
        // arg is $_FORM
        foreach ( $restrictions as &$restr ) {
            // format of restriction is array( field_id1 => array( allowed values ),
            //                                 ....
            //                                 field_idN => array( allowed values ),
            //                                 'msg' => message )
            foreach ( $restr as $key => $val_arr ) {
                if ( $key == 'msg' ) {
                    if ( $val_arr ) {
                        if ( !self::to_string( $restr[$key], '' ) ) {      // also html_entity_decodes the string
                            throw new EWZ_Exception( 'Invalid message for restriction' );
                        }
                    } else {
                        throw new EWZ_Exception( 'Missing message for restriction' );
                    }
                } else {   // $val_arr  is an array
                    foreach( $val_arr as $value ){
                        if( isset( $arg[$key]['is_second'] ) && $arg[$key]['is_second']  && ( $value != '~*~' ) ){
                            throw new EWZ_Exception( '"' . $arg[$key]['field_header'] . '"' .
                               ' has been designated as a "secondary" field, and thus may not appear in a restriction.' );
                        }
                        $okval = in_array( "$value", array( '~*~', '~-~', '~+~' ) ) ? 1 : 0; 
                        foreach ( $arg as $field_id => $field ) {

                            if ( $key == $field_id ) {
                                // an 'opt' type
                                if ( !$okval && isset( $field['fdata']['options'] ) ) {
                                    foreach ( $field['fdata']['options'] as $options ) {
                                        if ( $value == $options['value'] ) {
                                            $okval = 1;
                                        }
                                    }
                                }
                                // a 'fix' type 
                                if ( !$okval && isset( $field['fdata']['field_id'] ) ){
                                    $f = new Ewz_Field( $field['fdata']['field_id'] );
                                    if( isset( $f->fdata['options'] ) ){
                                        foreach ( $f->fdata['options'] as $options ) {
                                            if ( $value == $options['label'] ) {
                                                $okval = 1;
                                            }
                                        }
                                    }
                                    if( isset( $f->fdata['chklabel'] ) && ( $value ==  $f->fdata['chklabel'] )) {
                                        $okval = 1;
                                    }
                                    if( isset( $f->fdata['xchklabel'] ) && ( $value ==  $f->fdata['xchklabel'] )){
                                        $okval = 1;
                                    }
                                    if( isset( $f->fdata['radlabel'] ) && ( $value ==  $f->fdata['radlabel'] )){
                                        $okval = 1;
                                    }
                                    if( isset( $f->fdata['xradlabel'] ) && ( $value ==  $f->fdata['xradlabel'] )){
                                        $okval = 1;
                                    }
                               }
                                // an 'xtr' type 
                                if ( !$okval && isset( $field['fdata']['dkey'] ) ){
                                    $cust_arr = Ewz_Custom_Data::selection_list( $field['fdata']['dkey'] );
                                    if ( in_array($value, $cust_arr ) ) {
                                        $okval = 1;
                                    }
                                }
       
                            }
                        }      
                    } 
                    if ( !$okval ) {
                        throw new EWZ_Exception( "Invalid value for restriction on field: " . $key. ": ". print_r( $val_arr, true )  );
                    }
                }
            }
        }
        unset($restr);
        $restrictions = array_values($restrictions);   // otherwise if keys not 0,1,... js reads it as an object instead of array
                                                       // this can happen if a restriction is deleted.        
        return true;
    }

}
