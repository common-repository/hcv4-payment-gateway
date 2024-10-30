===  HieCOR Payment Gateway Plugin ===
Contributors: hiecor
Tags: HCV4 Payment Gateway
Requires at least: 4.7.5
Tested up to: 6.5.3
Stable tag: 1.4.8
License: GPLv2 or later

HieCOR's everything your business needs to provide software support in a single provider.
Included in HieCOR is a website, point of sale, invoicing, subscription, CRM, shipping, inventory management, merchant processing and support services.
This is a plugin will enable automatic inventory sync and accept payments with your HieCOR system.

== Description ==
HieCOR's everything your business needs to provide software support in a single provider.
Included in HieCOR is a website, point of sale, invoicing, subscription, CRM, shipping, inventory management, merchant processing and support services.
This is a plugin will enable automatic inventory sync and accept payments with your HieCOR system.


== Changelog ==
=1.4.8=
* Bug Fix - Send _wc_cog_cost in case of create product only.

=1.4.7=
* Bug Fix - CC validation included.

=1.4.6=
* Bug Fix - Customer Info passed to order API request.

=1.4.5=
* Bug Fix - Special price date format correction to hiecor push.

=1.4.4=
* Bug Fix - Special price dates correction to hiecor push.

=1.4.3=
* Feature - Support from wp all import - create/update product to hiecor using wp all import hook.

=1.4.2=
* Bug Fix - Stock should not be reduced in Estimate payment method.
* Feature - Order Source setting added in Estimate Payment Gateway settings.

=1.4.1=
* Bug Fix - Minor fix - few changes were missed in 1.4.0

=1.4.0=
* Feature - New "Estimate" payment method added.

=1.3.6=
* Bug Fix - PHP error fixed weight_conversion/dimension_conversion round function throwing error for string value

=1.3.5=
* Bug Fix - Deprecated: Creation of dynamic property in Corcrm_Utility. PHP notice fixed.

=1.3.4=
* Bug Fix - Auto linking Hiecor & WC variations were not matching because of WC Attribute Value not matching  it was coming in slug format.

=1.3.3=
* Bug Fix - Auto linking was not sending var_id to hiecor.

=1.3.2=
* Bug Fix - Auto linking Code refactor.
* Bug Fix - var_stock/var_surcharge stopped sending
* Bug Fix - check_syncable has been stopped since we no longer sending stock from wp -> hiecor

=1.3.1=
* Bug Fix - Auto linking was not pulling variations correctly.
*           product->get_available_variations replaced with product->get_children()
* Support - WP 6.5.3
* Supoort - WC 8.8.3

=1.3.0=
* Feature - Product Linking with hiecor based on Brand and SKU
* Feature - Stopped Stock syncing from woocommerce to hiecor
* Feature - Send single main image URL for product. Full URL will be saved in Hiecor

=1.2.3=
* Feature - Pull Brand from Attributes if not found in get_post_custom_values (wp_postmeta)

=1.2.2=
* Bug Fix - CorcrmUtility.getProductAttributes was causing db errors

=1.2.1=
* Bug Fix - hiecor_product_id added to wp_postmeta after creating product from woocommerce to hiecor

=1.2.0=
* Bug Fix - HiecorID was not showing. Also fallback to wp_post_meta-hiecor_product_id added if wp_posts.corcrm_product_id not found.

=1.1.9=
* Bug Fix - Hiecor VariationID was not showing

=1.1.8=
* WC support 8.5.2 & WP Support 6.4.3
* Minor php session notice fix

=1.1.7=
* Minor php notice fix


=1.1.6=
* Brand name is sent to hiecor from custom fields
* WC tested up to: 8.0.1
* WP tested up to: 6.3.2

=1.1.5=
* Sending X-API-Source to rest API call
* Customer creation during creating order in hiecor is removed

=1.1.4=
* Tested upto WP 6.0.2 & WC 6.9.0
* Variation Special Price feature added
* action_woocommerce_add_to_cart (check_corcrm_existance) removed

=1.1.3=
* Tested upto WP 6.0 & WC 6.5.1

=1.1.2=
* Feature -  Adding ping APIs to custom endpoints for checking WP APIs are accessible during HieCOR to WC sync.

=1.1.1=
* Bug -  if `Visible on product page` is not checked then variations & attributes were not pushing to HieCOR.

=1.1.0=
* Fix -  Plugin name is changed to "HieCOR Payment Gateway Plugin"

=1.0.27=
* Bug -  Image issue if month/year wise folder setting is Off.
* Bug -  Logging adjustments for check_corcrm_existency.

=1.0.26=
* Feature -  HieCOR support for composite product.Composite products will be created as `inactive` products in Hiecor.

=1.0.25=
* Bug Fix -  `check_corcrm_existance` SET corcrm_product_id=0 if product found deleted in HieCOR.

=1.0.24=
* Bug Fix -  hicor_visit_js conflict fixed.
* Bug Fix -  Removed subscriptions information from Variation section.
* Bug Fix -  check_corcrm_existency issue fixed i.e. woocommerce_add_to_cart hook updated.
* Bug Fix -  Product's General Tab - "CorCRM Product type" renamed to "Hiecor Product type".


=1.0.23=
* Feature		  -  Customer select shipping method passed to HieCOR.

=1.0.22=
* Feature		  -  Multi site support added.
* Bug Fix		  -  Customer IP address passed to Hiecor Order API.

=1.0.21=
* Bug Fix		  -  hiecor_order_id Locking issue fixed.
			 
=1.0.20=
* Bug Fix		  -  Custom Attribute was sending html characters
* Bug Fix		  -  Hiecor order API json parsing error will be logged into logger
* Bug Fix		  -  Pull variation attributes using -
					 wp_terms/wp_term_taxonomy/wp_term_relationships table to avoid duplicate slug among different attribute values.


=1.0.19=
* Bug Fix		  -  Attributes not used in variations will not be pushed to Hiecor.
* Bug Fix		  -  attribute_required flag "on/off" should be sent to Hiecor.

=1.0.18=
* Deprecation Fix -  action woocommerce_add_order_item_meta replaced with woocommerce_new_order_item
* Deprecation Fix -  WC_Order::get_used_coupons replaced with WC_Order::get_coupon_codes()
* Enhancement	  -  Support for Tip [ https://wordpress.org/plugins/yith-woocommerce-name-your-price ]
* Bug Fix		  -  WC_Order::get_customer_note passed to user_comments

=1.0.17=
* Bug Fix - Duplicate variationID issue fixed

=1.0.16=
* Bug Fix - Hiecor Iframe required product should not sync with hiecor
* Bug Fix - allow_hiecor_subscription notice fix

=1.0.15=
* WC support 4.1.0. Deprecation warnings removed from billing_addr & shipping_addr
* Hiecor iFrame Product Feature added
* Dynamic source through query string - feature added
* Bug Fix - Duplicate coupon code sync error displayed

=1.0.14=
* Minor Bug Fix - Stock Management from woocommerce to Hiecor for simple & variable product
* Minor Bug Fix - Primary image in Hiecor should be passed at 0th index
* Minor Bug Fix - Split row parameter passed to Hiecor
* Minor Bug Fix - Coupon description passed


=1.0.13=
* Minor Bug Fix - Tracking pixel/External Tracking Pixel
* Minor Bug Fix - Dimenion conversion PHP 7.X support
* Minor Bug Fix - Duplicate order prevention on Decline

=1.0.12=
* Minor Bug Fix - Wp prefix added to saveAttrMapping/getProductAttributes/getVariationAttributes

=1.0.11=
* Minor Bug Fix - Wp & Hiecor Coupon mapping fixed
* Minor Bug Fix - Wp(minimum_amount) & Hiecor(total_amount) mapping fixed

=1.0.10=
* Minor Bug Fix - Connection Timeout with HieCOR APIs handled

=1.0.9=
* Minor Bug Fix - Taxable/Non-Taxable flag for products
* Minor Bug Fix - Special Price/ Start-End date issue fixed
* Minor Bug Fix - Variation issue fixed ($loop added to assign correct hiecor variation_id on Variations)
* Minor Bug Fix - Variation issue fixed (User Parent UPC & Parent Price flat not sending now)

=1.0.8=
* Minor Bug Fix - Weight/Dimension conversion to lbs/inches
* Minor Bug Fix - Order Confirmation email param added

=1.0.7=
* Minor Bug Fix - Custom & Global Attributes with Spaces and hyphen can be pushed to Hiecor

=1.0.6=
* HCV4 Square payment support add
* Bug Fixes - product/list replaced with product/{id}

=1.0.5=
* Bug fix - Coupon with variation products can be created

=1.0.4=
* Support for woocommerce version 3.8.1

=1.0.3=
* Coupons can be added to HIECOR

=1.0.2=
* Order Source option added

=1.0.1=
* Minor Bug Fixes