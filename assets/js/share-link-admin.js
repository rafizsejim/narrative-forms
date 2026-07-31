/* Narrative Forms Pro - Share Link Admin */
(function(){
	'use strict';

	var L = window.nrfmShareLink || { copyText: 'Copy Link', copiedText: 'Copied' };

	document.addEventListener('click', function(e){
		var btn = e.target.closest('#nrfm-copy-share-link');
		if (!btn) { return; }
		e.preventDefault();
		var targetId = btn.getAttribute('data-target');
		var input = targetId ? document.getElementById(targetId) : null;
		if (!input) { return; }
		input.focus();
		input.select();
		try {
			document.execCommand('copy');
			btn.textContent = L.copiedText;
			setTimeout(function(){ btn.textContent = L.copyText; }, 1200);
		} catch (err) {
			// Fallback: keep selected so user can copy manually
		}
	});
})();
