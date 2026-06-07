/* Narrative Forms Frontend JS */
(function() {
    'use strict';
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Tiny event helper for consumers: window.nrfm.on('success', fn)
    if (!window.nrfm) {
        window.nrfm = {
            on: function(eventName, handler) {
                if (typeof handler !== 'function') return;
                document.addEventListener('nrfm-' + eventName, function(ev){ handler(ev.detail && ev.detail.formElement, ev.detail || {}); });
            }
        };
    }

    function emit(eventName, form, extra) {
        var detail = extra || {};
        detail.formElement = form;
        // Dispatch on document
        document.dispatchEvent(new CustomEvent('nrfm-' + eventName, { detail: detail }));
        // Dispatch on the specific form
        form.dispatchEvent(new CustomEvent('nrfm-' + eventName, { detail: detail }));
    }

    function init() {
        var forms = document.querySelectorAll('.nrfm-form');
        
        forms.forEach(function(form) {
            // File size and count validation
            form.addEventListener('change', function(ev){
                var target = ev.target;
                if (target && target.type === 'file') {
                    var name = target.name.replace(/\[\]$/, '');
                    var hidden = form.querySelector('input[name="nrfm_max_' + name + '"]');
                    var max = hidden ? parseInt(hidden.value, 10) : 0;
                    // Enforce maximum number of files if provided
                    var maxFilesEl = form.querySelector('input[name="nrfm_max_files_' + name + '"]');
                    var maxFiles = maxFilesEl ? parseInt(maxFilesEl.value, 10) : 0;
                    if (maxFiles > 0 && target.files && target.files.length > maxFiles) {
                        try {
                            var dt = new DataTransfer();
                            for (var j = 0; j < maxFiles; j++) { dt.items.add(target.files[j]); }
                            target.files = dt.files;
                        } catch (e) { /* older browsers may not support DataTransfer assignment */ }
                        var tpl = form.getAttribute('data-msg-max-files') || 'You can upload up to %d files.';
                        var msg = tpl.replace('%d', String(maxFiles));
                        // Also handle singular form if provided like "%d file"
                        if (maxFiles === 1) { msg = msg.replace('files', 'file'); }
                        showFieldError(target, msg);
                    } else {
                        clearFieldError(target);
                    }
                    if (max > 0 && target.files && target.files.length) {
                        for (var i=0;i<target.files.length;i++) {
                            if (target.files[i].size > max) {
                                showFieldError(target, form.getAttribute('data-msg-file-too-large'));
                                target.value = '';
                                break;
                            }
                        }
                    }
                }
            });
            // Basic required and email pattern checks on blur/change for quick feedback
            form.addEventListener('blur', function(ev){
                var el = ev.target;
                if (!el || !el.name) return;
                if (el.required && !el.value) {
                    showFieldError(el, form.getAttribute('data-msg-required'));
                } else if (el.type === 'email' && el.value) {
                    var ok = /.+@.+\..+/.test(el.value);
                    if (!ok) showFieldError(el, form.getAttribute('data-msg-invalid-email'));
                    else clearFieldError(el);
                } else {
                    clearFieldError(el);
                }
            }, true);
            form.addEventListener('submit', handleSubmit);
        });
    }
    
    function handleSubmit(e) {
        e.preventDefault();
        
        var form = e.target;
        emit('submit', form, {});
        // Prevent accidental double submits
        if (form.dataset.nrfmSubmitting === '1') {
            return;
        }
        form.dataset.nrfmSubmitting = '1';
        var formId = form.dataset.formId;
        var wrapper = form.closest('.nrfm-form-wrapper');
        var submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
        
        // Disable submit button
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.dataset.originalText = submitButton.textContent || submitButton.value;
            if (submitButton.tagName === 'BUTTON') {
                submitButton.textContent = 'Sending...';
            } else {
                submitButton.value = 'Sending...';
            }
        }
        
        // Remove any existing messages
        var existingMessage = wrapper.querySelector('.nrfm-message');
        if (existingMessage) {
            existingMessage.remove();
        }
        
        // Prepare form data, but cap file inputs by per-field max rules
        var formData = new FormData(form);
        var fileInputs = form.querySelectorAll('input[type="file"][name]');
        fileInputs.forEach(function(fileInput){
            var fieldName = fileInput.name; // may end with []
            var baseName = fieldName.replace(/\[\]$/, '');
            var maxFilesEl = form.querySelector('input[name="nrfm_max_files_' + baseName + '"]');
            var maxFiles = maxFilesEl ? parseInt(maxFilesEl.value, 10) : 0;
            var maxSizeEl = form.querySelector('input[name="nrfm_max_' + baseName + '"]');
            var maxSize = maxSizeEl ? parseInt(maxSizeEl.value, 10) : 0;

            if (!fileInput.files || !fileInput.files.length) return;

            var allowed = [];
            for (var i = 0; i < fileInput.files.length; i++) {
                var f = fileInput.files[i];
                if (maxSize > 0 && f.size > maxSize) {
                    // skip oversized
                    continue;
                }
                allowed.push(f);
            }

            var trimmed = false;
            if (maxFiles > 0 && allowed.length > maxFiles) {
                allowed = allowed.slice(0, maxFiles);
                trimmed = true;
            }

            // If the input is not multiple, ensure only one file is sent
            if (!fileInput.hasAttribute('multiple') && allowed.length > 1) {
                allowed = allowed.slice(0, 1);
                trimmed = true;
            }

            // Replace existing entries in FormData for this field
            var useArrayName = fileInput.hasAttribute('multiple');
            var sendName = useArrayName ? (baseName + '[]') : fieldName;
            formData.delete(fieldName);
            if (useArrayName) { formData.delete(baseName + '[]'); }
            for (var j = 0; j < allowed.length; j++) {
                formData.append(sendName, allowed[j], allowed[j].name);
            }

            // Show errors when we had to trim
            if (trimmed) {
                showFieldError(fileInput, 'You can upload up to ' + (maxFiles || 1) + ' file' + ((maxFiles || 1) > 1 ? 's' : '') + '.');
            } else {
                clearFieldError(fileInput);
            }
        });
        formData.append('action', 'nrfm_submit_form');
        formData.append('nrfm_ajax_nonce', nrfm_ajax.nonce);
        
        // Send AJAX request
        fetch(nrfm_ajax.url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            form.dataset.nrfmSubmitting = '0';
            // Re-enable submit button
            if (submitButton) {
                submitButton.disabled = false;
                if (submitButton.tagName === 'BUTTON') {
                    submitButton.textContent = submitButton.dataset.originalText;
                } else {
                    submitButton.value = submitButton.dataset.originalText;
                }
            }
            
            // Show message
            var messageDiv = document.createElement('div');
            messageDiv.className = 'nrfm-message';
            
            if (data.success && data.data) {
                messageDiv.className += ' nrfm-success';
                messageDiv.textContent = data.data.message || 'Thank you! Your form has been submitted.';
                
                // Insert message before form
                wrapper.insertBefore(messageDiv, form);
                
                // Hide form if setting enabled
                if (data.data.hide_form) {
                    form.style.display = 'none';
                }
                
                // Reset form
                form.reset();
                
                // Redirect if URL provided
                if (data.data.redirect_url) {
                    setTimeout(function() {
                        window.location.href = data.data.redirect_url;
                    }, 1000);
                }
                emit('success', form, { response: data });
            } else {
                messageDiv.className += ' nrfm-error';
                messageDiv.textContent = (data.data && data.data.message) ? data.data.message : (form.getAttribute('data-msg-error') || 'An error occurred. Please try again.');
                
                // Insert message before form
                wrapper.insertBefore(messageDiv, form);
                emit('error', form, { response: data });
            }
            
            // Scroll to message
            messageDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });

            emit('submitted', form, { response: data });
        })
        .catch(function(error) {
            form.dataset.nrfmSubmitting = '0';
            // Re-enable submit button
            if (submitButton) {
                submitButton.disabled = false;
                if (submitButton.tagName === 'BUTTON') {
                    submitButton.textContent = submitButton.dataset.originalText;
                } else {
                    submitButton.value = submitButton.dataset.originalText;
                }
            }
            
            // Show error message
            var messageDiv = document.createElement('div');
            messageDiv.className = 'nrfm-message nrfm-error';
            messageDiv.textContent = (form.getAttribute('data-msg-error') || 'An error occurred. Please try again.');
            wrapper.insertBefore(messageDiv, form);
            emit('error', form, { error: error });
            emit('submitted', form, { error: error });
        });
    }

    function showFieldError(input, message) {
        clearFieldError(input);
        var container = input.closest('p, div, section, article') || input.parentNode;
        var span = document.createElement('div');
        span.className = 'nrfm-field-error';
        span.textContent = message || 'Error';
        container.appendChild(span);
    }

    function clearFieldError(input) {
        var container = input.closest('p, div, section, article') || input.parentNode;
        var err = container ? container.querySelector('.nrfm-field-error') : null;
        if (err) err.remove();
    }
    
})();