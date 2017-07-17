<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly
/****************************/
/* Generate the rating form */
/****************************/
require_once( EWZ_PLUGIN_DIR . 'includes/ewz-common.php' );

/**
 * Return a string used to display a single field in read-only fashion
 *
 * For text fields, just return the text.  For drop-down items, return the option display label.
 * For image fields, display the thumbnail.
 *
 * @param  $args   array of named args
 *   user_id       int                 ID of current user ( judge )
 *   rating_form   Ewz_Rating_Form     The rating_form in which the field is to be displayed. 
 *   rating_field  Ewz_Rating_Field    The rating_field to be displayed
 *   item_id       integer             The item to be rated
 *   ratings       array of Ewz_Item_Ratings   The item ratings to be displayed
 *   rownum        integer             The row number of the item
 *   append_next   boolean             True if the next field is to be appended to this one
 *   atts          array               attributes for the shortcode
 *
 * @return string used to display the value
 **/
function ewz_display_rated_item_field( $args ){
    assert( is_array($args) );
    $user_id = $args['user_id']; 
    $rating_form = $args['rating_form'];
    $rating_field = $args['rating_field'];
    $item_id = $args['item_id'];
    $ratings = $args['ratings'];
    $rownum = $args['rownum'] ;
    $append_next = $args['append_next'];
    $atts = $args['atts'];

    assert( Ewz_Base::is_nn_int( $user_id ) );
    assert( Ewz_Base::is_nn_int( $item_id ) );
    assert( is_a( $rating_form, 'Ewz_Rating_Form' ) );
    assert( is_a( $rating_field, 'Ewz_Rating_Field' ) );
    assert( is_array( $ratings ) );
    assert( is_int( $rownum ) );
    assert( is_bool( $append_next ) );
    assert( is_array( $atts ) );

    // dont show a secondary field unless view is secondary
    if( $rating_field->is_second && $atts['view'] != 'secondary' ){
        return '';
    }
    $_rf_num = esc_attr( $atts['rf_num'] );
    $_value = '';
    $item = new Ewz_Item($item_id);
    // dont show if field is divided and judge not supposed to see it
    $show_to_judge = !$rating_field->divide || ( $user_id == $rating_form->show_judge[ $item_id ] );
    // then override that for the "read" view only
    if( $atts['view'] == 'read' ){
        $show_to_judge = true;
    }
    assert( !$rating_field->divide || ( in_array($rating_form->show_judge[$item_id], $rating_form->judges )));
    if( $show_to_judge ) {
        if( in_array( $rating_field->field_type, array( 'fix', 'xtr', 'lab' ) ) ){
            // read-only, only one value
            $rating = $ratings[0];
            $_value = ewz_safe_tags( Ewz_Item_Rating::rating_field_display( $rating_form, $rating_field, $item, $rating )[1] );
            if( preg_match( '/^http/', $_value ) ){ 
                $_value = '<img id="img' . esc_attr( "{$_rf_num}_{$rownum}_{$rating_field->rating_field_id}" ) . '" class="thumb" src="' . $_value . '"';
                $_value .= ' onClick="ewz_rating_window( ' . esc_attr( $atts['rf_num'] ) . ', ' . esc_attr( $rownum ) . ' )">';  
            }
        } else {
            // an input, may be from more than one judge if view is read
            $_value = ewz_display_input_field( array( 
                'rating_form'=>$rating_form,
                'atts'=>$atts,
                'ratings'=>$ratings,
                'rating_field' => $rating_field,
                'item' => $item,
                'rownum' => $rownum
            ));
            
            if( preg_match( '/^ *</', $_value ) ){
                // This is something that may be changed. To make sorting work,
                // when the value changes, the "ts_custom" attribute of the <td> must be reset
                // use onInput for text fields ( not supported in jQuery :( ), onChange for others
                if( $rating_field->field_type == 'str' ){
                    $_value =  preg_replace( '/>/', " onInput='ewz_ts_set(" . $_rf_num . ", this)'>", $_value, 1) ;
                } else {
                    $_value =  preg_replace( '/>/', " onChange='ewz_ts_set(" . $_rf_num . ", this)'>", $_value, 1) ;
                }
            }
        }
    }    
    assert( is_string( $_value ) || ( null == $_value  )|| ( '' == $_value  ) );
    
    // add an identifier so we can pick out appended items
    $field_val = "<span class='ewzval_" . esc_attr( $rating_field->rating_field_id ) . "'>" . $_value . "</span>";
    $tdstring = '';
    if( !$rating_field->append ){
        if( $show_to_judge ){
            $_uval = esc_attr( wp_strip_all_tags( Ewz_Item_Rating::rating_field_display( $rating_form, $rating_field, $item, $ratings[0] )[0] ) );
        } else {
            $_uval = '';
        }
        $tdstring = "<td id='cell{$_rf_num}_{$item_id}_". esc_attr( $rating_field->rating_field_id ) . "'  ts_custom='$_uval'>";
    }
    $tdstring .= $field_val;
    if( $append_next ){
        $tdstring .= "<br>";
    } else {
        $tdstring .= '</td>';
    } 
    return $tdstring;
}

/**
 * Return the html for the individual rating *page* for a single item
 * 
 * @param  $rating_fields    array of Ewz_Rating_Fields 
 * @param  $item_rating      the Ewz_Item_Rating to be filled out or displayed
 * @param  $item_row         integer --  position of item on page
 * @param  $rowcount         integer --  total number of items 
 *
 * @return 
 **/
function ewz_item_rating_page( $rating_fields, $item, $item_row, $rowcount, $atts ){
    assert( is_array( $rating_fields ) );
    assert( is_a( $item, 'Ewz_Item' ) );
    assert( is_int( $item_row ) );
    assert( is_int( $rowcount ) );
    assert( Ewz_Base::is_nn_int( $atts['rf_num'] ) );

    $_rf_num = esc_attr( $atts['rf_num'] );
    $_item_row = (int)$item_row;

    // add display: none here so it does not try to display before the css gets loaded.
    $output = '<div class="ewz_rating_page_' . esc_attr( $item->item_id ) . '" style="display: none;">';
    // the content of this div gets shown in the popup window when the thumbnail is clicked

    $output .=   '<div class="ewz_win">';
    foreach ( $rating_fields as $n => $rating_field ) {
        
        if( $rating_field->field_type == 'fix' ){ 
            // a read-only data field
            $item_field_id = $rating_field->fdata['field_id'];

            if ( isset( $item->item_files[$item_field_id]['fname'] ) ) {
                $output .= '<img  onContextMenu="return false;" ';
                $output .=        ' src="' . esc_attr( ewz_file_to_url( $item->item_files[$item_field_id]['fname'] ) ) . '"';
                $output .= '>';
            }
        }        
    }
    $_ident = esc_attr( $_rf_num . $item->item_id );
    $output .=    '</div>';  // ewz_win
    $output .=    '<div style="position:fixed;bottom:0;">';
    $output .=       '<table class="buttonrow">';
    $output .=          "<tr><td>";
    $output .=          "<button class='prevbutton' id='prevbutton" . $_ident . "' onClick='opener.ewz_rating_window(" . $_rf_num .", " . ($_item_row - 1) . ")'>&lt;-- Previous</button>";
    $output .=          "</td></tr>"; 
    $output .=          "<tr><td>";
    $output .=          "<button class='nextbutton' id='nextbutton" . $_ident . "' onClick='opener.ewz_rating_window(" . $_rf_num .", " . ($_item_row + 1) . ")'> &nbsp; &nbsp; Next --&gt;</button>";
    $output .=          "</td></tr>"; 
    $output .=          "<tr></tr>";
    $output .=        '</table>';
    $output .=    '</div>'; 
    $output .=    "<button class='testbutton' id='testbutton" . $_ident . "' onClick='opener.ewz_show_image(" . $_rf_num .", -1, $_item_row )'>Display A Test Image</button>";
    $output .= '</div>';
    return $output;
}


/**
 * Return the html for displaying a single input field
 *
 * @param  $args   array of named args
 *   append_next   boolean             True if the next field is to be appended to this one
 *   atts          array               attributes for the shortcode
 *   item          Ewz_Item            The item to be rated
 *   rating_field  Ewz_Rating_Field    The rating_field to be displayed
 *   rating_form   Ewz_Rating_Form     The rating_form in which the field is to be displayed. 
 *   ratings       array of Ewz_Item_Ratings   The item ratings to be displayed
 *   rownum        integer             The row number of the item
 *
 * @return  string  $display   -- the html
 **/
function ewz_display_input_field( $args )
{
    assert( is_array($args) );
    $atts   = $args['atts'];
    $item   = $args['item'];
    $rating_field = $args['rating_field'];
    $rating_form = $args['rating_form'];
    $ratings =$args['ratings'];
    $rownum = $args['rownum'];

    assert( is_int($rownum) ); 
    assert( is_a( $item, 'Ewz_Item') ); 
    assert( is_a( $rating_form, 'Ewz_Rating_Form') ); 
    assert( is_string( $atts['view'] ) );
    assert( is_array( $ratings ) );
    assert( in_array( $rating_field->field_type, array( 'str', 'opt', 'rad', 'chk' ) ) );
   
    $_display = '';
    switch( $atts['view'] ){
    case 'read':
        // read-only, may be multiple values
        $_display = ewz_safe_tags( ewz_display_multiple( $ratings, $rating_form, $rating_field, $item ) );
        break;
    case 'secondary': 
        $rating = $ratings[0];
        if( $rating_field->is_second ) {
            $cleanval = esc_attr( wp_strip_all_tags( Ewz_Item_Rating::rating_field_display( $rating_form, $rating_field, $item, $rating )[1] ) );
            $_display = ewz_input_display( $rating_field, $atts, $rating,  $cleanval, false );
        } else {
            $_display = Ewz_Item_Rating::rating_field_display( $rating_form, $rating_field, $item, $rating )[1];
        }
        break;
    case 'rate':
        if( !$rating_field->is_second ) {
            $rating = $ratings[0];
            $cleanval = esc_attr( wp_strip_all_tags( Ewz_Item_Rating::rating_field_display( $rating_form, $rating_field, $item, $rating )[0] ) );
            $_display = ewz_input_display( $rating_field, $atts, $rating,  $cleanval, false );  
        }
        break;
    default: 
        throw new EWZ_Exception( "Invalid view value ",  $atts['view'] );
    }   
    return $_display;   
}    
    
function ewz_display_multiple( $ratings, $rating_form, $rating_field, $item ){
    assert( is_array($ratings) );
    assert( is_a( $rating_form, 'Ewz_Rating_Form') );
    assert( is_a( $rating_field, 'Ewz_Rating_Field') );
    assert( is_a( $item, 'Ewz_Item') );
    $display = '';
    foreach( $ratings as $rating){
        $val = Ewz_Item_Rating::rating_field_display( $rating_form, $rating_field, $item, $rating )[1];

        if( $display ){
            $display .= '<br>';
        }
        $display .= ( $rating->judge_id . ':&nbsp;' . $val );
    }
    return $display;
}

/**
 * Call the EntryWizard display_...._form_field functions, accorting to the rating _field type
 *
 * @param Ewz_Rating_Field   $rating_field    the Ewz_Rating_Field to display
 * @param array              $rating          the Ewz_Item_Rating to be displayed
 * @param string             $savedval        data currently stored for the field
 * @param boolean            $fixed           -- true if an option may not be changed ( because it is used in a restriction )
 **/
 // a parameter webform_id is required for these functions ( in ewz_common.php )
 // to allow for multiple webforms appearing on a single page.  It is only used to generate an id.     
 // Using functions from the upload code. If img or radio items added, will need to fix the
 // fact that those functions require $rating_field to have a "field_id"
 
function ewz_input_display( $rating_field, $atts, $rating,  $savedval, $fixed ){
    assert( is_a( $rating_field,  'Ewz_Rating_Field' ) );
    assert( is_array( $atts ) );
    assert( is_a( $rating, 'Ewz_Item_Rating' ) );
    assert( is_string( $savedval ) );
    assert( is_bool( $fixed ) );
    assert( in_array( $rating_field->field_type, array( 'str', 'opt', 'rad', 'chk' ) ) );

    $_id    = esc_attr( "rating". $atts['rf_num'] . "_" . $rating->item_id . '_' . $rating_field->rating_field_id . "_" );
    $_name  = esc_attr( "rating[" . $rating_field->rating_field_id . "]" );
    $_savedval = ewz_safe_tags( "$savedval" );
    switch ( $rating_field->field_type ) {
        case 'str': $display = ewz_display_str_form_field( $_name, $_id, $_savedval, $rating_field );
                break;
    case 'opt': $display = ewz_display_opt_form_field( $_name, $_id, $_savedval, $rating_field, (bool)$fixed );
                break;
        /* case 'rad': $display = ewz_display_rad_form_field( $_name, $_id, $_savedval, $rating_field ); */
        /*         break; */
    case 'chk': // savedval is '-' for display of "unchecked" 
                $display = ewz_display_chk_form_field( $_name, $_id, str_replace( '-', '', $_savedval) );
                break;
        default:
            throw new EWZ_Exception( "Invalid field type " . $rating_field->field_type );
        }
    return $display;
}


/**
 * Return the html for a table row displaying the item with entered scores
 *
 * @param  $args   array of named args
 *   atts          array               attributes for the shortcode
 *   item_id       integer             id of item to be rated
 *   rating_fields array of Ewz_Rating_Field    The rating_fields to be displayed
 *   rating_form   Ewz_Rating_Form     The rating_form in which the field is to be displayed. 
 *   ratings       array of Ewz_Item_Ratings   The item ratings to be displayed
 *   rowcount      integer             Total number of rows
 *   rownum        integer             The row number of the item
 *   user_id       integer             id of judge
 *       
 * @return  html for the table row
 **/
function ewz_rated_item_row( $args ){
    assert( is_array($args) );
    $atts = $args['atts'];
    $item_id = $args['item_id'];
    $rating_fields = $args['rating_fields'];
    $rating_form = $args['rating_form'];
    $ratings = $args['ratings'];
    $rowcount = $args['rowcount'] ;
    $rownum = $args['rownum'] ;
    $user_id = $args['user_id']; 

    assert( Ewz_Base::is_nn_int( $user_id ) );
    assert( is_a( $rating_form, 'Ewz_Rating_Form' ) );
    assert( is_array( $rating_fields ) );
    assert( is_array( $ratings) );
    assert( is_int( $rownum ) );
    assert( is_int( $rowcount ) );
    assert( is_array( $atts ) );
    assert( Ewz_Base::is_nn_int( $atts['rf_num'] ) );

    $_attr = ( $atts['view'] == 'read' || $ratings[0]->complete ) ? '' : " class='ewz_new'";
    $_rid = esc_attr( $atts['rf_num'] . "_$item_id" );
           
    $output ="<tr id='row$_rid' $_attr>";
    foreach ( $rating_fields as $n => $rating_field ) {
        $next_displayed = next_displayed_field( $atts['view'], $rating_fields, $n );
        if( $next_displayed ){
            $append_next = $next_displayed->append ;
        } else {
            $append_next = false;
        }
        $output .= ewz_display_rated_item_field( array( 'user_id' => $user_id, 
                                                  'rating_form' => $rating_form, 
                                                  'rating_field' => $rating_field, 
                                                  'item_id' => $item_id, 
                                                  'ratings' => $ratings, 
                                                  'rownum' => $rownum, 
                                                  'append_next' => $append_next,
                                                  'atts' => $atts ) );
    }

    // last column contains hidden div for the image page, so is always there
    $output .= '<td>';
    if( $atts['view'] != "read" &&
        count( array_filter( $rating_fields, function($v) { return !in_array( $v->field_type, array( 'fix','xtr', 'lab') );}  ) ) > 0 ){
        $item_rating = $ratings[0];
        $disable_clear = $item_rating->item_rating_id ? '' : ' disabled="disabled"';
        $_rfnum = esc_attr( $atts['rf_num'] );
        $_item_id =  esc_attr( $item_rating->item_id );
        $nonce_field = wp_nonce_field( 'ewzrating', 'ewzratingnonce', true, false );
        $output .= str_replace( 'id="ewzratingnonce"', 'id="ewzratingnonce'  . $_rfnum . '_' . $_item_id . '"', $nonce_field );
        $output .= '<input type="hidden" name="item_rating_id" value="' .  esc_attr( $item_rating->item_rating_id ) . '">';
        $output .= '<input type="hidden" name="rating_form_id" value="' .  esc_attr( $item_rating->rating_form_id ) . '">';
        $output .= '<input type="hidden" name="item_id" value="' .  $_item_id . '">';
        $output .= '<button id="ewz_savebtn' . $_rfnum . '_' . esc_attr( $item_rating->item_id ) . '" class="ewz_savebtn_' . $_item_id . 
            '"' . " onClick='ewz_save_changed_rows( " .  $_rfnum . ", this )' disabled='disabled'>Save All</button>";

        $output .= '<br><br><button id="ewz_clearbtn' . $_rfnum . '_' . esc_attr( $item_rating->item_id ) . '" class="ewz_clearbtn_' . $_item_id . 
            '"' . " onClick='ewz_clear_row( " .  $_rfnum . ", this )'  $disable_clear >Clear Item</button>";
    }
    // invisible div to store the html for the rating page
    $output .= ewz_item_rating_page( $rating_fields, $ratings[0]->item, $rownum, $rowcount, $atts );         
    $output .= '</td>';

    $output .= '</tr>';
    return $output;
}

function next_displayed_field( $view, $rating_fields, $n ){
    assert( is_string( $view ) );
    assert( is_array( $rating_fields ) );
    assert( is_int($n));

    $i = 1;
    if( $view == 'secondary' ){
        if( isset($rating_fields[$n + $i]) ){
            return $rating_fields[$n + $i];
        } else {
            return false;
        }
    } else {
        while( isset($rating_fields[$n + $i]) ){
            if( !($rating_fields[$n + $i]->is_second) ){
                return $rating_fields[$n + $i];
            }
            ++$i;
        }
        return false;
    }
}        
        

/**
 * Return the html for the list display
 * Called from ewz-rating-shortcode.php
 *
 * @param   integer          $user_id       --  judge user id
 * @param   array            $item_ratings  --  contents of an item_rating table row
 * @param   Ewz_Rating_Form  $rating_form
 *
 * @return  string  $output is the html
 **/
function ewz_rating_list( $user_id, $item_ratings, $rating_form, $atts, $is_multiple ) {
    assert( Ewz_Base::is_nn_int( $user_id ) );
    assert( is_array( $item_ratings ) );
    assert( is_a( $rating_form, 'Ewz_Rating_Form' ) );
    assert( Ewz_Base::is_nn_int( $atts['rf_num'] ) );
    assert( is_bool( $is_multiple ) );

    // generate the output
    $num_done = (int)Ewz_Item_Rating::get_judge_count( $user_id, $rating_form->rating_form_id );
    $output = '';
    // only show the counts, finished button, help and jump-to-unrated buttons if 
    //   1. all items are shown and
    //   2. view is "rate" ( for "secondary", all items would show as rated ) and
    //   3. rf_num is 0 or not set  ( not perfect -- we really need a way to detect the number of
    //                  shortcodes on the page ).
    $show_counts = !$atts['item_ids'] && !$atts['rf_num'] && ( $atts['view'] == 'rate' );
    if( $show_counts ){
        // This div is anchored to the bottom of the viewport
        // Show it if settings want it 
        if( $rating_form->rating_scheme->settings['summary'] ){
            $output .= "<div class='ewz_jstatus' id='ewz_jstatus'>";
            $output .=  "$num_done Items saved.<br>" . (int)$rating_form->rating_status[ 'count' ] . " Items total." ;
            $output .= "</div>";
        }
    }

    $_rfnum =  esc_attr( $atts['rf_num'] );
    $output .=  '<table class="ewz_chead" id="ewz_chead' .  $_rfnum  . '" ><tr>';
    if( $show_counts ){
        if( $rating_form->rating_scheme->settings['finished'] ){
            $output .= "<td rowspan='2' class='ewz_jdone' >";
            $output .=    "When you have finished <u>all</u> the items, please click the button ";
            $output .=    "below to notify the administrators of this.  The software will first make sure you have actually ";
            $output .=    "saved everything correctly, then remove your access to the page. ";
            $output .=    "<br><button id='donebutton' onClick='judge_finished( " .  $_rfnum  . ")'>Finished</button>";
            $output .= "</td>";
        }
    }

    if( $show_counts || $atts['view'] == 'read'){
        if( $rating_form->rating_scheme->settings['jhelp']){         
            $output .=  '<td><button  id="help_btn" onClick="ewz_get_judge_help()">Using the Rating Form</button>';
            $output .=      '<div id="ewz_jhelp"  class="wp-dialog">' . ewz_jhelp_text($atts['view']) . '</div>';
            $output .=  '</td>';
        }
    }

    if( $show_counts && ( $num_done > 0 ) && ( $num_done < $rating_form->rating_status[ 'count' ] ) ){
        $output .= '</tr><tr><td><button id="next_new_btn' . $_rfnum . '" onClick="to_next_item( ' . $_rfnum . ' )">Jump To First Unrated Item</button></td>';
    }

    $output .=  '</tr></table>';  // chead
    $rating_fields = array_values( $rating_form->rating_scheme->fields );
    if ( count( $item_ratings ) > 0 ) {
        // doing 'submit' via ajax, no form needed 
        $_classlist = "ewz_rtable ewz_rating_table";
        if( !$is_multiple ){
            $_classlist .= " sortable";
        }
        $output .= '<div class="ratingdiv"><table id="ewz_rtable_' . $_rfnum  . '" class="' . $_classlist .'"><thead>';
        // header row
        $output .= "\n   <tr>";
        $lastfield = count($rating_fields) - 1;
        foreach ( $rating_fields as $n => $rating_field ) {
            $next_displayed = next_displayed_field( $atts['view'], $rating_fields, $n );
            if( $next_displayed ){
                $append_next = $next_displayed->append ;
            } else {
                $append_next = false;
            }

            if( $rating_field->is_second && $atts['view'] != 'secondary' ){
                continue;
            }
            if( !$rating_field->append ){
                $output .= "<th ";

                if( $rating_field->field_type == 'fix' ){
                    $field = $rating_form->rating_scheme->item_layout->fields[$rating_field->fdata['field_id']]; 
                    // no sorting for image or appended fields or in case of multiple shortcodes on a page
                    if( $field->field_type == 'img' || $atts['rf_num'] || $append_next ){
                        $output .= ' class="unsortable"';
                    } else {
                        $output .= ' class="col' . esc_attr( $rating_field->rating_field_id ) . '" ';
                    }
                } else {
                    if( ($rating_field->is_second && $atts['view'] != 'secondary') || $append_next ){
                        $output .= ' class="unsortable"';
                    } else {
                        $output .= ' class="col' . esc_attr( $rating_field->rating_field_id ) . '" ';
                    }
                }
                $output .= ">";
            }

            $output .= ewz_header( $n, $rating_field, $rating_fields, $rating_form, $atts );
            $next_displayed = next_displayed_field( $atts['view'], $rating_fields, $n );
            if( ( $n < $lastfield ) && $next_displayed && $next_displayed->append ) {
                $output .= " / ";
            } else {
                $output .= "</th>";  
            } 
        }

        // always needed for "save" column which contains the code for the large window
        $output .=    '<th class="unsortable"></th></tr>';
         
        $output .= '</thead><tbody>';


       // table rows
       $nratings = count( $item_ratings );
       $m = 0;
       foreach ( $item_ratings as  $item_id => $ratings ) {
           assert( $m < count($item_ratings));
           $output .= "\n" . ewz_rated_item_row( array( 'user_id' => $user_id, 
                                                        'rating_form' => $rating_form, 
                                                        'rating_fields' => $rating_fields, 
                                                        'item_id' => $item_id, 
                                                        'ratings' => $ratings, 
                                                        'rownum' => $m, 
                                                        'rowcount' => $nratings,
                                                        'atts' => $atts ) );
           ++$m;
       }
       $output .= "</tbody></table></div>";
       if( !$atts['rf_num'] && !$atts['item_ids']){
           $output .= "<div class='ewz_bottomtext' id='bottom_p'><span class='ewzleft'><a id='top_link' href='#columns_top'>Back to the top of the page</a></span> ";
           $output .= "<span class='ewzrt'><a id='reload_link' href='javascript:window.location.reload(true);'>Cancel Current Changes and Reload Page</a></span></div>";
       }
    }       
    return  $output;
}

function ewz_jhelp_text($view){
    assert( is_string($view) );
    $help_str1 = <<<EOT1
          &nbsp; 
          <p><u>If this is your first time using the interface, please read these instructions carefully before proceeding.</u>
             ( This window is resizeable ).
          </p>
          <p>For repeat users, here are the really important points to remember: </p>
             <ul class="ewz_lpad">
                 <li>If you leave it for any length of time,  <u>reload the page before continuing</u>.</li>
                 <li>To refresh/reload the page:
                      <ul class="ewz_lpad"><li>Windows: ctrl + F5</li>
                          <li>Mac/Apple: Apple + R or command + R</li>
                          <li>Linux: F5</li>
                      </ul>
                 </li>
                 <li>Make sure your image window is on a calibrated monitor and is <u>large enough to show the test image completely</u>.</li>
                 <li>"Save" buttons are disabled until you make a change.</li>
                 <li>Save frequently. You can change anything later.</li>
                 <li>If scoring, use the sort facility to review your scores before finishing.</li>
              </ul>
EOT1;
$help_str2 = <<<EOT2
          <h2>Setting Up the Windows</h2>
          <p>The first time you click on a thumbnail it will bring up a new window showing a larger version of the image.  
             Once such a window exists, all subsequent images will display in the same window. We will refer to this as the
             "image window" and to the original window as the "list window". If you can, <u>resize the list window</u> 
             as needed and <u>arrange the two windows</u> so both are visible and do not overlap. The image window should be on a 
             color-managed screen.
          </p>
          <h2>Image Dimensions</h2>
          <p>To ensure that all users see the same view, images may <b>not</b> be enlarged or shrunk.
             The image window should open automatically at a size just large enough to accommodate the largest image.
          </p> 
          <p>Just in case some particular browser or operating system shows the window differently, a test image with the maximum  
             possible dimensions, and with a very clearly-defined border, is provided.  You may view this image at any time by clicking
             the "Test Image" link in the lower right-hand corner of the image window. If the test image is cut off in any way,
             ( i.e. if you cannot see the entire border ), enlarge the window until it fits and the "Back" link in the bottom right
             corner is visible.
          </p>
          <h2>Sorting</h2>
          <p>Clicking on any column header containing an up or down arrow should sort the items by that column.
          </p>
          <p>Refreshing/Reloading the page should return the items to their original order.
             To refresh/reload the page:
                      <ul class="ewz_lpad"><li>Windows: ctrl + F5</li>
                          <li>Mac/Apple: Apple + R or command + R</li>
                          <li>Linux: F5</li>
                      </ul>          
           </p>
EOT2;
   $help_str3 = <<<EOT3
          <h2>Saving and Errors</h2>
          <p>Each row ( item ) has a "Save" button at the end. This button is <u>disabled at first</u>.
             As soon as you make any change to an item, the Save button is enabled and gets a red border. 
          </p>
          <p>Clicking <b>any</b> enabled "Save" button saves all the items with  enabled "Save" buttons -- i.e all the items
             changed since the last save.
             Once saving is complete, the red border around the "Save" buttons disappears, and the buttons are greyed out, indicating
             they are no longer enabled.  They will be re-enabled if you make any further change.
             The black border around the item row, which indicates you have no rating saved for the item, also disappears.
          </p>
          <p>The total number of ratings saved is displayed in the bottom left-hand corner of the list window. It should
             update after each save.
          </p>
          <p>It is suggested that you should save quite frequently, just in case of mistakes or network failures. You can always
             go back and change any item later.
          </p> 
          <h2>Errors and Messages</h2>
          <p>If there is a required field that you failed to fill out, no items will be saved. Instead, an error message will pop 
             up, and the row with the missing/incorrect item will be centered in the screen and highlighted. Once
             all errors have been corrected, all items with "Save" enabled will be saved.
          </p>
          <p>To speed things up, individual saves do not reload the entire page.
             But if you leave the window open for an extended period, <u>you should occasionally completely reload the page after saving</u>. 
             Otherwise, security features in Wordpress may cause your authorization to expire, and you will lose any unsaved work.
             If you leave the window open for long enough, you may see a reminder to do this. <br>A link to reload the page is provided at 
             the bottom.
          </p>
          <p> &nbsp;
          </p>
EOT3;
     if( $view == 'read' ){
         return $help_str2;
     } else {
         return $help_str1 . $help_str2 . $help_str3;
     }
}


 
/**
 * Return the table header row
 *
 * @param $n             int                          index of the rating_field in the rating_fields array
 * @param $rating_field  Ewz_Rating_Field             the field to be displayed in the column
 * @param $rating_fields array of Ewz_Rating_Fields   all the fields ( needed in case of append )
 * @param $rating_form   Ewz_Rating_Form              the rating form being displayed
 * @param $atts          array of strings             attributes set in the shortcode invocation
 **/
function ewz_header( $n, $rating_field, $rating_fields, $rating_form, $atts ){
    assert( is_int( $n ) );
    assert( is_a( $rating_field, 'Ewz_Rating_Field' ) );
    assert( is_array( $rating_fields ) );
    assert( is_a( $rating_form, 'Ewz_Rating_Form' ) );
    assert( is_array( $atts ) );

    $output= '';
    // do not display an "is_second" field unless the view is "secondary" 
    // TODO:  should it also be shown in a read-only view?
    if ( ( $atts['view'] != 'secondary' )  && $rating_field->is_second ){
        return '';
    }
    if( $rating_field->required ){
        $output .= '*';
    }
    $output .=  esc_html( $rating_field->field_header );
    return $output;
}