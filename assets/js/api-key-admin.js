/* Narrative Forms Pro - REST API key Show / Copy controls */
(function(){
	'use strict';

	var L = window.nrfmApiKey || { showText: 'Show', hideText: 'Hide', copyText: 'Copy', copiedText: 'Copied' };

	function flash(btn, text, revertTo) {
		btn.textContent = text;
		setTimeout(function(){ btn.textContent = revertTo; }, 1200);
	}

	function legacyCopy(input) {
		var prevType = input.type;
		input.type = 'text';
		input.focus();
		input.select();
		try { document.execCommand('copy'); } catch (err) { /* no-op */ }
		input.type = prevType;
		window.getSelection && window.getSelection().removeAllRanges();
	}

	document.addEventListener('click', function(e){
		var toggle = e.target.closest('.nrfm-api-key-toggle');
		if (toggle) {
			e.preventDefault();
			var field = document.getElementById(toggle.getAttribute('data-target'));
			if (!field) { return; }
			var reveal = field.type === 'password';
			field.type = reveal ? 'text' : 'password';
			toggle.textContent = reveal ? L.hideText : L.showText;
			toggle.setAttribute('aria-pressed', reveal ? 'true' : 'false');
			return;
		}

		var copy = e.target.closest('.nrfm-api-key-copy');
		if (copy) {
			e.preventDefault();
			var input = document.getElementById(copy.getAttribute('data-target'));
			if (!input) { return; }
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(input.value).then(function(){
					flash(copy, L.copiedText, L.copyText);
				}).catch(function(){
					legacyCopy(input);
					flash(copy, L.copiedText, L.copyText);
				});
			} else {
				legacyCopy(input);
				flash(copy, L.copiedText, L.copyText);
			}
		}
	});
})();
