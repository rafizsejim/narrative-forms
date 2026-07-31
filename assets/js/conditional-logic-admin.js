/* Narrative Forms - Conditional Logic (Admin) */
(function($){
	'use strict';

	function toggleValueInput($row) {
		var op = $row.find('select[name*="[operator]"]').val();
		var $val = $row.find('input[name*="[value]"]');
		if (op === 'empty' || op === 'not_empty') {
			$val.val('').prop('disabled', true);
		} else {
			$val.prop('disabled', false);
		}
	}

	$(document).on('click', '#nrfm-add-condition', function(){
		var $btn = $(this);
		var next = parseInt($btn.data('next-index'), 10) || 0;
		var tpl = document.getElementById('nrfm-conditional-row-template');
		if (!tpl) { return; }
		var html = tpl.innerHTML.replace(/__INDEX__/g, String(next));
		$('#nrfm-conditional-rows').append(html);
		$btn.data('next-index', next + 1);
		var $row = $('#nrfm-conditional-rows tr').last();
		toggleValueInput($row);
	});

	$(document).on('click', '.nrfm-remove-condition', function(){
		$(this).closest('tr').remove();
	});

	$(document).on('change', 'select[name*="[operator]"]', function(){
		toggleValueInput($(this).closest('tr'));
	});

	$(function(){
		$('#nrfm-conditional-rows tr').each(function(){
			toggleValueInput($(this));
		});
	});
})(jQuery);
