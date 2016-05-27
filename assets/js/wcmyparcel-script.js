jQuery( function( $ ) {
	// move shipment options to 'Ship to' column
	$('.wcmp_shipment_options').each( function( index ) {
		var $ship_to_column = $( this ).closest('tr').find('td.shipping_address');
		$( this ).appendTo( $ship_to_column );
		// hidden by default - make visible
		$( this ).show();
	});


	$('.wcmp_show_shipment_options').click( function ( event ) {
		event.preventDefault();
		$( this ).next('.wcmp_shipment_options_form').slideToggle();
	});

	// select > 500 if insured amount input is >499
	$( '.wcmp_shipment_options input.insured_amount' ).each( function( index ) {
		if ( $( this ).val() > 499 ) {
			var insured_select = $( this ).closest('table').parent().find('select.insured_amount');
			$( insured_select ).val('');
		};
	});

	// hide insurance options if insured not checked
	$('.wcmp_shipment_options .insured').change(function () {
		var insured_select = $( this ).closest('table').parent().find('select.insured_amount');
		var insured_input  = $( this ).closest('table').parent().find('input.insured_amount');
		if (this.checked) {
			$( insured_select ).prop('disabled', false);
			$( insured_select ).closest('tr').show();
			$('select.insured_amount').change();
		} else {
			$( insured_select ).prop('disabled', true);
			$( insured_select ).closest('tr').hide();
			$( insured_input ).closest('tr').hide();
		}
	}).change(); //ensure visible state matches initially

	// hide & disable insured amount input if not needed
	$('.wcmp_shipment_options select.insured_amount').change(function () {
		var insured_check  = $( this ).closest('table').parent().find('.insured');
		var insured_select = $( this ).closest('table').parent().find('select.insured_amount');
		var insured_input  = $( this ).closest('table').find('input.insured_amount');
		if ( $( insured_select ).val() ) {
			$( insured_input ).val('');
			$( insured_input ).prop('disabled', true);
			$( insured_input ).closest('tr').hide();
		} else {
			$( insured_input ).prop('disabled', false);
			$( insured_input ).closest('tr').show();
		}
	}).change(); //ensure visible state matches initially

	// hide all options if not a parcel
	$('.wcmp_shipment_options select.package_type').change(function () {
		var parcel_options  = $( this ).closest('table').parent().find('.parcel_options');
		if ( $( this ).val() == '1') {
			// parcel
			$( parcel_options ).find('input, textarea, button, select').prop('disabled', false);
			$( parcel_options ).show();
			$('.insured').change();
		} else {
			// not a parcel
			$( parcel_options ).find('input, textarea, button, select').prop('disabled', true);
			$( parcel_options ).hide();
			$('.insured').prop('checked', false);
			$('.insured').change();
		}
	}).change(); //ensure visible state matches initially


	// saving shipment options via AJAX
	$( '.wcmp_save_shipment_settings' )
		.on( 'click', 'a.button.save', function() {
			var order_id = $( this ).data().order;
			var $form = $( this ).closest('.wcmp_shipment_options').find('.wcmp_shipment_options_form');
			var package_type = $form.find('select.package_type option:selected').text();
			var $package_type_text_element = $( this ).closest('.wcmp_shipment_options').find('.wcpm_package_type');

			// show spinner
			$form.find('.wcmp_save_shipment_settings .waiting').show();

			var form_data = $form.find(":input").serialize();
			var data = {
				action:     'wcmp_save_shipment_options',
				order_id:   order_id,
				form_data:  form_data,
				security:   wc_myparcel.nonce,
			};

			$.post( wc_myparcel.ajax_url, data, function( response ) {
				// console.log(response);

				// set main text to selection
				$package_type_text_element.text(package_type);

				// hide spinner
				$form.find('.wcmp_save_shipment_settings .waiting').hide();

				// hide the form
				$form.slideUp();
			});



		});

	// Bulk actions
	$("#doaction, #doaction2").click(function (event) {
		var actionselected = $(this).attr("id").substr(2);
		// check if action starts with 'wcmp_'
		if ( $('select[name="' + actionselected + '"]').val().substring(0,5) == "wcmp_") {
			event.preventDefault();
			// remove notices
			$( '.myparcel_notice' ).remove();

			// strip 'wcmp_' from action
			var action = $('select[name="' + actionselected + '"]').val().substring(5);

			// Get array of checked orders (order_ids)
			var order_ids = [];
			$('tbody th.check-column input[type="checkbox"]:checked').each(
				function() {
					order_ids.push($(this).val());
				}
			);

			// execute action
			switch (action) {
				case 'export':
					myparcel_export( order_ids );
					break;
				case 'print':
					myparcel_print( order_ids );
					break;
				case 'export_print':
					myparcel_export( order_ids );
					myparcel_print( order_ids );
					break;
			}

			return;
		}
	});

	// single actions click
	$(".order_actions")
		.on( 'click', 'a.button.myparcel', function( event ) {
			event.preventDefault();
			var button_action = $( this ).data('request');
			var order_ids = [ $( this ).data('order-id') ];

			console.log( button_action );
			console.log( order_ids );
			// execute action
			switch (button_action) {
				case 'add_shipment':
					myparcel_export( order_ids );
					break;
				case 'get_labels':
					myparcel_print( order_ids );
					break;
				case 'add_return':
					myparcel_modal_dialog( order_ids, 'return' );
					// myparcel_return( order_ids );
					break;
			}
		});		

	$(window).bind('tb_unload', function() {
		// re-enable scrolling after closing thickbox
		// (not really needed since page is reloaded in the next step, but applied anyway)
		$("body").css({ overflow: 'inherit' })
	});

	// export orders to MyParcel via AJAX
	function myparcel_export( order_ids ) {
		console.log('exporting order to myparcel...');
		var data = {
			action:           'wc_myparcel',
			request:          'add_shipments',
			order_ids:        order_ids,
			security:         wc_myparcel.nonce,
		};

		$.post( wc_myparcel.ajax_url, data, function( response ) {
			response = $.parseJSON(response);
			console.log(response);
			if ( response !== null && typeof response === 'object' && 'error' in response) {
				myparcel_admin_notice( response.error, 'error' );
			}
			return;
		});

	}

	function myparcel_modal_dialog( order_ids, dialog ) {
		var request_prefix = (wclabels.ajaxurl.indexOf("?") != -1) ? '&' : '?';
		var thickbox_height = $(window).height()-120;
		var thickbox_parameters = '&TB_iframe=true&height='+thickbox_height+'&width=720';
		var url = wc_myparcel.ajax_url+request_prefix+'order_ids='+order_ids+'&action=wc_myparcel&request=modal_dialog&dialog='+dialog+'&security='+wc_myparcel.nonce+thickbox_parameters;

		// disable background scrolling
		$("body").css({ overflow: 'hidden' })
	
		tb_show('', url);
	}

	// export orders to MyParcel via AJAX
	function myparcel_return( order_ids ) {
		console.log('creating return for orders...');
		var data = {
			action:           'wc_myparcel',
			request:          'add_return',
			order_ids:        order_ids,
			security:         wc_myparcel.nonce,
		};

		$.post( wc_myparcel.ajax_url, data, function( response ) {
			response = $.parseJSON(response);
			console.log(response);
			if ( response !== null && typeof response === 'object' && 'error' in response) {
				myparcel_admin_notice( response.error, 'error' );
			}
			return;
		});

	}


	// Request MyParcel labels
	function myparcel_print( order_ids ) {
		console.log('requesting myparcel labels...');

		var request_prefix = (wclabels.ajaxurl.indexOf("?") != -1) ? '&' : '?';
		var url = wc_myparcel.ajax_url+request_prefix+'action=wc_myparcel&request=get_labels&security='+wc_myparcel.nonce;

		// create form to send order_ids via POST
		$('body').append('<form action="'+url+'" method="post" target="_blank" id="myparcel_post_data"></form>');
		$('#myparcel_post_data').append('<input type="hidden" name="order_ids" class="order_ids"/>');
		$('#myparcel_post_data input.order_ids').val( JSON.stringify( order_ids ) );

		// submit data to open or download pdf
		$('#myparcel_post_data').submit();



		/* alternate method:
		var data = {
			action:               'wc_myparcel',
			request:              'get_labels',
			order_ids:            order_ids,
			security:             wc_myparcel.nonce,
			label_response_type:  'url',
		};

		$.post( wc_myparcel.ajax_url, data, function( response ) {
			response = $.parseJSON(response);
			console.log(response);
			if ( response !== null && typeof response === 'object' && 'error' in response) {
				myparcel_admin_notice( response.error, 'error' );
			} else if ( response !== null && typeof response === 'object' && 'url' in response) {
				window.open( response.url, '_blank' );
			}
			return;
		});
		*/

	}

	function myparcel_admin_notice( message, type ) {
		$main_header = $( '#wpbody-content > .wrap > h1:first' );
		var notice = '<div class="myparcel_notice '+type+'"><p>'+message+'</p></div>';
		$main_header.after( notice );

	}

	$( document.body ).trigger( 'wc-enhanced-select-init' );

});

