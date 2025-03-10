(function ( $ ) {
	'use strict';
 
	var hcv4SquarePaymentForm;

	// create namespace to avoid any possible conflicts
	$.Hcv4Square_payments = {
		init: function() {
			// Checkout page
			$( document.body ).on( 'updated_checkout', function() {
				$.Hcv4Square_payments.loadForm();	
			});

			// Pay order form page
			if ( $( 'form#order_review' ).length ) {
				$.Hcv4Square_payments.loadForm();
			}

			var custom_element = square_params.custom_form_trigger_element;

			// custom click trigger for 3rd party forms that initially hides the payment form
			// such as multistep checkout plugins
			if ( custom_element.length ) {
				$( document.body ).on( 'click', custom_element, function() {
					$.Hcv4Square_payments.loadForm();		
				});
			}

			// work around for iFrame not loading if elements being replaced is hidden
			$( document.body ).on( 'click', '#payment_method_corcrm_payment', function() {
			    build_square_form(square_params);
				$( '.payment_box.payment_method_corcrm_payment' ).css( { 'display': 'block', 'visibility': 'visible', 'height': 'auto' } );	
			});
                },
		loadForm: function() {
			if ( $( '#payment_method_corcrm_payment' ).length ) {
                            // work around for iFrame not loading if elements being replaced is hidden
                            if ( ! $( '#payment_method_corcrm_payment' ).is( ':checked' ) ) {
                                    $( '.payment_box.payment_method_corcrm_payment' ).css( { 'display': 'block', 'visibility': 'hidden', 'height': '0' } );
                            }

                            // destroy the form and rebuild on each init
                            if ( 'object' === $.type( hcv4SquarePaymentForm ) ) {
                                    hcv4SquarePaymentForm.destroy();
                            }

                            build_square_form(square_params);

                            // when checkout form is submitted on checkout page
                            $( 'form.woocommerce-checkout' ).on( 'checkout_place_order_corcrm_payment', function( event ) {
                                    // remove any error messages first
                                    $( '.payment_method_corcrm_payment .woocommerce-error' ).remove();

                                    if ( $( '#payment_method_corcrm_payment' ).is( ':checked' ) && $( 'input.square-nonce' ).size() === 0 ) {
                                            hcv4SquarePaymentForm.requestCardNonce();

                                            return false;
                                    }

                                    return true;
                            });

                            // when checkout form is submitted on pay order page
                            $( 'form#order_review' ).on( 'submit', function( event ) {
                                    // remove any error messages first
                                    $( '.payment_method_corcrm_payment .woocommerce-error' ).remove();

                                    if ( $( '#payment_method_corcrm_payment' ).is( ':checked' ) && $( 'input.square-nonce' ).size() === 0 ) {
                                            hcv4SquarePaymentForm.requestCardNonce();

                                            return false;
                                    }

                                    return true;
                            });

                            $( document.body ).on( 'checkout_error', function() {
                                    $( 'input.square-nonce' ).remove();
                                    $( 'input#buyerVerification-token' ).remove();
                            });

                            // work around for iFrame not loading if elements being replaced is hidden
                            setTimeout( function() {
                                    if ( ! $( '#payment_method_corcrm_payment' ).is( ':checked' ) ) {
                                            $( '.payment_box.payment_method_corcrm_payment' ).css( { 'display': 'none', 'visibility': 'visible', 'height': 'auto' } );
                                    }
                            }, 1000 );
			}
		}
	}; // close namespace

	$.Hcv4Square_payments.init();
	
	function build_square_form(square_params){
	    hcv4SquarePaymentForm = new SqPaymentForm({
                    env: square_params.environment,
                    applicationId: square_params.application_id,
                    locationId: square_params.locationId,
                    inputClass: 'sq-input',
                    cardNumber: {
                        elementId: 'sq-card-number',
                        placeholder: square_params.placeholder_card_number   
                    },
                    cvv: {
                        elementId: 'sq-cvv',
                        placeholder: square_params.placeholder_card_cvv
                    },
                    expirationDate: {
                        elementId: 'sq-expiration-date',
                        placeholder: square_params.placeholder_card_expiration
                    },
                    postalCode: {
                        elementId: 'sq-postal-code',
                        placeholder: square_params.placeholder_card_postal_code
                    },
                    callbacks: {
                        cardNonceResponseReceived: function( errors, nonce, cardData ) {
                            if ( errors ) {
                                    var html = '';

                                    html += '<ul class="woocommerce_error woocommerce-error">';

                                    // handle errors
                                    $( errors ).each( function( index, error ) { 
                                            html += '<li>' + error.message + '</li>';
                                    });

                                    html += '</ul>';

                                    // append it to DOM
                                    $( '.payment_method_corcrm_payment fieldset' ).eq(0).prepend( html );
                            } else {
                                var $form = $( 'form.woocommerce-checkout, form#order_review' );


                                var verificationDetails = { 
                                        intent: 'CHARGE', 
                                        amount: square_params.cart_total, 
                                        currencyCode: square_params.get_woocommerce_currency, 
                                        billingContact: {}
                                  }; 
                                 try {
                                    hcv4SquarePaymentForm.verifyBuyer(
                                      nonce,
                                      verificationDetails,
                                      function(err,verification) {
                                            if (err == null) {
                                                // inject nonce to a hidden field to be submitted
                                                $form.append( '<input type="hidden" class="square-nonce" name="square_nonce" value="' + nonce + '" />' );
                                                $form.append( '<input type="hidden" id="buyerVerification-token" name="buyerVerification_token"  />' );
                                                document.getElementById('buyerVerification-token').value = verification.token;
                                                $form.submit();
                                            }else{
                                                console.log('BuyerVerificationErr',err);
                                            }
                                    });
                                    // POST the nonce form to the payment processing page
                                    // document.getElementById('nonce-form').submit();
                                  } catch (Error) {
                                        console.log("Error"+Error);
                                      //TypeError thrown if illegal arguments are passed
                                  }

                            }
                        },

                        paymentFormLoaded: function() {
                                hcv4SquarePaymentForm.setPostalCode( $( '#billing_postcode' ).val() );
                        },

                        unsupportedBrowserDetected: function() {
                                var html = '';

                                html += '<ul class="woocommerce_error woocommerce-error">';
                                html += '<li>' + square_params.unsupported_browser + '</li>';
                                html += '</ul>';

                                // append it to DOM
                                $( '.payment_method_corcrm_payment fieldset' ).eq(0).prepend( html );
                        }
                    },
                    inputStyles: $.parseJSON( square_params.payment_form_input_styles )
            });

            hcv4SquarePaymentForm.build();
	}
	
	
	
}( jQuery ) );