(function($) {
    'use strict';

    var config = window.nrfmWebhookTemplates || {};
    var presets = config.presets || {};
    var fieldNames = config.fieldNames || [];
    var metaFields = config.metaFields || [];
    var existingTemplates = config.existingTemplates || {};
    var existingHeaders = config.existingHeaders || {};

    /**
     * Initialize webhook template functionality
     */
    function init() {
        // Enhance existing webhook actions
        enhanceExistingWebhooks();

        // Watch for new webhook actions being added
        $(document).on('click', '#nrfm-add-webhook-action', function() {
            setTimeout(enhanceExistingWebhooks, 100);
        });

        // Handle format dropdown changes
        $(document).on('change', '.nrfm-action-item[data-action-type="webhook"] select[name*="[format]"]', function() {
            toggleTemplateRow($(this).closest('.nrfm-action-item'));
        });

        // Handle preset buttons
        $(document).on('click', '.nrfm-load-preset', function() {
            var preset = $(this).data('preset');
            var $textarea = $(this).closest('tr').find('.nrfm-template-textarea');
            if (presets[preset]) {
                $textarea.val(presets[preset]);
            }
        });

        // Live validation for headers JSON
        $(document).on('input', '.nrfm-headers-json-textarea', function() {
            validateHeadersTextarea($(this));
        });
    }

    /**
     * Enhance existing webhook action blocks with template option
     */
    function enhanceExistingWebhooks() {
        $('.nrfm-action-item[data-action-type="webhook"]').each(function() {
            var $action = $(this);
            
            // Skip if already enhanced
            if ($action.data('template-enhanced')) {
                return;
            }
            $action.data('template-enhanced', true);

            var $formatSelect = $action.find('select[name*="[format]"]');
            var actionIndex = $action.data('action-index');

            // Add "Custom Template" option to format dropdown
            if ($formatSelect.find('option[value="template"]').length === 0) {
                $formatSelect.append('<option value="template">' + config.formatLabel + '</option>');
            }

            // Get the existing template value from localized data
            var existingTemplate = existingTemplates[actionIndex] || '';
            var existingHeaderJson = existingHeaders[actionIndex] || '';

            // Insert the headers + template rows only if they aren't already present.
            // A block cloned from the (already enhanced) #tmpl-webhook-action template
            // carries them; injecting again would duplicate the Request Headers field.
            if ($action.find('.nrfm-webhook-headers-row').length === 0) {
                var $formatRow = $formatSelect.closest('tr');
                var templateHtml = getTemplateRowHtml(actionIndex, existingTemplate, existingHeaderJson);
                $formatRow.after(templateHtml);
            }

            // If we have an existing template, set format to 'template'
            if (existingTemplate) {
                $formatSelect.val('template');
            }

            // Populate available fields
            populateAvailableFields($action);
            validateHeadersTextarea($action.find('.nrfm-headers-json-textarea'));

            // Toggle visibility based on current format
            toggleTemplateRow($action);
        });

        // Also enhance the template in script tag for new webhooks
        enhanceWebhookTemplate();
    }

    /**
     * Enhance the webhook template script tag
     */
    function enhanceWebhookTemplate() {
        var $template = $('#tmpl-webhook-action');
        if ($template.length && !$template.data('template-enhanced')) {
            $template.data('template-enhanced', true);
            var html = $template.html();
            
            // Add template option to format select
            html = html.replace(
                '<option value="json">JSON</option>',
                '<option value="json">JSON</option>\n                                        <option value="template">' + config.formatLabel + '</option>'
            );
            
            // Add headers + template rows (template hidden by default)
            var headersRow = '\n                            <tr class="nrfm-webhook-headers-row">\n' +
                '                                <th><label>' + config.headersLabel + '</label></th>\n' +
                '                                <td>\n' +
                '                                    <textarea name="actions[webhook][{index}][headers_json]" class="large-text code nrfm-headers-json-textarea" rows="4" placeholder=\'{"Authorization":"Bearer ...","Accept":"application/json"}\'></textarea>\n' +
                '                                    <p class="description">' + config.headersHelp + '</p>\n' +
                '                                    <p class="description nrfm-headers-json-status" style="margin-top:4px;"></p>\n' +
                '                                </td>\n' +
                '                            </tr>';

            var templateRow = '\n                            <tr class="nrfm-webhook-template-row" style="display:none;">\n' +
                '                                <th><label>' + config.templateLabel + '</label></th>\n' +
                '                                <td>\n' +
                '                                    <div class="nrfm-template-presets" style="margin-bottom:8px;">\n' +
                '                                        <span style="font-weight:500;">' + config.presetLabel + '</span>\n' +
                '                                        <button type="button" class="button button-small nrfm-load-preset" data-preset="discord">' + config.presetDiscord + '</button>\n' +
                '                                        <button type="button" class="button button-small nrfm-load-preset" data-preset="slack">' + config.presetSlack + '</button>\n' +
                '                                        <button type="button" class="button button-small nrfm-load-preset" data-preset="teams">' + config.presetTeams + '</button>\n' +
                '                                        <button type="button" class="button button-small nrfm-load-preset" data-preset="generic">' + config.presetGeneric + '</button>\n' +
                '                                    </div>\n' +
                '                                    <textarea name="actions[webhook][{index}][template]" class="large-text code nrfm-template-textarea" rows="10" placeholder=\'{"content": "New submission from {{name}}"}\'></textarea>\n' +
                '                                    <p class="description">' + config.templateHelp + '</p>\n' +
                '                                    <details style="margin-top:8px;">\n' +
                '                                        <summary style="cursor:pointer;color:#0073aa;">' + config.availableLabel + '</summary>\n' +
                '                                        <div class="nrfm-available-fields" style="margin-top:8px;padding:10px;background:#f6f7f7;border-radius:4px;">' + getAvailableFieldsHtml() + '</div>\n' +
                '                                    </details>\n' +
                '                                </td>\n' +
                '                            </tr>';
            
            html = html.replace(
                '</table>',
                headersRow + templateRow + '\n                        </table>'
            );
            
            $template.html(html);
        }
    }

    /**
     * Generate template row HTML
     */
    function getTemplateRowHtml(actionIndex, existingTemplate, existingHeaderJson) {
        var escapedTemplate = existingTemplate ? escapeHtml(existingTemplate) : '';
        var escapedHeaders = existingHeaderJson ? escapeHtml(existingHeaderJson) : '';
        
        return '<tr class="nrfm-webhook-headers-row">' +
            '<th><label>' + config.headersLabel + '</label></th>' +
            '<td>' +
            '<textarea name="actions[webhook][' + actionIndex + '][headers_json]" class="large-text code nrfm-headers-json-textarea" rows="4" placeholder=\'{"Authorization":"Bearer ...","Accept":"application/json"}\'>' + escapedHeaders + '</textarea>' +
            '<p class="description">' + config.headersHelp + '</p>' +
            '<p class="description nrfm-headers-json-status" style="margin-top:4px;"></p>' +
            '</td>' +
            '</tr>' +
            '<tr class="nrfm-webhook-template-row" style="display:none;">' +
            '<th><label>' + config.templateLabel + '</label></th>' +
            '<td>' +
            '<div class="nrfm-template-presets" style="margin-bottom:8px;">' +
            '<span style="font-weight:500;">' + config.presetLabel + '</span> ' +
            '<button type="button" class="button button-small nrfm-load-preset" data-preset="discord">' + config.presetDiscord + '</button> ' +
            '<button type="button" class="button button-small nrfm-load-preset" data-preset="slack">' + config.presetSlack + '</button> ' +
            '<button type="button" class="button button-small nrfm-load-preset" data-preset="teams">' + config.presetTeams + '</button> ' +
            '<button type="button" class="button button-small nrfm-load-preset" data-preset="generic">' + config.presetGeneric + '</button>' +
            '</div>' +
            '<textarea name="actions[webhook][' + actionIndex + '][template]" class="large-text code nrfm-template-textarea" rows="10" placeholder=\'{"content": "New submission from {{name}}"}\'>' + escapedTemplate + '</textarea>' +
            '<p class="description">' + config.templateHelp + '</p>' +
            '<details style="margin-top:8px;">' +
            '<summary style="cursor:pointer;color:#0073aa;">' + config.availableLabel + '</summary>' +
            '<div class="nrfm-available-fields" style="margin-top:8px;padding:10px;background:#f6f7f7;border-radius:4px;"></div>' +
            '</details>' +
            '</td>' +
            '</tr>';
    }

    /**
     * Populate available fields in the details section
     */
    function populateAvailableFields($action) {
        var $fieldsContainer = $action.find('.nrfm-available-fields');
        if ($fieldsContainer.length) {
            $fieldsContainer.html(getAvailableFieldsHtml());
        }
    }

    /**
     * Get HTML for available fields display
     */
    function getAvailableFieldsHtml() {
        var html = '<div style="margin-bottom:8px;"><strong>Form Fields:</strong></div>';
        
        if (fieldNames.length > 0) {
            html += '<div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:12px;">';
            fieldNames.forEach(function(name) {
                html += '<code style="padding:2px 6px;background:#fff;border:1px solid #ddd;border-radius:3px;cursor:pointer;" class="nrfm-insert-placeholder" data-placeholder="{{' + name + '}}">{{' + name + '}}</code>';
            });
            html += '</div>';
        } else {
            html += '<p style="color:#666;margin-bottom:12px;">No form fields detected. Save the form first to see available fields.</p>';
        }

        html += '<div style="margin-bottom:8px;"><strong>Meta Fields:</strong></div>';
        html += '<div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:12px;">';
        metaFields.forEach(function(name) {
            html += '<code style="padding:2px 6px;background:#fff;border:1px solid #ddd;border-radius:3px;cursor:pointer;" class="nrfm-insert-placeholder" data-placeholder="{{' + name + '}}">{{' + name + '}}</code>';
        });
        html += '</div>';

        html += '<div style="margin-bottom:8px;"><strong>Special Placeholders:</strong></div>';
        html += '<div style="display:flex;flex-wrap:wrap;gap:5px;">';
        html += '<code style="padding:2px 6px;background:#e7f3ff;border:1px solid #0073aa;border-radius:3px;cursor:pointer;" class="nrfm-insert-placeholder" data-placeholder="{{all_fields}}" title="All form fields as JSON object">{{all_fields}}</code>';
        html += '<code style="padding:2px 6px;background:#e7f3ff;border:1px solid #0073aa;border-radius:3px;cursor:pointer;" class="nrfm-insert-placeholder" data-placeholder="{{all_fields_embed}}" title="Discord embed fields format">{{all_fields_embed}}</code>';
        html += '<code style="padding:2px 6px;background:#e7f3ff;border:1px solid #0073aa;border-radius:3px;cursor:pointer;" class="nrfm-insert-placeholder" data-placeholder="{{all_fields_slack}}" title="Slack fields format">{{all_fields_slack}}</code>';
        html += '<code style="padding:2px 6px;background:#e7f3ff;border:1px solid #0073aa;border-radius:3px;cursor:pointer;" class="nrfm-insert-placeholder" data-placeholder="{{all_fields_teams}}" title="Microsoft Teams facts format">{{all_fields_teams}}</code>';
        html += '</div>';

        return html;
    }

    /**
     * Toggle template row visibility based on format selection
     */
    function toggleTemplateRow($action) {
        var $formatSelect = $action.find('select[name*="[format]"]');
        var $templateRow = $action.find('.nrfm-webhook-template-row');
        
        if ($formatSelect.val() === 'template') {
            $templateRow.show();
        } else {
            $templateRow.hide();
        }
    }

    function validateHeadersTextarea($textarea) {
        if (!$textarea || !$textarea.length) {
            return;
        }
        var $status = $textarea.closest('td').find('.nrfm-headers-json-status');
        var value = ($textarea.val() || '').trim();
        if (!value) {
            $status.text('').css('color', '');
            return;
        }
        try {
            var parsed = JSON.parse(value);
            var isObject = parsed && typeof parsed === 'object' && !Array.isArray(parsed);
            if (isObject) {
                $status.text(config.headersValid || 'Valid JSON').css('color', '#1f7a1f');
            } else {
                $status.text(config.headersInvalid || 'Invalid JSON').css('color', '#b32d2e');
            }
        } catch (e) {
            $status.text(config.headersInvalid || 'Invalid JSON').css('color', '#b32d2e');
        }
    }

    /**
     * Handle placeholder click to insert into textarea
     */
    $(document).on('click', '.nrfm-insert-placeholder', function() {
        var placeholder = $(this).data('placeholder');
        var $textarea = $(this).closest('.nrfm-action-item').find('.nrfm-template-textarea');
        
        if ($textarea.length) {
            var cursorPos = $textarea[0].selectionStart;
            var textBefore = $textarea.val().substring(0, cursorPos);
            var textAfter = $textarea.val().substring(cursorPos);
            $textarea.val(textBefore + placeholder + textAfter);
            $textarea.focus();
            $textarea[0].selectionStart = $textarea[0].selectionEnd = cursorPos + placeholder.length;
        }
    });

    /**
     * Escape HTML entities
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    // Initialize on DOM ready
    $(document).ready(init);

})(jQuery);
