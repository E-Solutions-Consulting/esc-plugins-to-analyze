(function ( $ ) {
	'use strict';

	/**
	 * Restricts a subscription's own Schedule box (#_billing_period /
	 * #_billing_interval) to the values in bhSubscriptionBilling (localized
	 * from BH_ALLOWED_BILLING_* / BH_DEFAULT_BILLING_* in
	 * includes/modules/common/constants.php).
	 *
	 * Why: a new subscription has an empty billing period, so the Billing
	 * Period drop-down shows its first option, "day". Activating it like that
	 * saves "every 1 day" and WooCommerce Subscriptions sets the next payment
	 * to tomorrow; fixing the frequency afterwards doesn't move that date.
	 *
	 * - New subscription: disallowed options are removed and the default
	 *   ("every 3 months") is selected.
	 * - Existing subscription with an allowed value: disallowed options are
	 *   removed, the saved value is kept.
	 * - Existing subscription with a value outside the list (e.g. "day"): all
	 *   options stay, the disallowed ones disabled, so saving the form for any
	 *   other reason never rewrites its schedule silently.
	 *
	 * UI only on purpose: rewriting a subscription's schedule on the server
	 * would recalculate dates and scheduled renewals.
	 *
	 * When CS adds the subscription's product, the Schedule box is set to that
	 * product's own period/interval (the variation is the source of truth), with
	 * a notice; on an existing subscription the notice asks to check the Next
	 * payment date, which WooCommerce Subscriptions doesn't recalculate.
	 */

	var settings          = window.bhSubscriptionBilling || {};
	var allowedPeriods    = settings.allowedPeriods || [];
	var allowedIntervals  = settings.allowedIntervals || [];
	var isNewSubscription = !! settings.isNewSubscription;

	// The schedule lookup of the product on the subscription. Only the latest
	// one counts: a product removed or replaced meanwhile must not set the
	// schedule.
	var scheduleLookup = { id: 0 };

	// Requests whose answer decides the schedule (the product being added, its
	// schedule lookup), counted by reason. The subscription can't be saved
	// while any is unanswered: the Schedule box would post a value nobody checked.
	var saveHold = { reasons: {}, lockedButtons: $() };

	/**
	 * @param {jQuery}  $select
	 * @param {Array}   allowedValues
	 * @param {string}  defaultValue
	 * @param {boolean} forceDefault  True on a never-saved subscription:
	 *   WC_Subscription defaults billing_interval to 1 and the template marks
	 *   that option selected, indistinguishable from a real saved value.
	 */
	function restrictSelect( $select, allowedValues, defaultValue, forceDefault ) {
		if ( ! $select.length || ! allowedValues.length ) {
			return;
		}

		// [selected] is the attribute PHP writes for a saved value; with none,
		// .val() just reports the first option (e.g. "day").
		var hasSavedValue   = ! forceDefault && $select.find( 'option[selected]' ).length > 0;
		var currentValue    = $select.val();
		var isGrandfathered = hasSavedValue && allowedValues.indexOf( currentValue ) === -1;

		$select.find( 'option' ).each( function () {
			var $option = $( this );

			if ( allowedValues.indexOf( $option.attr( 'value' ) ) !== -1 ) {
				return;
			}

			if ( isGrandfathered ) {
				$option.prop( 'disabled', true );
			} else {
				$option.remove();
			}
		} );

		// Decided from currentValue captured before the loop: once an option is
		// disabled, .val() stops reporting it.
		if ( ( forceDefault || ! hasSavedValue || ! currentValue ) && defaultValue ) {
			$select.val( defaultValue ).trigger( 'change' );
		}
	}

	/**
	 * The product's schedule is asked to the server when the product is added
	 * (AH_Subscription_Admin_Product_Rules::ajax_product_schedule()), never
	 * cached with the page: a product created or edited after the subscription
	 * screen was opened must count. The product variation is the source of
	 * truth for the subscription's schedule.
	 *
	 * The product is already on the subscription when this runs, so a lookup
	 * that fails must be said out loud: the Schedule box would otherwise keep
	 * its previous (or default) value with no hint that it wasn't checked.
	 *
	 * @param {string}   productId
	 * @param {Function} done Called with {period, interval, name, nameWarning}.
	 */
	function fetchProductSchedule( productId, done ) {
		var lookupId = ++scheduleLookup.id;
		var isLatest = function () {
			return lookupId === scheduleLookup.id;
		};
		var failed   = function () {
			showNotice( 'error', 'The billing schedule of the product just added could not be read. Check the Schedule and the Next payment date before saving.' );
		};

		if ( ! settings.ajaxUrl || ! settings.scheduleNonce ) {
			failed();
			return;
		}

		holdSave( 'lookup' );

		$.ajax( {
			type    : 'POST',
			url     : settings.ajaxUrl,
			timeout : 15000,
			data    : {
				action     : 'bh_subscription_product_schedule',
				security   : settings.scheduleNonce,
				product_id : productId
			}
		} ).done( function ( response ) {
			if ( ! isLatest() ) {
				return;
			}

			if ( response && response.success && response.data ) {
				done( response.data );
			} else {
				failed();
			}
		} ).fail( function () {
			if ( isLatest() ) {
				failed();
			}
		} ).always( function () {
			// A superseded or cancelled lookup was already released when it was replaced.
			if ( isLatest() ) {
				releaseSave( 'lookup' );
			}
		} );
	}

	/**
	 * WooCommerce removes products, fees and shipping lines with the same
	 * request (woocommerce_remove_order_item): tell whether it takes a product
	 * row away. The row is still on the page when the request is sent.
	 *
	 * @param {string} requestData The request's serialized data.
	 * @return {boolean}
	 */
	function removesProductRow( requestData ) {
		var removes = false;

		new URLSearchParams( requestData ).forEach( function ( value, key ) {
			if ( ! /^order_item_ids(\[\d*\])?$/.test( key ) ) {
				return;
			}

			String( value ).split( ',' ).forEach( function ( id ) {
				if ( /^\d+$/.test( id ) && $( '#woocommerce-order-items tr.item[data-order_item_id="' + id + '"]' ).length ) {
					removes = true;
				}
			} );
		} );

		return removes;
	}

	/**
	 * Drop a lookup whose product is gone. Only the given lookup: a newer one
	 * belongs to another product and must finish.
	 *
	 * @param {number} lookupId The lookup that was current when the removal started.
	 */
	function cancelScheduleLookup( lookupId ) {
		if ( lookupId !== scheduleLookup.id ) {
			return;
		}

		scheduleLookup.id++;
		releaseSave( 'lookup' );
	}

	/**
	 * @return {boolean} Whether a request that decides the schedule is unanswered.
	 */
	function isSaveHeld() {
		return Object.keys( saveHold.reasons ).some( function ( reason ) {
			return saveHold.reasons[ reason ] > 0;
		} );
	}

	/**
	 * While such a request is unanswered the Schedule box still shows the
	 * previous (or default) schedule, and WooCommerce Subscriptions saves
	 * whatever it posts: the save buttons are held until it is answered.
	 *
	 * @param {string} reason 'add' (product being added) or 'lookup' (its schedule).
	 */
	function holdSave( reason ) {
		var wasHeld = isSaveHeld();

		// One lookup counts at a time: a newer one replaces the previous.
		saveHold.reasons[ reason ] = 'lookup' === reason ? 1 : ( saveHold.reasons[ reason ] || 0 ) + 1;

		if ( ! wasHeld ) {
			saveHold.lockedButtons = $( '#billing-schedule' ).closest( 'form' ).find( ':submit:enabled' ).prop( 'disabled', true );
		}
	}

	/**
	 * @param {string} reason
	 */
	function releaseSave( reason ) {
		if ( ! saveHold.reasons[ reason ] ) {
			return;
		}

		saveHold.reasons[ reason ]--;

		if ( ! isSaveHeld() ) {
			saveHold.lockedButtons.prop( 'disabled', false );
			saveHold.lockedButtons = $();
		}
	}

	function formatSchedule( interval, period ) {
		return '1' === String( interval ) ? 'every ' + period : 'every ' + interval + ' ' + period + 's';
	}

	function isSelectable( $select, value ) {
		var $option = $select.find( 'option' ).filter( function () {
			return $( this ).attr( 'value' ) === String( value );
		} );

		return $option.length > 0 && ! $option.prop( 'disabled' );
	}

	function showNotice( type, message ) {
		var $box = $( '#billing-schedule' );

		if ( ! $box.length ) {
			return;
		}

		$box.find( '.bh-product-schedule-notice' ).remove();
		$( '<div class="notice inline bh-product-schedule-notice" style="margin:8px 0;"><p></p></div>' )
			.addClass( 'notice-' + type )
			.find( 'p' ).text( message ).end()
			.prependTo( $box );
	}

	/**
	 * Fill the Schedule box from the product just added to the subscription.
	 *
	 * @param {{period: string, interval: string, name: string, nameWarning: string}} schedule
	 */
	function applyProductSchedule( schedule ) {
		var $period   = $( '#_billing_period' );
		var $interval = $( '#_billing_interval' );
		var target    = formatSchedule( schedule.interval, schedule.period );

		// The product's name and its configured schedule disagree (e.g. a "6 Month"
		// variation duplicated from the 3-month one): the schedule is still copied
		// from the variation, but CS must fix the product first.
		var nameWarning = schedule.nameWarning ? ' ' + schedule.nameWarning : '';


		if ( ! $period.length || ! $interval.length ) {
			showNotice( nameWarning ? 'error' : 'warning', '"' + schedule.name + '" is billed ' + target + '. This subscription\'s schedule can\'t be edited here: check that it matches.' + nameWarning );
			return;
		}

		if ( ! isSelectable( $period, schedule.period ) || ! isSelectable( $interval, schedule.interval ) ) {
			showNotice( 'error', '"' + schedule.name + '" is billed ' + target + ', which is not an allowed subscription schedule. Check the product\'s configuration before saving.' + nameWarning );
			return;
		}

		var changed = $period.val() !== schedule.period || $interval.val() !== String( schedule.interval );

		$period.val( schedule.period ).trigger( 'change' );
		$interval.val( String( schedule.interval ) ).trigger( 'change' );

		// The red "saved schedule does not match" box describes what was saved;
		// the form now matches the product, so it no longer applies.
		if ( ! nameWarning ) {
			$( '.bh-saved-schedule-problem, #bh-saved-schedule-problem-style' ).remove();
		}

		if ( nameWarning ) {
			showNotice( 'error', 'Schedule set to ' + target + ' from "' + schedule.name + '".' + nameWarning );
		} else if ( isNewSubscription ) {
			showNotice( 'info', 'Schedule set from "' + schedule.name + '": ' + target + '.' );
		} else if ( changed ) {
			// Existing subscription: WooCommerce Subscriptions doesn't move the
			// next payment date when the schedule changes.
			showNotice( 'warning', 'Schedule changed to ' + target + ' to match "' + schedule.name + '". Check the Next payment date before saving: it is not recalculated.' );
		} else {
			showNotice( 'info', 'Schedule already matches "' + schedule.name + '": ' + target + '.' );
		}
	}

	/**
	 * A subscription holds a single unit of its product: lock the quantity to 1
	 * in the "Add product(s)" modal and in the item's inline edit. An old item
	 * that already has more than 1 is left editable (only downwards makes
	 * sense; the server saves any changed quantity above 1 as 1). An old item
	 * stored below 1 (a fraction, kept as 0) is not posted at all, so saving
	 * the subscription for another reason keeps what it has: posting 1 would
	 * rewrite it and posting 0 makes WooCommerce remove the item.
	 */
	function lockQuantities() {
		$( '.wc-backbone-modal input[name="item_qty"], #woocommerce-order-items input.quantity[name^="order_item_qty"]' ).each( function () {
			var $input  = $( this );
			var current = parseFloat( $input.attr( 'data-qty' ) || $input.val() );

			if ( isNaN( current ) ) {
				current = 1;
			}

			if ( current > 1 ) {
				return;
			}

			if ( current < 1 ) {
				$input.prop( 'disabled', true ).attr( 'title', 'Saved with a quantity below 1. It is kept as it is: remove the product and add it again to fix it.' );
				return;
			}

			$input.val( '1' ).attr( { min: 1, max: 1, title: 'A subscription holds a single unit of its product.' } ).prop( 'readonly', true );
		} );
	}

	$( function () {
		// The modal is rendered on open and gets a new row after each pick
		// (wc-enhanced-select-init); the inline edit fields show on "edit".
		$( document.body ).on( 'wc_backbone_modal_loaded wc-enhanced-select-init', lockQuantities );
		$( document ).on( 'click', '#woocommerce-order-items a.edit-order-item', function () {
			setTimeout( lockQuantities, 0 );
		} );
		$( document ).ajaxComplete( function () {
			setTimeout( lockQuantities, 0 );
		} );
		lockQuantities();

		restrictSelect( $( '#_billing_period' ), allowedPeriods, settings.defaultPeriod, isNewSubscription );
		restrictSelect( $( '#_billing_interval' ), allowedIntervals, settings.defaultInterval, isNewSubscription );

		// A submit that doesn't come from the held buttons (e.g. another script)
		// waits too. Capture phase: before the form's other submit handlers.
		var form = $( '#billing-schedule' ).closest( 'form' ).get( 0 );

		if ( form ) {
			form.addEventListener( 'submit', function ( event ) {
				if ( isSaveHeld() ) {
					event.preventDefault();
					event.stopImmediatePropagation();
					showNotice( 'warning', 'The product is still being added and its billing schedule read. Save again in a moment.' );
				}
			}, true );
		}

		var isRequest = function ( options, action ) {
			return !! options && 'string' === typeof options.data && -1 !== options.data.indexOf( 'action=' + action );
		};

		$( document ).ajaxSend( function ( event, xhr, options ) {
			// The product is on the subscription as soon as the server handles
			// this request: nothing may be saved until it answers.
			if ( isRequest( options, 'woocommerce_add_order_item' ) ) {
				options.bhHoldsSave = true;
				holdSave( 'add' );
			}

			// Removing the product (also the first step of replacing it) makes
			// its lookup obsolete, but only once the removal has happened: note
			// which lookup is current now, and decide when the server answers.
			// Removing a fee or a shipping line never does: the product stays.
			if ( isRequest( options, 'woocommerce_remove_order_item' ) && removesProductRow( options.data ) ) {
				options.bhLookupAtRemoval = scheduleLookup.id;
			}
		} );

		// The removal was answered. If it failed, the product is still there and
		// its lookup must finish; if it worked, drop that lookup (not a newer one).
		$( document ).ajaxComplete( function ( event, xhr, options ) {
			if ( ! options || undefined === options.bhLookupAtRemoval ) {
				return;
			}

			var response = xhr && xhr.responseJSON;

			if ( response && response.success ) {
				cancelScheduleLookup( options.bhLookupAtRemoval );
			}
		} );

		// "Add item(s)" → "Add product(s)" posts action=woocommerce_add_order_item
		// with every modal row (data[0][id], data[1][id]…), including the empty
		// row the modal appends after each pick. Only one product per request is
		// accepted (AH_Subscription_Admin_Product_Rules), so the first row with an
		// id is the product added.
		$( document ).ajaxComplete( function ( event, xhr, options ) {
			if ( ! isRequest( options, 'woocommerce_add_order_item' ) ) {
				return;
			}

			var response  = xhr && xhr.responseJSON;
			var productId = null;

			if ( response && response.success ) {
				new URLSearchParams( options.data ).forEach( function ( value, key ) {
					if ( ! productId && value && /^data\[\d+\]\[id\]$/.test( key ) ) {
						productId = value;
					}
				} );
			}

			// The lookup takes over the hold before the add request lets go of
			// it, so there is no moment in between when saving is possible.
			if ( productId ) {
				fetchProductSchedule( productId, applyProductSchedule );
			}

			if ( options.bhHoldsSave ) {
				options.bhHoldsSave = false;
				releaseSave( 'add' );
			}
		} );
	} );

})( jQuery );
