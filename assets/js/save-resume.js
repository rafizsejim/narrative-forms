/* global nrfmSaveResume, nrfm_ajax */
(function() {
	'use strict';

	if (typeof nrfmSaveResume === 'undefined') {
		return;
	}

	function $(selector, context) {
		return Array.prototype.slice.call((context || document).querySelectorAll(selector));
	}

	function showMessage(container, message, isError) {
		if (!container) return;
		container.textContent = message;
		container.style.color = isError ? '#b32d2e' : '#1d2327';
	}

	function positionSaveButton(form) {
		var allActions = form.querySelectorAll('.nrfm-save-resume-actions');
		if (!allActions.length) {
			return;
		}
		var actions = allActions[0];
		// Safeguard: a form must never show more than one save link.
		for (var i = 1; i < allActions.length; i++) {
			if (allActions[i].parentNode) {
				allActions[i].parentNode.removeChild(allActions[i]);
			}
		}
		if (actions.dataset.placed === '1') {
			return;
		}
		var submit = form.querySelector('button[type="submit"], input[type="submit"]');
		if (!submit) {
			return;
		}
		submit.insertAdjacentElement('afterend', actions);
		actions.dataset.placed = '1';
	}

	function populateDraft(form, payload) {
		if (!payload || !payload.fields) return;
		var fields = payload.fields;

		Object.keys(fields).forEach(function(name) {
			var value = fields[name];
			var inputs = $(('[name="' + name + '"]'), form).concat($(('[name="' + name + '[]"]'), form));
			if (!inputs.length) {
				return;
			}

			inputs.forEach(function(input) {
				if (input.type === 'file') {
					return;
				}
				if (input.type === 'checkbox') {
					if (Array.isArray(value)) {
						input.checked = value.indexOf(input.value) !== -1;
					} else {
						input.checked = String(value) === String(input.value) || Boolean(value);
					}
					return;
				}
				if (input.type === 'radio') {
					input.checked = String(value) === String(input.value);
					return;
				}
				if (input.tagName === 'SELECT' && input.multiple && Array.isArray(value)) {
					Array.prototype.slice.call(input.options).forEach(function(option) {
						option.selected = value.indexOf(option.value) !== -1;
					});
					return;
				}
				input.value = Array.isArray(value) ? value.join(', ') : value;
			});
		});

		var tokenInput = form.querySelector('input[name="nrfm_resume_token"]');
		if (tokenInput && payload.token) {
			tokenInput.value = payload.token;
		}
		var message = form.querySelector('.nrfm-save-resume-message');
		showMessage(message, nrfmSaveResume.messageLoaded || 'Draft loaded.', false);
	}

	function handleSaveClick(event) {
		var button = event.target.closest('.nrfm-save-resume');
		if (!button) return;
		event.preventDefault();
		var form = button.closest('form.nrfm-form');
		if (!form || form.dataset.nrfmSubmitting === '1') {
			return;
		}
		var messageEl = form.querySelector('.nrfm-save-resume-message');
		var formData = new FormData(form);
		var fileInputs = $('input[type="file"][name]', form);
		fileInputs.forEach(function(input) {
			formData.delete(input.name);
			var base = input.name.replace(/\[\]$/, '');
			formData.delete(base);
			formData.delete(base + '[]');
		});
		formData.append('action', 'nrfm_save_resume');
		formData.append('nrfm_ajax_nonce', (nrfm_ajax && nrfm_ajax.nonce) ? nrfm_ajax.nonce : nrfmSaveResume.ajaxNonce);
		formData.append('nrfm_resume_base', window.location.href.split('#')[0]);

		button.disabled = true;
		fetch(nrfmSaveResume.ajaxUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin'
		})
		.then(function(response) { return response.json(); })
		.then(function(data) {
			button.disabled = false;
			if (!data || !data.success || !data.data) {
				throw new Error('Save failed');
			}
			var tokenInput = form.querySelector('input[name="nrfm_resume_token"]');
			if (tokenInput && data.data.token) {
				tokenInput.value = data.data.token;
			}
			var resumeUrl = data.data.resume_url || '';
			var prefix = messageEl && messageEl.dataset.savedText ? messageEl.dataset.savedText : (nrfmSaveResume.messageSaved || 'Draft saved. Use this link to resume:');
			if (messageEl) {
				messageEl.textContent = '';
				var text = document.createElement('div');
				text.textContent = prefix;
				var link = document.createElement('a');
				link.href = resumeUrl;
				link.textContent = resumeUrl;
				link.rel = 'noopener';
				link.style.display = 'block';
				link.style.marginTop = '4px';
				messageEl.appendChild(text);
				messageEl.appendChild(link);
				messageEl.style.color = '#1d2327';
			} else {
				showMessage(messageEl, prefix + ' ' + resumeUrl, false);
			}
		})
		.catch(function() {
			button.disabled = false;
			showMessage(messageEl, nrfmSaveResume.messageError || 'Unable to save your progress. Please try again.', true);
		});
	}

	function init() {
		document.addEventListener('click', handleSaveClick);
		$('.nrfm-form').forEach(function(form) {
			positionSaveButton(form);
		});
		if (window.nrfmResumeDrafts) {
			Object.keys(window.nrfmResumeDrafts).forEach(function(formId) {
				var payload = window.nrfmResumeDrafts[formId];
				var form = document.querySelector('.nrfm-form[data-form-id="' + formId + '"]');
				if (form) {
					positionSaveButton(form);
					populateDraft(form, payload);
				}
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
