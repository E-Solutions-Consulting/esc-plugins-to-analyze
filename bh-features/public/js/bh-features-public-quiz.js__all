(function( $ ) {
	'use strict';
	var currentContainerCheckboxes;
	var _total_questions;
	
	jQuery(document).on('qsm_init_progressbar_before', function(event, quizID, qmn_quiz_data) {		
		jQuery.each(qmn_quiz_data[quizID].qpages, function (numberQpage, qpage) {
			jQuery('.qsm-page-' + numberQpage).addClass(qpage.pagekey);
		});
		_total_questions	=	_.keys(qmn_quiz_data[quizID].qpages).length;
	});
	jQuery(document).on('qsm_go_to_page_before', function(event, quizID, pageNumber) {
		var $quizForm = QSM.getQuizForm(quizID);
		var $pages = $quizForm.children('.qsm-page');
			$pages.removeClass('fade-in');
	});
	jQuery(document).on('qsm_go_to_page_after', function(event, quizID, pageNumber) {
		const section = document.querySelector('section[data-qpid="' + pageNumber + '"]');
		const is_section_radio = Boolean(section && section.querySelector('.qmn_radio_answers'));
		const btn_continue	=	jQuery('.qsm-btn.mlw_custom_next');
		if (is_section_radio) {
			btn_continue.hide();
		} else {
			btn_continue.show();
		}
	});
	jQuery(document).on('qsm_next_button_click_before', function (event, quizID) {
		var quiz_form_id = '#quizForm' + quizID;
		var $container			=	jQuery(quiz_form_id).closest('.qmn_quiz_container');
		var page_number 		=	$container.find('.current_page_hidden').val();
		let page_number_current = parseInt(page_number) + 1;
		console.log('page_number_current', page_number_current, 'total_questions', _total_questions);
		if((page_number_current + 1)==_total_questions){
			setTimeout(function () {
				jQuery('.qsm-btn.qmn_btn.mlw_qmn_quiz_link').hide();
			},100);
		}
	});
	jQuery(document).on('qsm_before_display_result', function(event,results, quiz_form_id, $container){
		try{
			if(typeof hb_custom_redirect!=='undefined' && hb_custom_redirect !== null && hb_custom_redirect.trim() !== '' ){
				console.log('document', hb_custom_redirect);
				results.redirect	=	hb_custom_redirect;		
			}
		} catch (error) {
			console.error("Error redirect URL:", error);
		}
		

	});
	
	document.body.addEventListener('click', function (e) {
		if (e.target.matches('label.qsm-input-label')) {
		const inputId = e.target.getAttribute('for');
		const input = document.getElementById(inputId);
		
		if (
			input &&
			input.type === 'radio' &&
			input.style.display !== 'none' &&
			input.id !== 'question1_none'
		) {
			setTimeout(function () {
			
				const nextBtn = document.querySelector('.qsm-btn.mlw_custom_next');
				if (nextBtn) nextBtn.click();
			}, 200);
		}
		}
	});

	document.body.addEventListener('change', function (e) {
		if (!e.target.matches('input[type="checkbox"].qsm-multiple-response-input')) return;

		const clickedInput = e.target;
		const container = clickedInput.closest('.qmn_check_answers');
		if (!container) return;

		currentContainerCheckboxes	=	container;

		const label = container.querySelector('label[for="' + clickedInput.id + '"]');
		if (!label) return;

		const allCheckboxes = container.querySelectorAll('input[type="checkbox"].qsm-multiple-response-input');
		const noneCheckbox = Array.from(allCheckboxes).find(cb => {
			const lbl = container.querySelector('label[for="' + cb.id + '"]');
			return lbl && lbl.textContent.trim().toLowerCase() === 'none';
		});

		if (clickedInput === noneCheckbox && clickedInput.checked) {
			allCheckboxes.forEach(cb => {
				if (cb !== noneCheckbox) {
					cb.checked = false;
					cb.disabled = true;
				}
			});
			jQuery('html, body').animate({
				scrollTop: currentContainerCheckboxes.scrollHeight / 2
			}, 500);
		}
		if (clickedInput === noneCheckbox && !clickedInput.checked) {
			allCheckboxes.forEach(cb => {
				if (cb !== noneCheckbox) {
					cb.disabled = false;
				}
			});
		}

		if (clickedInput !== noneCheckbox && clickedInput.checked) {
			if (noneCheckbox) {
				noneCheckbox.checked = false;
				noneCheckbox.disabled = false;
			}
		}
	});

	document.addEventListener('change', function (e) {
		if (e.target.matches('input[type="checkbox"]')) {
			const clickedInput = e.target;
			const label = clickedInput.nextElementSibling;

			if (label && label.textContent.trim().toLowerCase() === 'all of the above') {
				const container = clickedInput.closest('.qmn_check_answers');
				if (!container) return;

				currentContainerCheckboxes	=	container;

				const checkboxes = container.querySelectorAll('input[type="checkbox"]:not([id="' + clickedInput.id + '"])');

				checkboxes.forEach(cb => {
					cb.checked = clickedInput.checked;
				});

				jQuery('html, body').animate({
					scrollTop: currentContainerCheckboxes.scrollHeight / 2
				}, 500);
			}
		}
	});

	const waitForSection = setInterval(() => {
		const section = document.querySelector('section.qsm-page.qsm-question-page.gender-box');
		if (section) {
			const options = section.querySelectorAll('.mrq_checkbox_class');
			options.forEach(container => {
				const label = container.querySelector('label');
				if (label && !container.querySelector('.gender-label')) {
					const text = label.innerText.trim().toLowerCase();
					label.className="qsm-input-label " + text;
				}
			});
			clearInterval(waitForSection);
		}
	}, 300);

	document.addEventListener('DOMContentLoaded', function () {
		const pages = document.querySelectorAll('.qsm-page');
		pages.forEach(page => {
			page.classList.remove('fade-in');
			const observer = new MutationObserver(() => {
				const isVisible = window.getComputedStyle(page).display !== 'none';
				if (isVisible) {
					page.classList.remove('fade-in');
					void page.offsetWidth;
					page.classList.add('fade-in');
				}
			});
			observer.observe(page, { attributes: true, attributeFilter: ['style'] });
		});
	});
	
	(function() {
		const observer = new MutationObserver(mutations => {
			mutations.forEach(mutation => {
				mutation.addedNodes.forEach(node => {
					if (
						node.classList &&
						node.classList.contains('qsm-page')
					) {
						node.classList.remove('fade-in');
						void node.offsetWidth;
						node.classList.add('fade-in');
					}
				});
			});
		});

		observer.observe(document.body, {
			childList: true,
			subtree: true
		});
	})();

})( jQuery );
