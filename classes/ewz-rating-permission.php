<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/ewz-base.php");
require_once( EWZ_PLUGIN_DIR . "classes/ewz-permission.php");

class Ewz_Rating_Permission extends Ewz_Permission
{
    /**
     * Can the current user edit the rating form ( object parameter )
     * Return true if the current user can assign the rating form's layout
     *
     * @param  Ewz_Rating_Form   $rating_form
     * @return boolean
     **/
    public static function can_edit_rating_form_obj( $rating_form ) {
        assert( is_a( $rating_form, 'Ewz_Rating_Form' ) );
        return self::can_assign_layout( $rating_form->rating_scheme->item_layout_id );
    }

    /**
     * Can the current user edit the rating form ( id parameter )
     * Return true if the current user can assign the rating form's layout
     *
     * @param  int    $rating_form_id
     * @return boolean
     **/
     public static function can_edit_rating_form( $rating_form_id ) { 
         assert( Ewz_Base::is_nn_int( $rating_form_id ) ); 
         foreach( Ewz_Rating_Form::get_all_form_layout_ids() as $sch_lay ){
             if( (int)$sch_lay->rating_form_id  == $rating_form_id ){
                 if( self::can_assign_layout( (int)$sch_lay->item_layout_id ) ){
                     return true;
                 }
             }
         }
         return false;
     } 

    /**
     * Can the current user edit all rating forms
     * Return true if the current user can assign any layout used in any rating form
     *
     * @return boolean
     **/
    public static function can_edit_all_rating_forms(){
        foreach( Ewz_Rating_Form::get_all_form_layout_ids() as $sch_lay ){
            if( !self::can_assign_layout( (int)$sch_lay->item_layout_id ) ){
                return false;
            }
        }
        return true;
    }

    /**
     * Can the current user edit the rating scheme ( object parameter )
     * Return true if the current user can edit the rating scheme's layout
     *
     * @param  Ewz_Rating_Scheme   $rating_scheme
     * @return boolean
     **/
    public static function can_edit_rating_scheme_obj( $rating_scheme ) {
        assert( is_a( $rating_scheme, 'Ewz_Rating_Scheme' ) );
        return self::can_edit_layout( $rating_scheme->item_layout_id );
    }


    /**
     * Can the current user edit the rating scheme ( id parameter )
     * Return true if the current user can assign the rating scheme's layout
     *
     * @param  int  $rating_scheme_id
     * @return boolean
     **/
    public static function can_edit_rating_scheme( $rating_scheme_id ) {
        assert( Ewz_Base::is_nn_int( $rating_scheme_id ) );
        return self::can_edit_layout(  Ewz_Rating_Scheme::get_item_layout_id( $rating_scheme_id ) );        
    }

    /**
     * Can the current user see the admin rating schemes page
     * Return true if the current user can edit the layout for a scheme
     *
     * @return boolean
     **/
    public static function can_see_scheme_page(){
        $scheme_layouts =  Ewz_Rating_Scheme::get_all_scheme_layout_ids();
        if( self::can_edit_all_layouts() ){
            return true;
        }
        foreach( $scheme_layouts as $sch_lay ){
            if( self::can_edit_layout( (int)$sch_lay->item_layout_id ) ){
                return true;
            }
        }
        return false;
    }

    /**
     * Can the current user edit all rating schemes
     * Return true if the current user can edit any layout used in any rating scheme
     *
     * @return boolean
     **/
    public static function can_edit_all_schemes(){
        foreach( Ewz_Rating_Scheme::get_all_scheme_layout_ids() as $sc_lay){
            if( !self::can_edit_layout( (int)$sc_lay->item_layout_id ) ){
                return false;
            }
        }
        return true;
    }
    /**
     * Can the current user see the admin rating forms page
     * Return true if the current user can assign the layout for some rating form
     *
     * @return boolean
     **/
    public static function can_see_rating_form_page(){
        $form_layouts = Ewz_Rating_Form::get_all_form_layout_ids();
        if( self::can_edit_all_layouts() ){
            return true;
        }
        foreach( $form_layouts as $sch_lay ){
            if( self::can_assign_layout( (int)$sch_lay->item_layout_id ) ){
                return true;
            }
        }
        return false;
    }

    /**
     * Can the current user download from the rating form
     * Return true if the current user can download from some webform with the input layout
     *
     * @param  int   $layout_id
     * @return boolean
     **/
    public static function can_download_rating( $layout_id ){
        assert( Ewz_Base::is_nn_int( $layout_id ) );
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        $perms_for_user = self::get_ewz_permissions_for_user();
        if ( in_array( -1, $perms_for_user['ewz_can_download_webform'] ) ) {
            return true;
        }
        if ( in_array( $layout_id, $perms_for_user['ewz_can_download_webform_L'] ) ) {
            return true;
        }        
        return false;
    }
        
}
