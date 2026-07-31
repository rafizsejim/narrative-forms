/* Narrative Forms — AI form builder (admin) */
(function () {
	'use strict';

	var cfg = window.nrfmAI || {};
	var i18n = cfg.i18n || {};
	var storeKey = 'nrfm_ai_versions_' + ( cfg.formId || '0' );

	// Each entry is a checkpoint: { instruction, html }. This single array drives
	// the transcript, the "Revert" version history, and the AI context — and it is
	// mirrored to localStorage so it survives a reload.
	var versions = loadVersions();
	var busy = false;
	var statusTimer = null;
	var startedAt = 0;

	function byId( id ) { return document.getElementById( id ); }

	// --- editor (reach CodeMirror via the DOM; setValue fires core's preview) ---
	function getCM() {
		var el = document.querySelector( '#tab-fields .CodeMirror' );
		return el && el.CodeMirror ? el.CodeMirror : null;
	}
	function getEditorValue() {
		var cm = getCM();
		if ( cm ) { return cm.getValue(); }
		var ta = byId( 'nrfm-form-content' );
		return ta ? ta.value : '';
	}
	function setEditorValue( html ) {
		var cm = getCM();
		if ( cm ) { cm.setValue( html ); cm.focus(); return; }
		var ta = byId( 'nrfm-form-content' );
		if ( ta ) { ta.value = html; }
	}

	// --- persistence ---
	function loadVersions() {
		try {
			var raw = window.localStorage.getItem( storeKey );
			var v = raw ? JSON.parse( raw ) : [];
			return Array.isArray( v ) ? v : [];
		} catch ( e ) { return []; }
	}
	function saveVersions() {
		try { window.localStorage.setItem( storeKey, JSON.stringify( versions.slice( -30 ) ) ); } catch ( e ) {}
	}

	// --- transcript + version history ---
	function renderTranscript() {
		var box = byId( 'nrfm-ai-transcript' );
		if ( ! box ) { return; }
		box.innerHTML = '';
		versions.forEach( function ( v ) {
			var line = document.createElement( 'div' );
			line.className = 'nrfm-ai-line';

			var who = document.createElement( 'span' );
			who.className = 'nrfm-ai-who';
			who.textContent = ( i18n.you || 'You' ) + ' ▸ ';
			line.appendChild( who );
			line.appendChild( document.createTextNode( v.instruction ) );

			var revert = document.createElement( 'button' );
			revert.type = 'button';
			revert.className = 'button-link nrfm-ai-revert';
			revert.textContent = i18n.revert || 'Revert';
			revert.addEventListener( 'click', function () {
				setEditorValue( v.html );
				setStatus( i18n.reverted || '' );
			} );
			line.appendChild( document.createTextNode( ' ' ) );
			line.appendChild( revert );

			box.appendChild( line );
		} );
		box.scrollTop = box.scrollHeight;
	}

	function contextHistory() { return versions.map( function ( v ) { return v.instruction; } ).slice( -6 ); }
	function lastInstruction() { return versions.length ? versions[ versions.length - 1 ].instruction : ''; }

	// --- status (rotating reassurance + elapsed seconds + spinner) ---
	function setSpinner( on ) {
		var s = byId( 'nrfm-ai-spinner' );
		if ( s ) { s.className = 'spinner nrfm-ai-spinner' + ( on ? ' is-active' : '' ); }
	}
	function setStatus( msg ) {
		var s = byId( 'nrfm-ai-status' );
		if ( s ) { s.textContent = msg || ''; }
	}
	function startStatus() {
		setSpinner( true );
		startedAt = Date.now();
		tickStatus();
		statusTimer = window.setInterval( tickStatus, 1000 );
	}
	function tickStatus() {
		var list = ( i18n.statuses && i18n.statuses.length ) ? i18n.statuses : [ 'Generating…' ];
		var elapsed = Math.round( ( Date.now() - startedAt ) / 1000 );
		var msg = list[ Math.floor( elapsed / 4 ) % list.length ];
		setStatus( msg + ' (' + elapsed + 's)' );
	}
	function stopStatus( finalMsg ) {
		if ( statusTimer ) { window.clearInterval( statusTimer ); statusTimer = null; }
		setSpinner( false );
		setStatus( finalMsg || '' );
	}

	function setBusy( on ) {
		busy = on;
		var g = byId( 'nrfm-ai-generate' );
		var r = byId( 'nrfm-ai-regenerate' );
		if ( g ) { g.disabled = on || ! cfg.configured; }
		if ( r ) { r.disabled = on || ! cfg.configured || ! lastInstruction(); }
	}

	function errorMessage( code ) {
		var e = i18n.errors || {};
		switch ( code ) {
			case 'not_configured': return e.not_configured || i18n.genericError || '';
			case 'prompt_too_long': return e.prompt_too_long || i18n.genericError || '';
			case 'timeout': return e.timeout || i18n.genericError || '';
			case 'provider_error': return e.provider_error || i18n.genericError || '';
			case 'empty_output': return e.empty_output || i18n.genericError || '';
			default: return i18n.genericError || '';
		}
	}

	// --- generate: the browser posts to a WP AJAX endpoint; the server forwards
	//     the request to the configured provider (the key never leaves the server) ---
	function generate( instruction ) {
		if ( busy ) { return; }
		instruction = ( instruction || '' ).trim();
		if ( ! instruction ) { setStatus( i18n.emptyPrompt || '' ); return; }
		if ( ! cfg.configured ) { setStatus( errorMessage( 'not_configured' ) ); return; }

		setBusy( true );
		startStatus();

		var body = new FormData();
		body.append( 'action', 'nrfm_ai_generate' );
		body.append( 'nonce', cfg.nonce || '' );
		body.append( 'instruction', instruction );
		body.append( 'current_html', getEditorValue() );
		body.append( 'wrapper', cfg.wrapperTag || 'p' );
		contextHistory().forEach( function ( h ) { body.append( 'history[]', h ); } );

		fetch( cfg.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( res ) {
				return res.json().catch( function () { return {}; } );
			} )
			.then( function ( json ) {
				setBusy( false );
				stopStatus();
				var data = ( json && json.data ) ? json.data : {};
				if ( json && json.success && typeof data.html === 'string' ) {
					setEditorValue( data.html );
					versions.push( { instruction: instruction, html: data.html } );
					versions = versions.slice( -30 );
					saveVersions();
					renderTranscript();
					var input = byId( 'nrfm-ai-instruction' );
					if ( input ) { input.value = ''; input.focus(); }
					setStatus( i18n.done || '' );
				} else {
					setStatus( errorMessage( data.error ) );
				}
			} )
			.catch( function () {
				setBusy( false );
				stopStatus();
				setStatus( i18n.genericError || '' );
			} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var toggle = byId( 'nrfm-ai-toggle' );
		var panel = byId( 'nrfm-ai-panel' );
		if ( ! toggle || ! panel ) { return; }

		renderTranscript();
		setBusy( false );

		toggle.addEventListener( 'click', function () {
			var opening = panel.hasAttribute( 'hidden' );
			if ( opening ) { panel.removeAttribute( 'hidden' ); } else { panel.setAttribute( 'hidden', '' ); }
			toggle.setAttribute( 'aria-expanded', opening ? 'true' : 'false' );
			if ( opening ) {
				var input = byId( 'nrfm-ai-instruction' );
				if ( input ) { input.focus(); }
			}
		} );

		var input = byId( 'nrfm-ai-instruction' );

		var gen = byId( 'nrfm-ai-generate' );
		if ( gen ) { gen.addEventListener( 'click', function () { generate( input ? input.value : '' ); } ); }

		if ( input ) {
			input.addEventListener( 'keydown', function ( e ) {
				if ( ( e.metaKey || e.ctrlKey ) && e.key === 'Enter' ) {
					e.preventDefault();
					generate( input.value );
				}
			} );
		}

		var regen = byId( 'nrfm-ai-regenerate' );
		if ( regen ) {
			regen.addEventListener( 'click', function () {
				var li = lastInstruction();
				if ( li ) { generate( li ); }
			} );
		}

		var reset = byId( 'nrfm-ai-reset' );
		if ( reset ) {
			reset.addEventListener( 'click', function () {
				versions = [];
				saveVersions();
				renderTranscript();
				if ( input ) { input.value = ''; }
				setBusy( false );
				setStatus( '' );
			} );
		}

		// Starter chips: fill the box with a starting prompt (don't auto-run).
		Array.prototype.forEach.call( document.querySelectorAll( '.nrfm-ai-chip' ), function ( chip ) {
			chip.addEventListener( 'click', function () {
				if ( ! input ) { return; }
				input.value = chip.getAttribute( 'data-prompt' ) || chip.textContent;
				input.focus();
			} );
		} );

		if ( ! cfg.configured && gen ) { gen.disabled = true; }
	} );
})();
