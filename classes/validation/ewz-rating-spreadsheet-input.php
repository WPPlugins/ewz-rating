<?php

defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/validation/ewz-input.php" );

/* Validation for the Admin Download Ratings Spreadsheet  */

class Ewz_Rating_Spreadsheet_Input extends Ewz_Input {

    function __construct( $form_data ) {
        parent::__construct( $form_data );
        assert( is_array( $form_data ) );
        
        $this->rules = array(
            'rating_form_id'    => array( 'type' => 'to_seq',      'req' => true, 'val' => '' ),
            'ss_style'          => array( 'type' => 'limited',     'req' => true, 'val' => array('I','R' ) ),
            'ewzmode'           => array( 'type' => 'fixed',       'req' => true,  'val' => 'rspread'),
            'ewznonce'          => array( 'type' => 'anonce',      'req' => true,  'val' => '' ),
            '_wp_http_referer'  => array( 'type' => 'to_string',   'req' => false, 'val' => '' ),
        );

        $this->validate();
    }
}

