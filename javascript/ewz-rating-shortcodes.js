'use strict';
var EWZrdata, tinymce;
var  ewzr_menu = [], k, ratingform;
for ( k=0; k < EWZrdata.ratingforms.length; ++k ) {
    ratingform = EWZrdata.ratingforms[k];
    ewzr_menu.push( {    text: ratingform.rating_form_title + " - simple", 
                         ewz_r_ident: ratingform.rating_form_ident,
                         onclick: function(){
                             tinymce.execCommand( 'mceInsertContent',false, '[ewz_show_rating identifier="' + this.settings.ewz_r_ident + '"]');
                         }
                    } );
}
ewzr_menu.push( {    text: "-" } );

for ( k=0; k < EWZrdata.ratingforms.length; ++k ) {
    ratingform = EWZrdata.ratingforms[k];
    ewzr_menu.push( {  text: ratingform.rating_form_title + " - general", 
                       ewz_r_ident:  ratingform.rating_form_ident,
                       onclick: function(){
                           var msg = "Enter the view type, which must be one of:\n 'rate'  ( normal view showing all non-secondary columns ) ";
                               msg += "\n 'read'  ( read-only )\n 'secondary'  ( only fields with the 'secondary'  ";
                               msg += "box checked will be editable )";
                           var view =  prompt( msg ) || "";
                           while( view && !view.match(/^(rate|read|secondary)$/) ){
                               msg = "PLEASE TRY AGAIN.\n\nThe view type must be one of:\n 'rate'  (normal view showing all columns ";
                               msg += "except those with the \"secondary\" box checked)\n 'read'  (read-only)\n 'secondary'  (only fields with the ";
                               msg += "'secondary' box checked will be editable).\nPlease enter a valid value.";
                               view =  prompt( msg ) || "";
                           }
                           var rf_num = prompt("Enter the sequence number ( blank, 1,2,3, etc -- each shortcode on a page MUST have a different number.\nIf there is only one shortcode, leave the sequence number blank if you wish to display the 'Finished', 'help' and 'jump-to-unrated' buttons. \nIf there is more than one, the first should be '1' ):", "");
                           while( rf_num && !rf_num.match(/^($|[1-9][0-9]*$)/) ){
                               rf_num = prompt("PLEASE TRY AGAIN.\n\nThe sequence number must be 1, 2, 3 .....  \nPlease enter a number, or blank ( or cancel )  if there is only one shortcode on the page.", "") || '';
                           }
                           var item_ids =  prompt("Enter a comma-separated list of item_ids, blank ( or cancel ) to show all :") || '';
                           while( item_ids && !item_ids.match(/^($|[1-9][0-9,]*$)/) ){
                               item_ids = prompt("PLEASE TRY AGAIN.\n\n The item ids must consist of digits only, separated by commas, no spaces.\nPlease enter a valid list of item_ids, or blank ( or cancel ) to show all items.", "") || '';
                           }
                           var judge_ids =  prompt("Enter a comma-separated list of judge_ids, blank ( or cancel ) to display to all :") || '';
                           while( judge_ids && !judge_ids.match(/^($|[1-9][0-9,]*$)/) ){
                              judge_ids  = prompt("PLEASE TRY AGAIN.\n\nThe judge ids list must consist of digits only, separated by commas, no spaces.\nPlease enter a valid list of judge_ids, or blank ( or cancel ) for all judges.", "") || '';
                           }

                           tinymce.execCommand( 'mceInsertContent', false, '[ewz_show_rating identifier="' + 
                                               this.settings.ewz_r_ident + '" rf_num="' + rf_num +  
                                               '" item_ids="' + item_ids + '" judge_ids="' + judge_ids + '" view="' + view + '"]' );
                       }
                    } );
}


(function() {

    tinymce.create('tinymce.plugins.RShortcodes', { 
        init: function(editor, url) { 
            editor.addButton( 'r_shortcodes', {
                type: 'menubutton',
                text: 'EWZR Shortcodes',
                icon: false,
                onselect: function(e) {}, 
                menu: ewzr_menu
            });
        }
    });
    tinymce.PluginManager.add( 'r_shortcodes', tinymce.plugins.RShortcodes );
})();
