jQuery(document).ready(function () {
   jQuery('.variation-custom-fields-times').css('display', 'none');
    jQuery('.corcrm_custom_subscription_time_field ').css('display', 'none');
    jQuery('.hiecor_subscription_interval').css('display', 'none');
    jQuery('.subscription_lifetime').css('display', 'none');
    jQuery('.enter_subscription_lifetime').css('display', 'none');
    jQuery('.subscription_options').css('display', 'none');

    sel_val = jQuery('#corcrm_custom_product_type').find(":selected").val();

    if (sel_val == 'subscription') {
        jQuery('.corcrm_custom_subscription_time_field ').css('display', 'block');
        jQuery('.hiecor_subscription_interval').css('display', 'block');
        jQuery('.subscription_lifetime').css('display', 'block');
        jQuery('.allow_hiecor_subscription_field').css('display', 'none');
        jQuery('.subscription_options').css('display', 'none');
        jQuery('.enter_subscription_lifetime').css('display', 'block');

        if (jQuery('#subscription_lifetime_checkbox').is(':checked')) {
            jQuery('.enter_subscription_lifetime').css('display', 'none');
        } else {
            jQuery('.enter_subscription_lifetime').css('display', 'block');
        }
    }

    jQuery('#corcrm_custom_product_type').on('change', function () {
        sel_val = jQuery(this).find(":selected").val();
        if (sel_val == 'subscription') {
            jQuery('.corcrm_custom_subscription_time_field ').css('display', 'block');
            jQuery('.hiecor_subscription_interval').css('display', 'block');
            jQuery('.subscription_lifetime').css('display', 'block');
            jQuery('.allow_hiecor_subscription_field').css('display', 'none');
            jQuery('.subscription_options').css('display', 'none');
            jQuery("#allow_hiecor_subscription").prop("checked", false);
             jQuery('.enter_subscription_lifetime').css('display', 'block');
        } else {
            jQuery('.corcrm_custom_subscription_time_field ').css('display', 'none');
            jQuery('.hiecor_subscription_interval').css('display', 'none');
            jQuery('.subscription_lifetime').css('display', 'none');
            jQuery('.allow_hiecor_subscription_field').css('display', 'block');
            jQuery('.subscription_options').css('display', 'none');
            jQuery('.enter_subscription_lifetime').css('display', 'none');
        }
    });

    jQuery(document).on('change', '.crm_select_class', function () {
        sel_val = jQuery(this).find(":selected").val();
        if (sel_val == 'subscription') {
            jQuery(this).parents('.variation-custom-fields').next('.variation-custom-fields-times').css('display', 'block');

        } else {
            jQuery(this).parents('.variation-custom-fields').next('.variation-custom-fields-times').css('display', 'none');

        }
    });

    if (jQuery("#corcrm_custom_product_type").val() == "subscription") {
        jQuery("#corcrm_custom_product_type option[value='straight']").remove();
    }

    jQuery('#subscription_lifetime_checkbox').on('click', function () {
        if (jQuery('#subscription_lifetime_checkbox').is(':checked')) {
            jQuery('.enter_subscription_lifetime').css('display', 'none');
        } else {
            jQuery('.enter_subscription_lifetime').css('display', 'block');
        }
    });

   

    jQuery('#allow_hiecor_subscription').on('click', function () {
        if (jQuery('#allow_hiecor_subscription').is(':checked')) {
            jQuery('.subscription_options').css('display', 'block');
        } else {
            jQuery('.subscription_options').css('display', 'none');
        }
    });
    
     if (jQuery('#allow_hiecor_subscription').is(':checked')) {
        jQuery('.subscription_options').css('display', 'block');
    } else {
        jQuery('.subscription_options').css('display', 'none');
    }
    // check if Hiecor square payment checkbox is checked
    show_hide_hcv4_square_payment();
    jQuery("#woocommerce_corcrm_payment_square_payment:checkbox").change(function() {
		show_hide_hcv4_square_payment();
    });
    
});

function  show_hide_hcv4_square_payment(){
    var ischecked= jQuery("#woocommerce_corcrm_payment_square_payment:checkbox").is(':checked');
    if(ischecked){
        jQuery("#woocommerce_corcrm_payment_square_environment").parents("tr").fadeIn();
        jQuery("#woocommerce_corcrm_payment_square_payment_application").parents("tr").fadeIn();
        jQuery("#woocommerce_corcrm_payment_square_payment_location_id").parents("tr").fadeIn();
    }else{
        jQuery("#woocommerce_corcrm_payment_square_environment").parents("tr").fadeOut();
        jQuery("#woocommerce_corcrm_payment_square_payment_application").parents("tr").fadeOut();
        jQuery("#woocommerce_corcrm_payment_square_payment_location_id").parents("tr").fadeOut();
    }
}