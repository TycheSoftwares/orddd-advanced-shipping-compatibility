/**
 * Handles the functionality of custom delivery settings for shipping packages. 
 *
 *
 * @since 1.0
 */

jQuery(document).ready( function() {
    var shipping_package_id = orddd_advanced_shipping_compatibility_params.shipping_package_id;
    jQuery(document).on( "change", "input[name=\"shipping_method[ " + shipping_package_id + "]\"]", function() {
        console.log( "HERE" );
        if( jQuery( "#orddd_enable_shipping_based_delivery" ).val() == "on" ) {
            localStorage.removeItem( "orddd_storage_next_time" );
            localStorage.removeItem( "e_deliverydate_session" );
            localStorage.removeItem( "h_deliverydate_session" );
            localStorage.removeItem( "time_slot" );  
        }
        orddd_update_delivery_session();
    });
});