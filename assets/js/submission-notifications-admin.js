/* global nrfmNotifications */
(function($) {
	'use strict';

	if (typeof nrfmNotifications === 'undefined') {
		return;
	}

	var data = nrfmNotifications;

	function formatBadge(count) {
		if (!data.badgeLabel) {
			return String(count);
		}
		return data.badgeLabel.replace('%d', count);
	}

	function addBadge($target, count) {
		if (!count || !$target || !$target.length) {
			return;
		}
		var $badge = $('<span class="nrfm-unread-badge"></span>').text(formatBadge(count));
		$target.append($badge);
	}

	function addTabBadge(count) {
		var total = parseInt(count, 10);
		if (!total) {
			return;
		}
		var $tab = $('.nrfm-tabs a[data-tab="submissions"]');
		if (!$tab.length || $tab.find('.nrfm-tab-badge').length) {
			return;
		}
		$tab.append($('<span class="nrfm-tab-badge"></span>').text(formatBadge(total)));
	}

	function addBulkActionOption() {
		if (!data.bulkActionValue || !data.bulkActionLabel) {
			return;
		}
		$('select[name="action"], select[name="action2"]').each(function() {
			var $select = $(this);
			if ($select.find('option[value="' + data.bulkActionValue + '"]').length) {
				return;
			}
			$select.append(
				$('<option></option>').val(data.bulkActionValue).text(data.bulkActionLabel)
			);
		});
	}

	function markUnreadRows(unreadIds, markSeenUrls) {
		if (!Array.isArray(unreadIds) || !unreadIds.length) {
			return;
		}
		unreadIds.forEach(function(id) {
			var $checkbox = $('input[name="submission_ids[]"][value="' + id + '"]');
			if (!$checkbox.length) {
				return;
			}
			var $row = $checkbox.closest('tr');
			$row.addClass('nrfm-submission-unread');
			var url = markSeenUrls && markSeenUrls[id];
			if (url) {
				var $actionsCell = $row.find('td.column-actions');
				if ($actionsCell.length && !$actionsCell.find('.nrfm-mark-seen').length) {
					$actionsCell.append(
						' <a class="button button-small nrfm-mark-seen" href="' + url + '">' + data.bulkActionLabel + '</a>'
					);
				}
			}
		});
	}

	function addListBadges(listCounts) {
		if (!listCounts) {
			return;
		}
		$('.wp-list-table tbody tr').each(function() {
			var $row = $(this);
			var formId = $row.find('input[name="form[]"]').val();
			if (!formId || !listCounts[formId]) {
				return;
			}
			var $title = $row.find('a.row-title');
			addBadge($title, listCounts[formId]);
		});
	}

	if (data.isFormsList && data.listUnreadCounts) {
		addListBadges(data.listUnreadCounts);
	}

	if (data.isEditPage && typeof data.tabUnreadCount !== 'undefined') {
		addTabBadge(data.tabUnreadCount);
	}

	if (data.isSubmissionsTab) {
		addBulkActionOption();
		markUnreadRows(data.unreadIds || [], data.markSeenUrls || {});
	}
})(jQuery);
