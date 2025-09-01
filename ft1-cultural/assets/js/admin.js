/**
 * FT1 Cultural Admin JavaScript
 */

(function($) {
    'use strict';
    
    // Global FT1 Cultural object
    window.FT1Cultural = window.FT1Cultural || {};
    
    // Initialize when document is ready
    $(document).ready(function() {
        FT1Cultural.init();
    });
    
    // Main initialization
    FT1Cultural.init = function() {
        this.bindEvents();
        this.initComponents();
        this.loadDashboardData();
    };
    
    // Bind global events
    FT1Cultural.bindEvents = function() {
        // Modal events
        $(document).on('click', '[data-modal]', this.openModal);
        $(document).on('click', '.ft1-modal-close, .ft1-modal-backdrop', this.closeModal);
        $(document).on('keyup', this.handleKeyup);
        
        // Form events
        $(document).on('submit', '.ft1-form', this.handleFormSubmit);
        $(document).on('click', '.ft1-btn[data-action]', this.handleButtonAction);
        
        // Table events
        $(document).on('click', '.ft1-table .action-edit', this.editItem);
        $(document).on('click', '.ft1-table .action-delete', this.deleteItem);
        $(document).on('click', '.ft1-table .action-view', this.viewItem);
        
        // File upload events
        $(document).on('change', '.ft1-file-input', this.handleFileSelect);
        $(document).on('click', '.ft1-upload-area', this.triggerFileSelect);
        $(document).on('dragover dragenter', '.ft1-upload-area', this.handleDragOver);
        $(document).on('dragleave dragend drop', '.ft1-upload-area', this.handleDrop);
        
        // Search and filter events
        $(document).on('input', '.ft1-search-input', this.debounce(this.handleSearch, 300));
        $(document).on('change', '.ft1-filter-select', this.handleFilter);
        
        // Pagination events
        $(document).on('click', '.ft1-pagination a', this.handlePagination);
    };
    
    // Initialize components
    FT1Cultural.initComponents = function() {
        this.initCalendar();
        this.initCharts();
        this.initDataTables();
        this.initDatePickers();
        this.initRichTextEditors();
        this.initTooltips();
    };
    
    // Modal functions
    FT1Cultural.openModal = function(e) {
        e.preventDefault();
        
        var modalId = $(this).data('modal');
        var modal = $('#' + modalId);
        
        if (modal.length) {
            modal.addClass('show');
            $('body').addClass('modal-open');
            
            // Load content if URL is provided
            var url = $(this).data('url');
            if (url) {
                FT1Cultural.loadModalContent(modal, url);
            }
        }
    };
    
    FT1Cultural.closeModal = function(e) {
        if (e.target === this || $(e.target).hasClass('ft1-modal-close')) {
            $('.ft1-modal').removeClass('show');
            $('body').removeClass('modal-open');
        }
    };
    
    FT1Cultural.loadModalContent = function(modal, url) {
        var body = modal.find('.ft1-modal-body');
        
        body.html('<div class="ft1-loading"><div class="ft1-spinner"></div></div>');
        
        $.ajax({
            url: url,
            type: 'GET',
            success: function(response) {
                if (response.success) {
                    body.html(response.data.content);
                } else {
                    body.html('<div class="ft1-alert danger">' + response.data + '</div>');
                }
            },
            error: function() {
                body.html('<div class="ft1-alert danger">' + ft1Cultural.strings.error + '</div>');
            }
        });
    };
    
    // Handle keyboard events
    FT1Cultural.handleKeyup = function(e) {
        // Close modal on Escape
        if (e.keyCode === 27) {
            FT1Cultural.closeModal(e);
        }
    };
    
    // Form handling
    FT1Cultural.handleFormSubmit = function(e) {
        e.preventDefault();
        
        var form = $(this);
        var action = form.data('action') || 'submit';
        var method = form.data('method') || 'POST';
        var url = form.attr('action') || ft1Cultural.ajaxUrl;
        
        // Validate form
        if (!FT1Cultural.validateForm(form)) {
            return false;
        }
        
        // Show loading state
        FT1Cultural.setFormLoading(form, true);
        
        // Prepare form data
        var formData = new FormData(form[0]);
        formData.append('action', action);
        formData.append('nonce', ft1Cultural.nonce);
        
        // Submit form
        $.ajax({
            url: url,
            type: method,
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                FT1Cultural.handleFormResponse(form, response);
            },
            error: function() {
                FT1Cultural.showAlert('danger', ft1Cultural.strings.error);
                FT1Cultural.setFormLoading(form, false);
            }
        });
    };
    
    // Form validation
    FT1Cultural.validateForm = function(form) {
        var isValid = true;
        
        // Clear previous errors
        form.find('.ft1-form-error').remove();
        form.find('.ft1-form-control').removeClass('error');
        
        // Check required fields
        form.find('[required]').each(function() {
            var field = $(this);
            var value = field.val().trim();
            
            if (!value) {
                FT1Cultural.showFieldError(field, ft1Cultural.strings.required_field);
                isValid = false;
            }
        });
        
        // Check email fields
        form.find('input[type="email"]').each(function() {
            var field = $(this);
            var value = field.val().trim();
            
            if (value && !FT1Cultural.isValidEmail(value)) {
                FT1Cultural.showFieldError(field, ft1Cultural.strings.invalid_email);
                isValid = false;
            }
        });
        
        // Check date fields
        form.find('input[type="date"]').each(function() {
            var field = $(this);
            var value = field.val();
            
            if (value && !FT1Cultural.isValidDate(value)) {
                FT1Cultural.showFieldError(field, ft1Cultural.strings.invalid_date);
                isValid = false;
            }
        });
        
        return isValid;
    };
    
    // Show field error
    FT1Cultural.showFieldError = function(field, message) {
        field.addClass('error');
        field.after('<div class="ft1-form-error">' + message + '</div>');
    };
    
    // Handle form response
    FT1Cultural.handleFormResponse = function(form, response) {
        FT1Cultural.setFormLoading(form, false);
        
        if (response.success) {
            FT1Cultural.showAlert('success', ft1Cultural.strings.success);
            
            // Close modal if form is in modal
            var modal = form.closest('.ft1-modal');
            if (modal.length) {
                modal.removeClass('show');
                $('body').removeClass('modal-open');
            }
            
            // Reload page or redirect
            var redirect = form.data('redirect');
            if (redirect) {
                window.location.href = redirect;
            } else {
                // Reload current page section
                FT1Cultural.reloadPageSection();
            }
        } else {
            FT1Cultural.showAlert('danger', response.data || ft1Cultural.strings.error);
        }
    };
    
    // Set form loading state
    FT1Cultural.setFormLoading = function(form, loading) {
        var submitBtn = form.find('button[type="submit"], input[type="submit"]');
        
        if (loading) {
            submitBtn.prop('disabled', true).addClass('loading');
            form.addClass('loading');
        } else {
            submitBtn.prop('disabled', false).removeClass('loading');
            form.removeClass('loading');
        }
    };
    
    // Button actions
    FT1Cultural.handleButtonAction = function(e) {
        e.preventDefault();
        
        var btn = $(this);
        var action = btn.data('action');
        var confirm = btn.data('confirm');
        
        // Show confirmation if required
        if (confirm && !window.confirm(confirm)) {
            return false;
        }
        
        // Handle different actions
        switch (action) {
            case 'delete':
                FT1Cultural.deleteItem.call(this, e);
                break;
            case 'approve':
                FT1Cultural.approveItem.call(this, e);
                break;
            case 'reject':
                FT1Cultural.rejectItem.call(this, e);
                break;
            case 'send':
                FT1Cultural.sendItem.call(this, e);
                break;
            default:
                FT1Cultural.genericAction.call(this, e);
        }
    };
    
    // Generic AJAX action
    FT1Cultural.genericAction = function(e) {
        var btn = $(this);
        var action = btn.data('action');
        var id = btn.data('id');
        var url = btn.data('url') || ft1Cultural.ajaxUrl;
        
        // Set loading state
        btn.prop('disabled', true).addClass('loading');
        
        $.ajax({
            url: url,
            type: 'POST',
            data: {
                action: 'ft1_' + action,
                id: id,
                nonce: ft1Cultural.nonce
            },
            success: function(response) {
                if (response.success) {
                    FT1Cultural.showAlert('success', ft1Cultural.strings.success);
                    FT1Cultural.reloadPageSection();
                } else {
                    FT1Cultural.showAlert('danger', response.data || ft1Cultural.strings.error);
                }
            },
            error: function() {
                FT1Cultural.showAlert('danger', ft1Cultural.strings.error);
            },
            complete: function() {
                btn.prop('disabled', false).removeClass('loading');
            }
        });
    };
    
    // Item actions
    FT1Cultural.editItem = function(e) {
        e.preventDefault();
        
        var id = $(this).data('id');
        var type = $(this).data('type');
        
        // Open edit modal
        FT1Cultural.openEditModal(type, id);
    };
    
    FT1Cultural.deleteItem = function(e) {
        e.preventDefault();
        
        if (!confirm(ft1Cultural.strings.confirm_delete)) {
            return false;
        }
        
        var btn = $(this);
        var id = btn.data('id');
        var type = btn.data('type');
        
        btn.prop('disabled', true).addClass('loading');
        
        $.ajax({
            url: ft1Cultural.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ft1_delete_' + type,
                id: id,
                nonce: ft1Cultural.nonce
            },
            success: function(response) {
                if (response.success) {
                    FT1Cultural.showAlert('success', ft1Cultural.strings.success);
                    btn.closest('tr').fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    FT1Cultural.showAlert('danger', response.data || ft1Cultural.strings.error);
                }
            },
            error: function() {
                FT1Cultural.showAlert('danger', ft1Cultural.strings.error);
            },
            complete: function() {
                btn.prop('disabled', false).removeClass('loading');
            }
        });
    };
    
    FT1Cultural.viewItem = function(e) {
        e.preventDefault();
        
        var id = $(this).data('id');
        var type = $(this).data('type');
        
        // Open view modal
        FT1Cultural.openViewModal(type, id);
    };
    
    // File upload handling
    FT1Cultural.handleFileSelect = function(e) {
        var files = e.target.files;
        var uploadArea = $(this).closest('.ft1-upload-container');
        
        FT1Cultural.processFiles(files, uploadArea);
    };
    
    FT1Cultural.triggerFileSelect = function(e) {
        e.preventDefault();
        $(this).find('.ft1-file-input').click();
    };
    
    FT1Cultural.handleDragOver = function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('dragover');
    };
    
    FT1Cultural.handleDrop = function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var uploadArea = $(this);
        uploadArea.removeClass('dragover');
        
        if (e.type === 'drop') {
            var files = e.originalEvent.dataTransfer.files;
            FT1Cultural.processFiles(files, uploadArea.closest('.ft1-upload-container'));
        }
    };
    
    FT1Cultural.processFiles = function(files, container) {
        var fileList = container.find('.ft1-file-list');
        
        Array.from(files).forEach(function(file) {
            // Validate file
            if (!FT1Cultural.validateFile(file)) {
                return;
            }
            
            // Add file to list
            var fileItem = FT1Cultural.createFileItem(file);
            fileList.append(fileItem);
            
            // Upload file
            FT1Cultural.uploadFile(file, fileItem, container);
        });
    };
    
    FT1Cultural.validateFile = function(file) {
        var maxSize = 10 * 1024 * 1024; // 10MB
        var allowedTypes = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        
        // Check file size
        if (file.size > maxSize) {
            FT1Cultural.showAlert('danger', ft1Cultural.strings.file_too_large);
            return false;
        }
        
        // Check file type
        var extension = file.name.split('.').pop().toLowerCase();
        if (allowedTypes.indexOf(extension) === -1) {
            FT1Cultural.showAlert('danger', ft1Cultural.strings.invalid_file_type);
            return false;
        }
        
        return true;
    };
    
    FT1Cultural.createFileItem = function(file) {
        var extension = file.name.split('.').pop().toLowerCase();
        var size = FT1Cultural.formatFileSize(file.size);
        
        return $(`
            <div class="ft1-file-item" data-file="${file.name}">
                <div class="ft1-file-info">
                    <div class="ft1-file-icon">${extension.toUpperCase()}</div>
                    <div class="ft1-file-details">
                        <div class="ft1-file-name">${file.name}</div>
                        <div class="ft1-file-size">${size}</div>
                    </div>
                </div>
                <div class="ft1-file-progress">
                    <div class="ft1-progress-bar" style="width: 0%"></div>
                </div>
                <div class="ft1-file-actions">
                    <button type="button" class="ft1-btn danger small remove-file">×</button>
                </div>
            </div>
        `);
    };
    
    FT1Cultural.uploadFile = function(file, fileItem, container) {
        var formData = new FormData();
        formData.append('file', file);
        formData.append('action', 'ft1_upload_document');
        formData.append('nonce', ft1Cultural.nonce);
        formData.append('relacionado_tipo', container.data('type'));
        formData.append('relacionado_id', container.data('id'));
        
        $.ajax({
            url: ft1Cultural.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        var percent = Math.round((e.loaded / e.total) * 100);
                        fileItem.find('.ft1-progress-bar').css('width', percent + '%');
                    }
                });
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    fileItem.addClass('uploaded').data('id', response.data.id);
                    fileItem.find('.ft1-file-actions').html(`
                        <button type="button" class="ft1-btn success small">✓</button>
                        <button type="button" class="ft1-btn danger small remove-file">×</button>
                    `);
                } else {
                    fileItem.addClass('error');
                    FT1Cultural.showAlert('danger', response.data || ft1Cultural.strings.error);
                }
            },
            error: function() {
                fileItem.addClass('error');
                FT1Cultural.showAlert('danger', ft1Cultural.strings.error);
            }
        });
    };
    
    // Search and filter
    FT1Cultural.handleSearch = function(e) {
        var query = $(this).val();
        var table = $(this).data('target');
        
        FT1Cultural.filterTable(table, 'search', query);
    };
    
    FT1Cultural.handleFilter = function(e) {
        var value = $(this).val();
        var filter = $(this).data('filter');
        var table = $(this).data('target');
        
        FT1Cultural.filterTable(table, filter, value);
    };
    
    FT1Cultural.filterTable = function(tableId, filterType, value) {
        var table = $('#' + tableId);
        
        // Show loading
        table.addClass('loading');
        
        $.ajax({
            url: ft1Cultural.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ft1_filter_table',
                table: tableId,
                filter_type: filterType,
                filter_value: value,
                nonce: ft1Cultural.nonce
            },
            success: function(response) {
                if (response.success) {
                    table.find('tbody').html(response.data.rows);
                    table.find('.ft1-pagination').html(response.data.pagination);
                }
            },
            complete: function() {
                table.removeClass('loading');
            }
        });
    };
    
    // Pagination
    FT1Cultural.handlePagination = function(e) {
        e.preventDefault();
        
        var page = $(this).data('page');
        var table = $(this).closest('.ft1-table-container').find('table').attr('id');
        
        FT1Cultural.loadTablePage(table, page);
    };
    
    FT1Cultural.loadTablePage = function(tableId, page) {
        var table = $('#' + tableId);
        
        table.addClass('loading');
        
        $.ajax({
            url: ft1Cultural.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ft1_load_table_page',
                table: tableId,
                page: page,
                nonce: ft1Cultural.nonce
            },
            success: function(response) {
                if (response.success) {
                    table.find('tbody').html(response.data.rows);
                    table.find('.ft1-pagination').html(response.data.pagination);
                }
            },
            complete: function() {
                table.removeClass('loading');
            }
        });
    };
    
    // Initialize calendar
    FT1Cultural.initCalendar = function() {
        var calendarEl = document.getElementById('ft1-calendar');
        
        if (calendarEl && typeof FullCalendar !== 'undefined') {
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'pt-br',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,listWeek'
                },
                events: function(info, successCallback, failureCallback) {
                    $.ajax({
                        url: ft1Cultural.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'ft1_get_calendar_events',
                            start: info.startStr,
                            end: info.endStr,
                            nonce: ft1Cultural.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                successCallback(response.data);
                            } else {
                                failureCallback();
                            }
                        },
                        error: failureCallback
                    });
                },
                eventClick: function(info) {
                    FT1Cultural.showEventDetails(info.event);
                }
            });
            
            calendar.render();
        }
    };
    
    // Initialize charts
    FT1Cultural.initCharts = function() {
        if (typeof Chart !== 'undefined') {
            FT1Cultural.initDashboardCharts();
            FT1Cultural.initReportCharts();
        }
    };
    
    FT1Cultural.initDashboardCharts = function() {
        // Projects by status chart
        var ctx = document.getElementById('projects-chart');
        if (ctx) {
            FT1Cultural.loadChartData('projects_by_status', function(data) {
                new Chart(ctx, {
                    type: 'doughnut',
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            });
        }
        
        // Monthly submissions chart
        var ctx2 = document.getElementById('submissions-chart');
        if (ctx2) {
            FT1Cultural.loadChartData('monthly_submissions', function(data) {
                new Chart(ctx2, {
                    type: 'line',
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            });
        }
    };
    
    FT1Cultural.loadChartData = function(chartType, callback) {
        $.ajax({
            url: ft1Cultural.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ft1_get_chart_data',
                chart_type: chartType,
                nonce: ft1Cultural.nonce
            },
            success: function(response) {
                if (response.success) {
                    callback(response.data);
                }
            }
        });
    };
    
    // Initialize data tables
    FT1Cultural.initDataTables = function() {
        $('.ft1-data-table').each(function() {
            var table = $(this);
            var options = {
                responsive: true,
                pageLength: 25,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'
                }
            };
            
            // Add custom options from data attributes
            if (table.data('options')) {
                $.extend(options, table.data('options'));
            }
            
            table.DataTable(options);
        });
    };
    
    // Initialize date pickers
    FT1Cultural.initDatePickers = function() {
        $('.ft1-datepicker').each(function() {
            $(this).attr('type', 'date');
        });
    };
    
    // Initialize rich text editors
    FT1Cultural.initRichTextEditors = function() {
        $('.ft1-rich-editor').each(function() {
            var textarea = $(this);
            
            // Simple rich text editor using contenteditable
            var editor = $('<div class="ft1-editor" contenteditable="true"></div>');
            editor.html(textarea.val());
            
            textarea.hide().after(editor);
            
            editor.on('input', function() {
                textarea.val(editor.html());
            });
        });
    };
    
    // Initialize tooltips
    FT1Cultural.initTooltips = function() {
        $('[data-tooltip]').each(function() {
            $(this).attr('title', $(this).data('tooltip'));
        });
    };
    
    // Load dashboard data
    FT1Cultural.loadDashboardData = function() {
        if ($('.ft1-dashboard').length) {
            $.ajax({
                url: ft1Cultural.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ft1_get_dashboard_data',
                    nonce: ft1Cultural.nonce
                },
                success: function(response) {
                    if (response.success) {
                        FT1Cultural.updateDashboardStats(response.data);
                    }
                }
            });
        }
    };
    
    FT1Cultural.updateDashboardStats = function(data) {
        // Update stat cards
        $('.ft1-stat-card').each(function() {
            var card = $(this);
            var stat = card.data('stat');
            
            if (data[stat]) {
                card.find('.number').text(data[stat].value);
                
                if (data[stat].change) {
                    var changeEl = card.find('.change');
                    changeEl.text(data[stat].change);
                    changeEl.addClass(data[stat].change > 0 ? 'positive' : 'negative');
                }
            }
        });
    };
    
    // Utility functions
    FT1Cultural.showAlert = function(type, message) {
        var alert = $(`
            <div class="ft1-alert ${type}" style="position: fixed; top: 20px; right: 20px; z-index: 10000; min-width: 300px;">
                ${message}
                <button type="button" class="ft1-alert-close" style="float: right; background: none; border: none; font-size: 18px; cursor: pointer;">&times;</button>
            </div>
        `);
        
        $('body').append(alert);
        
        // Auto remove after 5 seconds
        setTimeout(function() {
            alert.fadeOut(300, function() {
                alert.remove();
            });
        }, 5000);
        
        // Manual close
        alert.find('.ft1-alert-close').on('click', function() {
            alert.fadeOut(300, function() {
                alert.remove();
            });
        });
    };
    
    FT1Cultural.reloadPageSection = function() {
        // Reload main content area
        var content = $('.ft1-content');
        if (content.length) {
            location.reload();
        }
    };
    
    FT1Cultural.formatFileSize = function(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        var k = 1024;
        var sizes = ['Bytes', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };
    
    FT1Cultural.isValidEmail = function(email) {
        var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    };
    
    FT1Cultural.isValidDate = function(date) {
        return !isNaN(Date.parse(date));
    };
    
    FT1Cultural.debounce = function(func, wait) {
        var timeout;
        return function executedFunction() {
            var context = this;
            var args = arguments;
            
            var later = function() {
                timeout = null;
                func.apply(context, args);
            };
            
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    };
    
    // Export to global scope
    window.FT1Cultural = FT1Cultural;
    
})(jQuery);

