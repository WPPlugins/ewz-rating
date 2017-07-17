<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/ewz-layout.php");
require_once( EWZ_PLUGIN_DIR . "classes/validation/ewz-input.php");

/* Validation for the Rating_Scheme page */

class Ewz_Rating_Field_Input extends Ewz_Input
{

    function __construct( $form_data ) {
        parent::__construct( $form_data );
        assert( is_array( $form_data ) );
        $this->rules = array(
                             'rating_field_id'=> array( 'type' => 'v_rfield_id', 'req' => false,  'val' => '' ),
                             'field_header'   => array( 'type' => 'to_string',   'req' => true,  'val' => '' ),
                             'field_type'     => array( 'type' => 'limited',     'req' => true,  'val' => array( 'str', 'opt', 'rad', 'chk', 'fix', 'xtr','lab' ) ),
                             'field_ident'    => array( 'type' => 'ident',       'req' => true,  'val' => '' ),
                             'fdata'          => array( 'type' => 'v_fdata',     'req' => true,  'val' => '' ),
                             'ss_column'      => array( 'type' => 'to_int1',     'req' => false, 'val' => '' ),
                             'required'       => array( 'type' => 'to_bool',     'req' => false, 'val' => '' ),
                             'append'         => array( 'type' => 'to_bool',     'req' => false, 'val' => '' ),
                             'divide'         => array( 'type' => 'to_bool',     'req' => false, 'val' => '' ),
                             'is_second'      => array( 'type' => 'to_bool',     'req' => false, 'val' => '' ),
                             );
        $this->validate();
    }

    function validate(){
        parent::validate();

        if ( !array_key_exists( 'required', $this->input_data ) ) {
            $this->input_data['required'] = false;
        }
        if ( !array_key_exists( 'append', $this->input_data ) ) {
            $this->input_data['append'] = false;
        }
        if ( !array_key_exists( 'divide', $this->input_data ) ) {
            $this->input_data['divide'] = false;
        }
        if ( !array_key_exists( 'is_second', $this->input_data ) ) {
            $this->input_data['is_second'] = false;
        }
        return true;
   }

    function v_rfield_id( $value, $arg ){
        assert( is_string( $value ) );
        assert( isset( $arg ) );
        if( !preg_match('/^X?[0-9]+$/', $value ) ){
             throw new EWZ_Exception( "Invalid rating field id $value" );
        }
        return true;
    }

    //****** All v_.... functions must return a boolean or raise an exception **************/
    function v_fdata( &$fdata ){
        assert( is_array( $fdata ) );

        switch ( $this->input_data['field_type'] ) {
        case 'opt':
            self::valid_opt_input( $fdata );
            break;
        case 'str':
            self::valid_str_input( $fdata );
            break;
        case 'lab':
            self::valid_lab_input( $fdata );
            break;
        case 'fix':
            self::valid_fix_input( $fdata );
            break;
        case 'xtr':
            self::valid_xtra_input( $fdata );
            break;
        case 'rad':
            break;
        case 'chk':
            self::valid_chk_input( $fdata );
            break;
        default:   throw new EWZ_Exception( 'Invalid value for field type: ' . $this->input_data['field_type'] );
        }

        return true;
    }

   /**
     * Validate checkbox field input
     *
     * @param  array  $field     input checkbox field to check
     * @return boolean  true or raise an exception
     */
    private static function valid_chk_input( &$field )
    {
        assert( is_array( $field ) );
        $all_field_data = array('chkmax', 'chklabel', 'xchklabel');
        foreach ( $field as $name => $val ) {
            if( !is_string( $val ) ){
                 throw new EWZ_Exception( "Bad input data format for $name ");
            }
            if ( !in_array( $name, $all_field_data ) ) {
                throw new EWZ_Exception( "Bad input data for $name ");
            }

            if ( 'chkmax' == $name ) {
                if ( !preg_match( '/^\d*$/', $val ) ) {
                    throw new EWZ_Exception( "Invalid value for $name: '$val'" );
                }
                if( !$val ){
                    $field[$name] = 0;  // changing, cant use $val on left
                }
                $field[$name] = (int)$val;      // changing, cant use $val on left
            }
            if ( in_array(  $name, array( 'chklabel', 'xchklabel' ) ) ){
                if ( preg_match( '/[^A-Za-z0-9_\.\- ]/', $val ) ) {
                    throw new EWZ_Exception( "Invalid value for checkbox label" );
                }
            }
        }
        return true;
    }

    /**
     * Validate string field input
     *
     * @param  array  $field     input string field to check
     * @return boolean  true if no issues
     */
    private static function valid_str_input( &$field )
    {
        assert( is_array( $field ) );

        $req_field_data = array('maxstringchars');
        $all_field_data = array('maxstringchars', 'fieldwidth', 'ss_col_fmt', 'textrows');
        foreach ( $req_field_data as $req ) {
            if ( !array_key_exists( $req, $field )|| preg_match( '/^ *$/', $field[$req] ) ) {
                throw new EWZ_Exception( "Missing required item $req for a text input" );
            }
        }
        foreach ( $field as $name => $val ) {
            if( !is_string( $val ) ){
                 throw new EWZ_Exception( "Bad input data format for $name ");
            }
            if ( !in_array( $name, $all_field_data ) ) {
                throw new EWZ_Exception( "Bad input data for $name ");
            }

            if ( 'maxstringchars' == $name ) {
                if ( !preg_match( '/^\d*$/', $val ) ) {
                    throw new EWZ_Exception( "Invalid value for $name: '$val'" );
                }
                if( !$val ){
                    $field[$name] = EWZ_MAX_STRING_LEN;  // changing, cant use $val on left
                }
                $field[$name] = (int)$val;      // changing, cant use $val on left

            }
            if ( ( 'fieldwidth' == $name ) ) {
                if ( !preg_match( '/^\d*$/', $val ) ) {
                    throw new EWZ_Exception( "Invalid value for $name: '$val'" );
                }
                if( !$val ){
                    $field[$name] = EWZ_MAX_FIELD_WIDTH;  // changing, cant use $val on left
                }
                $field[$name] = ( int )$val;   // changing, cant use $val
            }
            if ( 'ss_col_fmt' == $name ){
                if ( !preg_match( '/^-1$|^\d*$/', $val ) ) {
                    throw new EWZ_Exception( "Invalid value for $name: '$val'" );
                }
                $field[$name] = ( int )$val;    // changing, cant use $val on left
            }
        }
        return true;
    }


    /**
     * Validate label field input
     *
     * @param  array  $field     input label field to check
     * @return boolean   true if no issues
     */
    private static function valid_lab_input( &$field )
    {
        assert( is_array( $field ) );

        $req_field_data = array('label');
        $all_field_data = array('label');
        foreach ( $req_field_data as $req ) {
            if ( !array_key_exists( $req, $field )|| preg_match( '/^ *$/', $field[$req] ) ) {
                throw new EWZ_Exception( "Missing required item $req for a text input" );
            }
        }
        foreach ( $field as $name => $val ) {
            if( !is_string( $val ) ){
                 throw new EWZ_Exception( "Bad input data format for $name ");
            }
            if ( !in_array( $name, $all_field_data ) ) {
                throw new EWZ_Exception( "Bad input data for $name ");
            }

            if ( 'label' == $name ) {
                if( !self::to_string( $val, '' ) ){               // also html_entity_decodes the string
                    throw new EWZ_Exception( "Invalid format for a label field." );
                }
                if( strlen( $val ) > 50 ){
                    throw new EWZ_Exception( "Label is too long." );
                }
                    
                $val = str_replace('\\', '', $val );  // messes up stripslashes and serialize. Its hard
                                                      // to see a legit use for this, so easier to just remove
                $field[$name] = $val;     
            }
        }
        return true;
    }


    /**
     * Validate "fix" field input -- a read-only string from an Ewz_Field
     *
     * @param  array  $field  input fix field to check
     */
    private static function valid_fix_input( &$field )
    {
        assert( is_array( $field ) );

        $req_field_data = array( 'field_id' );
        $all_field_data = array( 'field_id' );

        foreach ( $req_field_data as $req ) {
            if( is_string( $field[$req] ) ){
                if ( !array_key_exists( $req, $field ) || preg_match( '/^ *$/', $field[$req] ) ) {
                    throw new EWZ_Exception( "Missing required item $req " );
                }
            } else {
                if ( !array_key_exists( $req, $field ) || ( count( $field[$req] ) == 0 ) ) {
                    throw new EWZ_Exception( "Missing required item $req " );
                }
            }      
        }
        foreach ( $field as $name => $val ) {
            if ( !in_array( $name, $all_field_data ) )
                {
                    throw new EWZ_Exception( "Bad input data '$name' for image field");
                }
            if ( $name == 'field_id'){
                if( !is_string( $val ) ){
                    throw new EWZ_Exception( "Bad input data format for $name ");
                }
                if ( isset( $val ) && !preg_match( '/^\d+$/', $val ) ) {
                    throw new EWZ_Exception( "Invalid value for $name: '$val'" );
                }
                $field[$name] = ( int ) $val;
                
            } 
         }
        return true;
    }

    /**
     * Validate "xtr" field input -- a read-only string defining an Extra Data item
     *
     * @param  array  $field  input xtr field to check
     */
    private static function valid_xtra_input( &$field )
    {
        assert( is_array( $field ) );

        $req_field_data = array( 'dobject', 'dvalue', 'origin', 'dkey' );
        $all_field_data = array( 'dobject', 'dvalue', 'origin', 'dkey' );

        foreach ( $req_field_data as $req ) {
            if( is_string( $field[$req] ) ){
                if ( !array_key_exists( $req, $field ) || preg_match( '/^ *$/', $field[$req] ) ) {
                    throw new EWZ_Exception( "Missing required item $req " );
                }
            } else {
                if ( !array_key_exists( $req, $field ) || ( count( $field[$req] ) == 0 ) ) {
                    throw new EWZ_Exception( "Missing required item $req " );
                }
            }      
        }
        foreach ( $field as $name => $val ) {
            if ( !in_array( $name, $all_field_data ) ) {
                throw new EWZ_Exception( "Bad input data '$name' for extra data field");
            }
            if( !is_string( $val ) ){
                throw new EWZ_Exception( "Bad input data format for $name ");
            }
            $field[$name] = $val;
         }
        return true;
    }
    /**
     * Validate option-list field input
     *
     * @param  array  $field  input option field to check
     * @return string $bad_data  comma-separated list of bad data
     */
    private static function valid_opt_input( &$fdata )
    {
        assert( is_array( $fdata ) );

        $req_field_data = array('label', 'value', 'maxnum');
        foreach ( $req_field_data as $req ) {
            foreach ( $fdata['options'] as $key => $val ) {
                if ( !array_key_exists( $req, $val ) ||  preg_match( '/^ *$/', $val[$req] )  ) {
                    throw new EWZ_Exception( "Missing required item $req for option list");
                }
            }
        }
        foreach ( $fdata as $name => $val ) {
            if ( $name != 'options' ) {
                throw new EWZ_Exception( "Bad input data $name" );
            }
            if( 'options' == $name ){
               foreach ( $val as $key => $optarr ) {
                   // key is label, value or maxnum
                   foreach ( $optarr as $optkey => $optval ) {
                       if( !is_string( $optval ) ){
                           throw new EWZ_Exception( "Bad input data format for $optkey ");
                       }
                       switch ( $optkey ) {
                           case 'maxnum':
                               if ( !preg_match( '/^\d*$/', $optval ) ) {
                                   throw new EWZ_Exception( "Invalid value for field option $optkey : $optval");
                               }
                               $fdata[$name][$key][$optkey] = ( int )$optval;  // changing, cant use optval
                               break;
                           case 'value':
                               if ( preg_match( '/[^A-Za-z0-9_\.\-]/', $optval ) ) {
                                   throw new EWZ_Exception( "Invalid value for field option $optkey : $optval" );
                               }
                               break;
                           case 'label':
                               if ( preg_match( '/[^A-Za-z0-9_\.\- ]/', $optval ) ) {
                                   throw new EWZ_Exception( "Invalid value found for field option $optkey : $optval" );
                               }
                               break;
                           default:
                                   throw new EWZ_Exception( "Invalid value for field option $optkey : $optval" );
                       }
                   }
               }
            }
        }
        return true;
    }

}
