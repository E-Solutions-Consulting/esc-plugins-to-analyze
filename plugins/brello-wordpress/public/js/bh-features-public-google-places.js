(function( $ ) {
	'use strict';
	//console.log('script google places');
	jQuery(document).ready(function ($) {

		function initializeAutocomplete() {
			try {
				const billingInput = document.getElementById('billing_address_1');
				const shippingInput = document.getElementById('shipping_address_1');
		
				if (!billingInput || !shippingInput) throw new Error("No se encontraron los campos de dirección.");
		
				const autocompleteBilling = new google.maps.places.Autocomplete(billingInput, {
					types: ['geocode'],
					componentRestrictions: { country: 'us' }
				});

				const autocompleteShipping = new google.maps.places.Autocomplete(shippingInput, {
					types: ['geocode'],
					componentRestrictions: { country: 'us' }
				});

				autocompleteBilling.addListener('place_changed', () => validateAndFillCheckout('billing', autocompleteBilling));
				autocompleteShipping.addListener('place_changed', () => validateAndFillCheckout('shipping', autocompleteShipping));
			} catch (error) {
				console.error("Error inicializando Google Autocomplete:", error);
			}
		}

		function validateAndFillCheckout(type, autocompleteInstance) {
			const place = autocompleteInstance.getPlace();
		
			if (!place || !place.address_components) {
				alert("Please select a valid address.");
				return;
			}

			const state = getStateFromPlace(place);
		
			if (!allowedStates.includes(state)) {
				alert("State not allowed");
				document.getElementById(`${type}_address_1`).value = "";
				return;
			}
			fillCheckoutFields(type, place);
		}
		
		function fillCheckoutFields(type, place) {
			const addressComponents = getAddressComponents(place);
			if (!addressComponents) return;
		
			//console.log('addressComponents', addressComponents);
			document.getElementById(`${type}_address_1`).value = addressComponents.street;
			document.getElementById(`${type}_city`).value = addressComponents.city;
			document.getElementById(`${type}_state`).value = addressComponents.state;
			document.getElementById(`${type}_postcode`).value = addressComponents.postcode;
			document.getElementById(`${type}_country`).value = "US";
		
			jQuery(`#${type}_address_1, #${type}_city, #${type}_state, #${type}_postcode, #${type}_country`).trigger('change');
		}

		function getAddressComponents(place) {
			const components = {};
			place.address_components.forEach(component => {
				if (component.types.includes('street_number')) {
					components.street_number = component.long_name;
				} else if (component.types.includes('route')) {
					components.route = component.long_name;
				} else if (component.types.includes('locality')) {
					components.city = component.long_name;
				} else if (component.types.includes('administrative_area_level_1')) {
					components.state = component.short_name;
				} else if (component.types.includes('postal_code')) {
					components.postcode = component.long_name;
				}
			});
		
			components.street = [components.street_number, components.route].filter(Boolean).join(' ');
			return components;
		}
		
		function getStateFromPlace(place) {
			for (const component of place.address_components) {
				if (component.types.includes("administrative_area_level_1")) {
					return component.short_name;
				}
			}
			return null;
		}

		try {
			initializeAutocomplete();
		} catch (error) {
			console.error("Error al inicializar todo el proceso:", error);
		}
		
	});
})( jQuery );
