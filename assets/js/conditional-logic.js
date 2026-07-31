/* Narrative Forms - Conditional Logic (Frontend) */
(function(){
	'use strict';

	function escSelector(value) {
		if (window.CSS && CSS.escape) { return CSS.escape(value); }
		return value.replace(/([\!"#$%&'()*+,./:;<=>?@[\\\]^`{|}~])/g, '\\$1');
	}

	function getValues(form, name) {
		var sel = '[name="' + escSelector(name) + '"]';
		var selArray = '[name="' + escSelector(name) + '[]"]';
		var els = form.querySelectorAll(sel + ',' + selArray);
		if (!els.length) { return []; }
		var first = els[0];
		if (first.type === 'checkbox' || first.type === 'radio') {
			return Array.prototype.filter.call(els, function(el){ return el.checked; })
				.map(function(el){ return el.value || ''; });
		}
		if (first.tagName === 'SELECT' && first.multiple) {
			return Array.prototype.map.call(first.selectedOptions, function(opt){ return opt.value || ''; });
		}
		return [first.value || ''];
	}

	function matchRule(values, operator, ruleValue) {
		var hasValue = values.length > 0 && values.some(function(v){ return v !== ''; });
		var norm = function(v){ return String(v || '').toLowerCase(); };
		var ruleVal = norm(ruleValue);
		var vals = values.map(norm);
		switch (operator) {
			case 'empty': return !hasValue;
			case 'not_empty': return hasValue;
			case 'contains':
				return vals.some(function(v){ return v.indexOf(ruleVal) !== -1; });
			case 'not_equals':
				return vals.every(function(v){ return v !== ruleVal; });
			case 'greater':
			case 'less': {
				var ruleNum = parseFloat(ruleValue);
				if (isNaN(ruleNum)) { return false; }
				var nums = values.map(function(v){ return parseFloat(v); }).filter(function(v){ return !isNaN(v); });
				if (!nums.length) { return false; }
				if (operator === 'greater') {
					return nums.some(function(v){ return v > ruleNum; });
				}
				return nums.some(function(v){ return v < ruleNum; });
			}
			case 'equals':
			default:
				return vals.some(function(v){ return v === ruleVal; });
		}
	}

	function findTargets(form, name) {
		var sel = '[name="' + escSelector(name) + '"]';
		var selArray = '[name="' + escSelector(name) + '[]"]';
		return form.querySelectorAll(sel + ',' + selArray);
	}

	// Count distinct named controls inside a container (array fields like name[]
	// count once). Used to avoid hiding a container that groups several fields.
	function countNamedFields(container) {
		var names = {};
		var controls = container.querySelectorAll('input[name], select[name], textarea[name]');
		Array.prototype.forEach.call(controls, function(c){
			var n = c.getAttribute('name');
			if (n) { names[n.replace(/\[\]$/, '')] = true; }
		});
		return Object.keys(names).length;
	}

	// The wrapper to show/hide for a field: the largest ancestor block that still
	// contains only this one field. Stops before a container that groups several
	// fields (e.g. a custom multi-field card), so hiding one field never hides its
	// neighbours. Falls back to the bare control when no single-field wrapper exists.
	function findWrapper(form, el) {
		var best = el;
		var node = el.parentElement;
		while (node && node !== form) {
			if (node.tagName === 'P' || node.tagName === 'DIV' || node.tagName === 'SECTION' || node.tagName === 'ARTICLE' || node.tagName === 'FIELDSET') {
				if (countNamedFields(node) > 1) {
					break;
				}
				best = node;
			}
			node = node.parentElement;
		}
		return best;
	}

	function setVisible(form, targets, visible) {
		Array.prototype.forEach.call(targets, function(el){
			var wrap = findWrapper(form, el);
			wrap.style.display = visible ? '' : 'none';
			// When we fall back to the bare control (no single-field wrapper existed,
			// e.g. a custom multi-field card), also toggle its associated label so it
			// doesn't dangle after the control is hidden.
			if (wrap === el && el.id) {
				var label = form.querySelector('label[for="' + escSelector(el.id) + '"]');
				if (label) { label.style.display = visible ? '' : 'none'; }
			}
			// Disable hidden fields so the browser won't validate them (a hidden
			// "required" field would otherwise block submit) and their stale values
			// aren't sent. The server enforces the same via the nrfm_required_fields
			// filter, so this works even when JS is unavailable.
			el.disabled = !visible;
		});
	}

	function applyRules(form, rules, mode) {
		var decisions = {};
		var defaults = {};

		rules.forEach(function(rule){
			if (!rule || !rule.target) { return; }
			if (rule.action === 'show') {
				defaults[rule.target] = false; // hidden until condition matches
			} else if (defaults[rule.target] === undefined) {
				defaults[rule.target] = true; // visible by default for hide rules
			}
		});

		Object.keys(defaults).forEach(function(target){
			var els = findTargets(form, target);
			if (!els.length) { return; }
			setVisible(form, els, defaults[target]);
		});

		var grouped = {};
		rules.forEach(function(rule){
			if (!rule || !rule.target) { return; }
			var key = rule.target + '|' + (rule.action || 'show');
			if (!grouped[key]) { grouped[key] = []; }
			grouped[key].push(rule);
		});

		Object.keys(grouped).forEach(function(key){
			var rulesForTarget = grouped[key];
			var action = (rulesForTarget[0] && rulesForTarget[0].action) ? rulesForTarget[0].action : 'show';
			var matches = rulesForTarget.map(function(rule){
				var values = getValues(form, rule.field);
				return matchRule(values, rule.operator, rule.value || '');
			});
			var ok = (mode === 'all') ? matches.every(Boolean) : matches.some(Boolean);
			if (ok) {
				var target = rulesForTarget[0].target;
				decisions[target] = (action === 'show');
			}
		});

		Object.keys(decisions).forEach(function(target){
			var els = findTargets(form, target);
			if (!els.length) { return; }
			setVisible(form, els, decisions[target]);
		});
	}

	function bindForm(form, rules) {
		if (!rules) { return; }
		var list = Array.isArray(rules) ? rules : (rules.rules || []);
		var mode = (!Array.isArray(rules) && rules.mode) ? rules.mode : 'any';
		if (!list.length) { return; }
		var handler = function(){ applyRules(form, list, mode); };
		form.addEventListener('input', handler, true);
		form.addEventListener('change', handler, true);
		applyRules(form, list, mode);
	}

	function init() {
		var allRules = window.NRFM_ConditionalRules || {};
		Object.keys(allRules).forEach(function(formId){
			var form = document.querySelector('form.nrfm-form[data-form-id="' + escSelector(formId) + '"]');
			if (form) {
				bindForm(form, allRules[formId]);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
