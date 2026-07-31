(function() {
	'use strict';

	if (typeof nrfmWebhookLogs === 'undefined') {
		return;
	}

	document.addEventListener('DOMContentLoaded', function() {
		var actionsTab = document.getElementById('tab-actions');
		if (!actionsTab) {
			return;
		}

		// If we're showing logs, swap the content
		if (nrfmWebhookLogs.showLogs) {
			showLogsView(actionsTab);
			return;
		}

		// Otherwise, add the "View Logs" link
		addLogsLink();
	});

	function showLogsView(actionsTab) {
		var logsContent = document.getElementById('nrfm-webhook-logs-content');
		if (!logsContent) {
			return;
		}

		// Create back link
		var backLink = document.createElement('p');
		backLink.style.marginBottom = '15px';
		var backAnchor = document.createElement('a');
		backAnchor.href = nrfmWebhookLogs.backUrl;
		backAnchor.innerHTML = '&larr; ' + nrfmWebhookLogs.backText;
		backLink.appendChild(backAnchor);

		// Hide original content
		var originalContent = actionsTab.innerHTML;
		actionsTab.innerHTML = '';
		
		// Add back link and logs content
		actionsTab.appendChild(backLink);
		logsContent.style.display = 'block';
		actionsTab.appendChild(logsContent);

		// Hide the save button when viewing logs
		var saveWrap = document.getElementById('nrfm-save-wrap');
		if (saveWrap) {
			saveWrap.style.display = 'none';
		}
	}

	function addLogsLink() {
		// Find the "Add Webhook" button
		var addWebhookBtn = document.getElementById('nrfm-add-webhook-action');
		if (!addWebhookBtn) {
			return;
		}

		// Create the "View Webhook Logs" link
		var link = document.createElement('a');
		link.href = nrfmWebhookLogs.logsUrl;
		link.className = 'button';
		link.style.marginLeft = '8px';
		link.textContent = nrfmWebhookLogs.linkText;

		// Add count badge if there are logs
		if (nrfmWebhookLogs.logCount > 0) {
			var badge = document.createElement('span');
			badge.style.cssText = 'display:inline-block;background:#2271b1;color:#fff;border-radius:10px;padding:0 6px;font-size:11px;margin-left:6px;';
			badge.textContent = nrfmWebhookLogs.logCount;
			link.appendChild(badge);
		}

		// Insert after the Add Webhook button
		addWebhookBtn.parentNode.insertBefore(link, addWebhookBtn.nextSibling);
	}
})();
