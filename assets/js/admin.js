/* Narrative Forms Admin JS */
(function($) {
    'use strict';
    
    var codeEditorHTML = null;

    // Escape user-entered text when building markup strings.
    function escAttr(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/"/g, '&quot;'); }
    function escHtml(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

    // Helpers
    function schedulePreviewAndVars() {
        clearTimeout(window.previewTimer);
        window.previewTimer = setTimeout(updatePreview, 150);
        clearTimeout(window.varsTimer);
        window.varsTimer = setTimeout(updateVariableChips, 150);
    }

    function refreshHtmlEditorSoon() {
        if (codeEditorHTML && codeEditorHTML.codemirror) {
            setTimeout(function(){ codeEditorHTML.codemirror.refresh(); }, 50);
        }
    }

    function getHtmlContent() {
        return (codeEditorHTML && codeEditorHTML.codemirror) ? codeEditorHTML.codemirror.getValue() : $('#nrfm-form-content').val();
    }

    function getWrapperTag() {
        var globalSettings = window.nrfm_settings || {};
        return globalSettings.wrapper_tag || 'p';
    }
    
    $(document).ready(function() {
        initializeCodeEditor();
        initializePreview();
        updateVariableChips();
        initializeSettingsDisclosure();
    });
    
    function initializeCodeEditor() {
        if (typeof wp !== 'undefined' && typeof wp.codeEditor !== 'undefined' && $('#nrfm-form-content').length) {
            
            var editorSettings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};
            editorSettings.codemirror = _.extend({}, editorSettings.codemirror, {
                indentUnit: 4,
                tabSize: 4,
                mode: 'htmlmixed',
                lineNumbers: true,
                lineWrapping: true,
                autoCloseTags: true,
                matchBrackets: true,
                autoCloseBrackets: true,
                lint: false,
                gutters: ['CodeMirror-linenumbers']
            });
            
            codeEditorHTML = wp.codeEditor.initialize($('#nrfm-form-content'), editorSettings);
            // Always keep underlying textarea hidden; we only toggle CM wrappers
            $('#nrfm-form-content').hide();
            
            codeEditorHTML.codemirror.on('change', schedulePreviewAndVars);

            // Ensure proper sizing
            setTimeout(refreshHtmlEditorSoon, 100);
        }
    }
    
    function initializePreview() {
        if ($('#nrfm-preview-column').length) {
            $('#nrfm-preview-column').show();
            $('.nrfm-editor-wrapper').addClass('nrfm-editor-split');
            $('#nrfm-show-preview').hide();
            $('#nrfm-hide-preview').show();
            updatePreview();
        }

    }

    function initializeSettingsDisclosure() {
        var $children = $('.nrfm-setting-children[data-parent-input]');
        if (!$children.length) {
            return;
        }

        function applyVisibility($group) {
            var selector = $group.data('parent-input');
            if (!selector) {
                return;
            }
            var $input = $(selector).first();
            if (!$input.length) {
                return;
            }
            var isEnabled = $input.is(':checked');
            $group.toggle(isEnabled);
        }

        $children.each(function() {
            applyVisibility($(this));
        });

        $(document).on('change', '#nrfm-form-editor #tab-settings input[type="checkbox"], .nrfm-settings-wrap input[type="checkbox"]', function() {
            $children.each(function() {
                applyVisibility($(this));
            });
        });
    }
    
    // Tab switching (without page reload except for submissions)
    $('.nrfm-tabs .nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var $tab = $(this);
        var tabId = $tab.data('tab');
        
        // For submissions tab, always reload to list view (remove any single-view params)
        if (tabId === 'submissions') {
            var urlObj = new URL(window.location.href);
            urlObj.searchParams.set('tab', 'submissions');
            urlObj.searchParams.delete('nrfm_view');
            // Preserve edit_form nonce; do NOT delete _wpnonce
            window.location.href = urlObj.toString();
            return;
        }

        // If submissions panel is rendered outside the form, editable tabs still exist in DOM.
        // No reload is needed when switching away from Submissions anymore because
        // main form markup is always present in the page.
        
        // Switch tabs without reload for others
        $('.nrfm-tabs .nav-tab').removeClass('nav-tab-active');
        $tab.addClass('nav-tab-active');
        
        $('.nrfm-tab-panel').removeClass('active');
        $('#tab-' + tabId).addClass('active');

        // Toggle Save button visibility depending on active tab
        var showSave = (tabId !== 'submissions');
        var $saveWrap = $('#nrfm-save-wrap');
        if ($saveWrap.length) {
            if (showSave) { $saveWrap.show(); } else { $saveWrap.hide(); }
        }
        
        // Update URL without reload
        var urlObj2 = new URL(window.location.href);
        urlObj2.searchParams.set('tab', tabId);
        window.history.replaceState({}, '', urlObj2.toString());
        
        // Refresh CodeMirror when switching to Fields tab
        if (tabId === 'fields' && codeEditorHTML && codeEditorHTML.codemirror) {
            setTimeout(function() { refreshHtmlEditorSoon(); updatePreview(); }, 100);
        }
    });
    
    // Slug editing
    $('#edit-slug').on('click', function() {
        var $slugInput = $('#form-slug');
        var $button = $(this);
        
        if ($slugInput.prop('readonly')) {
            $slugInput.prop('readonly', false).focus();
            $button.text('Save');
        } else {
            $slugInput.prop('readonly', true);
            $button.text('Edit');
            
            // Update shortcode
            var slug = $slugInput.val();
            $('#form-shortcode').val('[nrfm_form slug="' + slug + '"]');
        }
    });
    
    // Show/Hide preview
    $('#nrfm-show-preview').on('click', function(e) {
        e.preventDefault();
        $('#nrfm-preview-column').show();
        $('.nrfm-editor-wrapper').addClass('nrfm-editor-split');
        $(this).hide();
        $('#nrfm-hide-preview').show();
        updatePreview();
        
        // Refresh CodeMirror
        refreshHtmlEditorSoon();
    });
    
    $('#nrfm-hide-preview').on('click', function(e) {
        e.preventDefault();
        // Keep split wrapper to prevent vertical shift; just hide preview pane
        $('#nrfm-preview-column').hide();
        if (!$('.nrfm-editor-wrapper').hasClass('nrfm-editor-split')) {
            $('.nrfm-editor-wrapper').addClass('nrfm-editor-split');
        }
        $(this).hide();
        $('#nrfm-show-preview').show();
        refreshHtmlEditorSoon();
    });
    
    // Update preview with debounce (use shared timers)
    $('#nrfm-form-content').on('input', function() {
        schedulePreviewAndVars();
    });
    
    var previewLoaded = false;
    var previewFieldsWrap = null;

    function updatePreview() {
        var a = window.nrfm_admin || {};
        var preview = $('#nrfm-form-preview')[0];
        if (!preview) return;

        var content = getHtmlContent();
        // Apply wrapper tag
        try {
            var wrapper = getWrapperTag();
            var hasWrappersAlready = /<\s*(p|div|section|article)\b/i.test(content);
            if (wrapper && wrapper !== 'none' && !hasWrappersAlready) {
                content = content.replace(/^(\s*)(<(?:input|textarea|select|button|label)[^>]*>.*)$/gmi, function(_m, _s, inner){
                    return '<' + wrapper + '>' + inner.trim() + '</' + wrapper + '>';
                });
            }
            var prev;
            do { prev = content; content = content.replace(/<\s*(p|div|section|article)\b[^>]*>\s*<\/\1>/gi, ''); } while (content !== prev);
        } catch(e) {}

        // If preview iframe already loaded, update .nrfm-fields-wrap directly (like HTML Forms)
        if (previewLoaded && previewFieldsWrap) {
            previewFieldsWrap.innerHTML = content;
            return;
        }

        // Load preview from server first time, then update inline
        if (a.preview_url && !previewLoaded) {
            preview.onload = function() {
                try {
                    var doc = preview.contentDocument || preview.contentWindow.document;
                    previewFieldsWrap = doc.querySelector('.nrfm-fields-wrap');
                    if (previewFieldsWrap) {
                        previewLoaded = true;
                        previewFieldsWrap.innerHTML = content;
                    }
                } catch(e) {}
            };
            preview.src = a.preview_url;
        }
    }
    
    // Build dynamic variable chips from current editor content
    function updateVariableChips() {
        try {
            var content = getHtmlContent();
            var tokens = computeTokensFromContent(content);
            // Redirect URL chips
            var $redirectContainer = $('#redirect-url').closest('td').find('.nrfm-variable-buttons');
            if ($redirectContainer.length) {
                var html = '';
                tokens.forEach(function(tok){
                    html += '<button type="button" class="button button-small nrfm-insert-var" data-target="redirect-url" data-var="' + tok + '">' + tok + '</button> ';
                });
                $redirectContainer.html(html);
            }
            // Email message chips (any container with data-target pointing to a textarea id)
            $('.nrfm-variable-buttons.nrfm-dynamic-vars').each(function(){
                var target = $(this).data('target');
                if (!target) return;
                var html2 = '';
                tokens.forEach(function(tok){
                    html2 += '<button type="button" class="button button-small nrfm-insert-var" data-target="' + target + '" data-var="' + tok + '">' + tok + '</button> ';
                });
                $(this).html(html2);
            });
        } catch (e) {}
    }

    function computeTokensFromContent(content) {
        var namesMap = {};
        var regex = /name=["']([^"']+)/gi;
        var m;
        while ((m = regex.exec(content)) !== null) {
            var nm = (m[1] || '').replace(/\[\]$/, '');
            if (!nm) continue;
            // Exclude internal helper fields such as nrfm_max_* and any nrfm_* token
            if (/^nrfm_/i.test(nm) || /^_/i.test(nm)) continue;
            namesMap[nm] = true;
        }
        var tokens = Object.keys(namesMap).map(function(n){ return '[' + n.toUpperCase() + ']'; });
        var builtins = ['[NRFM_TIMESTAMP]','[NRFM_USER_AGENT]','[NRFM_IP_ADDRESS]','[NRFM_REFERRER_URL]','[NRFM_ALL_FIELDS]'];
        builtins.forEach(function(b){ if (tokens.indexOf(b) === -1) tokens.push(b); });
        return tokens;
    }

    // Field buttons - show configuration
    $('.nrfm-field-btn').on('click', function() {
        var fieldType = $(this).data('field');
        showFieldConfig(fieldType);
    });
    
    function showFieldConfig(fieldType) {
        var $config = $('#nrfm-field-config');
        var $body = $config.find('.nrfm-field-config-body');
        
        // Update title
        $('#nrfm-field-title').text(getFieldTitle(fieldType));
        
        // Build configuration form
        var html = '';
        
        if (fieldType === 'submit') {
            html = buildSubmitConfig();
        } else if (fieldType === 'file') {
            html = buildFileConfig();
        } else if (fieldType === 'dropdown' || fieldType === 'checkboxes' || fieldType === 'radio') {
            html = buildChoiceFieldConfig(fieldType);
        } else {
            html = buildSimpleFieldConfig(fieldType);
        }
        
        $body.html(html);
        
        // Slide down
        $config.show();
    }
    
    function buildSimpleFieldConfig(fieldType) {
        var html = '<table class="form-table">';
        html += '<tr>';
        html += '<th><label for="field-label">Field Label *</label></th>';
        html += '<td><input type="text" id="field-label" class="regular-text"></td>';
        html += '</tr>';

        // Placeholder (for inputs and textarea; date inputs don't use placeholders reliably)
        if (fieldType !== 'date') {
            html += '<tr>';
            html += '<th><label for="field-placeholder">Placeholder <span class="description">(Optional)</span></label></th>';
            html += '<td><input type="text" id="field-placeholder" class="regular-text" placeholder="Type in your placeholder"></td>';
            html += '</tr>';
        }
        
        if (fieldType === 'textarea') {
            html += '<tr>';
            html += '<th><label for="field-rows">Rows</label></th>';
            html += '<td><input type="number" id="field-rows" value="5" min="1" max="20" class="small-text"></td>';
            html += '</tr>';
        }

        // Default value
        html += '<tr>';
        html += '<th><label for="field-default">Default Value <span class="description">(Optional)</span></label></th>';
        html += '<td><input type="text" id="field-default" class="regular-text" placeholder="Text to pre-fill this field with."></td>';
        html += '</tr>';

        html += buildRequiredAndWrapRow();
        html += '</table>';
        
        html += '<p>';
        html += '<button type="button" class="button button-primary" onclick="nrfmAddField(\'' + fieldType + '\')">Add Field to Form</button> ';
        html += '<button type="button" class="button" onclick="nrfmCancelField()">Cancel</button>';
        html += '</p>';
        
        return html;
    }
    
    function buildFileConfig() {
        var html = '<table class="form-table">';
        html += '<tr>';
        html += '<th><label for="field-label">Field Label *</label></th>';
        html += '<td><input type="text" id="field-label" class="regular-text"></td>';
        html += '</tr>';
        html += '<tr>';
        html += '<th><label for="field-accept">Accept File Types</label></th>';
        html += '<td>';
        html += '<input type="text" id="field-accept" class="regular-text" value=".pdf,.doc,.docx,.jpg,.png" placeholder=".pdf,.doc,.docx">';
        html += '<p class="description">Comma-separated list of accepted file extensions</p>';
        html += '</td>';
        html += '</tr>';
        html += '<tr>';
        html += '<th><label for="field-max-size">Max File Size (MB)</label></th>';
        html += '<td>';
        html += '<input type="number" id="field-max-size" class="small-text" value="5" min="0" step="1">';
        html += '<p class="description">Per file. Leave empty or 0 for no limit (not recommended).</p>';
        html += '</td>';
        html += '</tr>';
        html += '<tr>';
        html += '<th><label for="field-max-files">Maximum files allowed</label></th>';
        html += '<td><input type="number" id="field-max-files" class="small-text" value="1" min="1" step="1"></td>';
        html += '</tr>';
        html += buildRequiredAndWrapRow();
        html += '</table>';
        
        html += '<p>';
        html += '<button type="button" class="button button-primary" onclick="nrfmAddField(\'file\')">Add Field to Form</button> ';
        html += '<button type="button" class="button" onclick="nrfmCancelField()">Cancel</button>';
        html += '</p>';
        
        return html;
    }
    
    function buildChoiceFieldConfig(fieldType) {
        var html = '<table class="form-table">';
        html += '<tr>';
        html += '<th><label for="field-label">Field Label *</label></th>';
        html += '<td><input type="text" id="field-label" class="regular-text"></td>';
        html += '</tr>';
        html += '<tr>';
        html += '<th>Choices</th>';
        html += '<td id="field-choices">';
        html += '<div class="nrfm-choice"><input type="text" value="Option 1" class="regular-text"> <button type="button" class="button" onclick="nrfmRemoveChoice(this)">×</button></div>';
        html += '<div class="nrfm-choice"><input type="text" value="Option 2" class="regular-text"> <button type="button" class="button" onclick="nrfmRemoveChoice(this)">×</button></div>';
        html += '</td>';
        html += '</tr>';
        html += '<tr>';
        html += '<th></th>';
        html += '<td><button type="button" class="button" onclick="nrfmAddChoice()">Add Choice</button></td>';
        html += '</tr>';
        
        if (fieldType === 'checkboxes') {
            html += '<tr>';
            html += '<th></th>';
            html += '<td><label><input type="checkbox" id="field-multiple" checked> Accept multiple values</label></td>';
            html += '</tr>';
        }
        
        html += buildRequiredAndWrapRow();
        html += '</table>';
        
        html += '<p>';
        html += '<button type="button" class="button button-primary" onclick="nrfmAddField(\'' + fieldType + '\')">Add Field to Form</button> ';
        html += '<button type="button" class="button" onclick="nrfmCancelField()">Cancel</button>';
        html += '</p>';
        
        return html;
    }
    
    function buildSubmitConfig() {
        var html = '<table class="form-table">';
        html += '<tr>';
        html += '<th><label for="field-label">Button Text</label></th>';
        html += '<td><input type="text" id="field-label" class="regular-text" value="Submit"></td>';
        html += '</tr>';
        html += buildRequiredAndWrapRow(true);
        html += '</table>';
        
        html += '<p>';
        html += '<button type="button" class="button button-primary" onclick="nrfmAddField(\'submit\')">Add Button to Form</button> ';
        html += '<button type="button" class="button" onclick="nrfmCancelField()">Cancel</button>';
        html += '</p>';
        
        return html;
    }
    
    function getFieldTitle(fieldType) {
        var titles = {
            'text': 'Text Field',
            'email': 'Email Field',
            'url': 'URL Field',
            'number': 'Number Field',
            'date': 'Date Field',
            'textarea': 'Textarea Field',
            'dropdown': 'Dropdown Field',
            'checkboxes': 'Checkboxes Field',
            'radio': 'Radio Buttons Field',
            'file': 'File Upload Field',
            'submit': 'Submit Button'
        };
        return titles[fieldType] || 'Configure Field';
    }
    
    // Reusable row for Required + Wrap checkboxes.
    // If forSubmit is true, omit the Required checkbox.
    function buildRequiredAndWrapRow(forSubmit) {
        var html = '<tr>';
        html += '<th></th>';
        html += '<td>';
        html += '<div class="nrfm-toggle-list">';
        if (!forSubmit) {
            html += '<label><input type="checkbox" id="field-required"> This field is required</label>';
        }
        var wrapperTag = getWrapperTag();
        if (wrapperTag !== 'none') {
            html += '<label><input type="checkbox" id="field-wrap" checked> Wrap this field in a &lt;' + wrapperTag + '&gt; tag</label>';
        }
        html += '</div>';
        html += '</td>';
        html += '</tr>';
        return html;
    }
    
    // Global functions for field configuration
    window.nrfmAddField = function(fieldType) {
        var label = $('#field-label').val();
        if (!label && fieldType !== 'submit') {
            alert('Please enter a field label');
            return;
        }
        
        var html = generateFieldHTML(fieldType, label);
        
        // Insert at cursor position or at end
        if (codeEditorHTML) {
            var doc = codeEditorHTML.codemirror.getDoc();
            var cursor = doc.getCursor();
            doc.replaceRange(html, cursor);
        } else {
            var textarea = $('#nrfm-form-content')[0];
            var start = textarea.selectionStart;
            var end = textarea.selectionEnd;
            var text = textarea.value;
            
            textarea.value = text.substring(0, start) + html + text.substring(end);
            textarea.selectionStart = textarea.selectionEnd = start + html.length;
            textarea.focus();
        }
        
        // Hide config
        $('#nrfm-field-config').hide();
        
        // Update preview
        updatePreview();
    };
    
    window.nrfmCancelField = function() {
        $('#nrfm-field-config').hide();
    };
    
    window.nrfmAddChoice = function() {
        var html = '<div class="nrfm-choice"><input type="text" value="" class="regular-text" placeholder="Enter choice"> ';
        html += '<button type="button" class="button" onclick="nrfmRemoveChoice(this)">×</button></div>';
        $('#field-choices').append(html);
    };
    
    window.nrfmRemoveChoice = function(button) {
        $(button).parent().remove();
    };
    
    function generateFieldHTML(fieldType, label) {
        var globalSettings = window.nrfm_settings || {};
        var wrapperTag = globalSettings.wrapper_tag || 'p';
        var wrap = $('#field-wrap').length ? $('#field-wrap').is(':checked') : (wrapperTag !== 'none');
        var required = $('#field-required').is(':checked');
        var placeholder = $('#field-placeholder').val() || '';
        var defaultValue = $('#field-default').val() || '';
        // Generate a clean field name: lowercase, remove apostrophes, collapse non-alnum to underscore, trim leading/trailing underscores
        var fieldName = label ? label.toLowerCase()
            .replace(/'/g, '')
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '') : '';
        var labelText = label + (required ? ' <span class="nrfm-required" aria-hidden="true">*</span>' : '');
        // Build a slug-prefixed id to avoid collisions across multiple forms on the same page
        var formSlug = ($('#form-slug').val() || '').toString().trim();
        formSlug = formSlug ? formSlug.replace(/[^a-z0-9\-]/gi, '-').toLowerCase() : '';
        var idPrefix = formSlug ? formSlug + '-' : '';
        var fieldId = idPrefix + fieldName;
        // Optional custom id/class (use exactly what user types after sanitization; no forced prefix)
        var customId = ($('#field-custom-id').val() || '').trim();
        if (customId) {
            customId = customId.replace(/'/g, '').replace(/[^a-z0-9_\-]/gi, '-');
        }
        var extraClass = ($('#field-class').val() || '').trim().replace(/[^ a-z0-9_\-]/gi, '').replace(/\s+/g, ' ').trim();
        var html = '';
        
        if (wrap && wrapperTag !== 'none') {
            var wrapperAttrs = '';
            if (customId) { wrapperAttrs += ' id="' + customId + '"'; }
            if (extraClass) { wrapperAttrs += ' class="' + extraClass + '"'; }
            html += '<' + wrapperTag + wrapperAttrs + '>\n';
        }
        
        if (fieldType === 'submit') {
            html += '    <button type="submit">' + (label || 'Submit') + '</button>\n';
        } else if (fieldType === 'file') {
            var accept = $('#field-accept').val() || '';
            var multiple = $('#field-multiple').is(':checked');
            var maxSizeMb = parseInt($('#field-max-size').val(), 10);
            if (isNaN(maxSizeMb)) { maxSizeMb = 0; }
            
            html += '    <label for="' + fieldId + '">' + labelText + '</label>\n';
            html += '    <input type="file" id="' + fieldId + '" name="' + fieldName + '"';
            if (accept) html += ' accept="' + accept + '"';
            var maxFiles = parseInt($('#field-max-files').val(), 10) || 1;
            if (maxFiles > 1) html += ' multiple';
            if (required) html += ' required';
            html += '>\n';
            if (maxSizeMb > 0) {
                var bytes = maxSizeMb * 1024 * 1024;
                html += '    <input type="hidden" name="nrfm_max_' + fieldName + '" value="' + bytes + '">\n';
            }
            if (maxFiles > 1) {
                html += '    <input type="hidden" name="nrfm_max_files_' + fieldName + '" value="' + maxFiles + '">\n';
            }
        } else if (fieldType === 'textarea') {
            var rows = $('#field-rows').val() || 5;
            html += '    <label for="' + fieldId + '">' + labelText + '</label>\n';
            html += '    <textarea id="' + fieldId + '" name="' + fieldName + '" rows="' + rows + '"';
            if (placeholder) html += ' placeholder="' + placeholder.replace(/"/g,'&quot;') + '"';
            if (required) html += ' required';
            html += '>' + (defaultValue ? defaultValue.replace(/</g,'&lt;') : '') + '</textarea>\n';
        } else if (fieldType === 'dropdown') {
            html += '    <label for="' + fieldId + '">' + labelText + '</label>\n';
            html += '    <select id="' + fieldId + '" name="' + fieldName + '"';
            if (required) html += ' required';
            html += '>\n';
            
            $('#field-choices .nrfm-choice input').each(function() {
                var choice = $(this).val();
                if (choice) {
                    html += '        <option value="' + escAttr(choice) + '">' + escHtml(choice) + '</option>\n';
                }
            });
            
            html += '    </select>\n';
        } else if (fieldType === 'checkboxes' || fieldType === 'radio') {
            html += '    <label>' + labelText + '</label>\n';
            
            $('#field-choices .nrfm-choice input').each(function(index) {
                var choice = $(this).val();
                if (choice) {
                    var inputType = fieldType === 'checkboxes' ? 'checkbox' : 'radio';
                    var inputName = fieldType === 'checkboxes' ? fieldName + '[]' : fieldName;
                    var choiceId = fieldId + '_' + index;
                    
                    html += '    <label><input type="' + inputType + '" id="' + choiceId + '" name="' + inputName + '" value="' + escAttr(choice) + '"';
                    if (required && fieldType === 'radio' && index === 0) html += ' required';
                    html += '> ' + escHtml(choice) + '</label><br>\n';
                }
            });
        } else {
            // Simple fields (text, email, url, number, date)
            html += '    <label for="' + fieldId + '">' + labelText + '</label>\n';
            html += '    <input type="' + fieldType + '" id="' + fieldId + '" name="' + fieldName + '"';
            if (placeholder && fieldType !== 'date') html += ' placeholder="' + placeholder.replace(/"/g,'&quot;') + '"';
            if (defaultValue) html += ' value="' + defaultValue.replace(/"/g,'&quot;') + '"';
            if (required) html += ' required';
            html += '>\n';
        }
        
        if (wrap && wrapperTag !== 'none') {
            html += '</' + wrapperTag + '>\n';
        }
        
        return html;
    }
    
    // Actions repeater
    var emailActionIndex = $('.nrfm-action-item[data-action-type="email"]').length || 0;
    var webhookActionIndex = $('.nrfm-action-item[data-action-type="webhook"]').length || 0;
    
    // Add email action
    $('#nrfm-add-email-action').on('click', function() {
        var template = $('#tmpl-email-action').html();
        var html = template.replace(/{index}/g, emailActionIndex++);
        $('#nrfm-actions-container').append(html);
        // refresh dynamic variable chips for the newly added block
        updateVariableChips();
    });
    
    // Add webhook action
    $('#nrfm-add-webhook-action').on('click', function() {
        var template = $('#tmpl-webhook-action').html();
        var html = template.replace(/{index}/g, webhookActionIndex++);
        $('#nrfm-actions-container').append(html);
    });
    
    // Remove action
    $(document).on('click', '.nrfm-remove-action', function() {
        if (confirm('Are you sure you want to remove this action?')) {
            $(this).closest('.nrfm-action-item').remove();
        }
    });
    
    // Collapse/expand action bodies and keep a concise summary
    function refreshActionSummary($item){
        var type = $item.data('action-type');
        var summary = '';
        if (type === 'email') {
            var to = $item.find('input[name*="[to]"]').first().val() || '';
            var from = $item.find('input[name*="[from]"]').first().val() || '';
            summary = ' — From ' + (from || '—') + '. To ' + (to || '—') + '.';
        } else if (type === 'webhook') {
            var url = $item.find('input[name*="[url]"]').first().val() || '';
            summary = url ? ' — ' + url : '';
        }
        $item.find('.nrfm-action-summary').text(summary);
    }

    function toggleAction($item, expand){
        var $btn = $item.find('> h3 .nrfm-action-toggle');
        var $body = $item.find('.nrfm-action-body');
        var expanded = (expand !== undefined) ? !!expand : ($btn.attr('aria-expanded') !== 'true');
        if (expanded) {
            $body.show();
            $btn.attr('aria-expanded','true').text('▾'); // down arrow
        } else {
            $body.hide();
            $btn.attr('aria-expanded','false').text('▸'); // right arrow
        }
        refreshActionSummary($item);
    }

    // Arrow button does not need its own handler; header handles toggling for minimal JS

    // Make entire header clickable (except Remove button)
    $(document).on('click', '.nrfm-action-item>h3', function(e){
        if ($(e.target).closest('.nrfm-remove-action').length) return;
        toggleAction($(this).closest('.nrfm-action-item'));
    });

    // Update summaries live when fields change
    $(document).on('input change', '.nrfm-action-item input, .nrfm-action-item select, .nrfm-action-item textarea', function(){
        var $item = $(this).closest('.nrfm-action-item');
        refreshActionSummary($item);
    });

    // Collapse already-saved actions on load (their summary line stays visible), so the
    // Actions tab is scannable. Newly added actions are appended after this runs and stay
    // expanded so they can be filled in.
    $('.nrfm-action-item').each(function(){ toggleAction($(this), false); });
    
    // Variable insertion
    $(document).on('click', '.nrfm-insert-var', function() {
        var target = $(this).data('target');
        var variable = $(this).data('var');
        var $field = $('#' + target);
        
        if ($field.length) {
            var val = $field.val();
            var start = $field[0].selectionStart;
            var end = $field[0].selectionEnd;
            
            $field.val(val.substring(0, start) + variable + val.substring(end));
            $field[0].selectionStart = $field[0].selectionEnd = start + variable.length;
            $field.focus();
        }
    });
    
    // Assign stable IDs to existing email action message textareas (replaces prior inline script)
    $(function(){
        $('textarea[name^="actions[email]["][name$="][message]"]').each(function(){
            var $t = $(this);
            if (!$t.attr('id')) {
                var name = $t.attr('name') || '';
                var m = name.match(/^actions\[email\]\[(\d+)\]\[message\]$/);
                if (m && m[1]) {
                    $t.attr('id', 'actions_email_' + m[1] + '_message');
                }
            }
        });
    });

    // Submissions list: select-all checkbox behavior (replaces prior inline script)
    $(function(){
        var $table = $('#nrfm-submissions-form .wp-list-table');
        if ($table.length) {
            $('#cb-select-all-1').on('change', function(){
                var checked = $(this).is(':checked');
                $table.find('tbody .check-column input[type="checkbox"]').prop('checked', checked);
            });
        }
    });

})(jQuery);