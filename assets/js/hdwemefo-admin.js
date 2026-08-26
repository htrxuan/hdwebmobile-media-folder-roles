jQuery(document).ready(function ($) {
    'use strict';


    const hdwemefo = {
        selectedFolder: 'all',
        folders: [],
        expandedFolders: [],
        isCollapsed: false,
        sidebarWidth: 280,
        uploadRefreshTimer: null,
        deleteRefreshTimer: null,
        useQueueUploadRefresh: false,
        lastUploadRefreshAt: 0,

        init: function () {
            this.setupGlobalPrefilter();
            this.waitForFrame();
            this.loadFolders();
            this.bindEvents();
            this.interceptMediaQueries();
            this.setupUploaderHooks();
        },

        setupGlobalPrefilter: function () {
            const self = this;

            $.ajaxPrefilter(function (options, originalOptions, jqXHR) {
                let isUpload = false;
                const url = options.url || '';
                let data = options.data;

                // 1. Detect Upload Action
                if (url.indexOf('async-upload.php') !== -1 || (typeof url === 'string' && url.indexOf('action=upload-attachment') !== -1)) {
                    isUpload = true;
                }

                // Handle data inspection safely
                if (typeof data === 'string') {
                    if (data.indexOf('action=upload-attachment') !== -1 || data.indexOf('action=async-upload') !== -1) {
                        isUpload = true;
                    }
                } else if (data instanceof FormData) {
                    if (data.has('action') && (data.get('action') === 'upload-attachment' || data.get('action') === 'async-upload')) {
                        isUpload = true;
                    }
                } else if (typeof data === 'object' && data !== null) {
                    if (data.action === 'upload-attachment' || data.action === 'async-upload') {
                        isUpload = true;
                    }
                }

                // 2. Inject Parameters for Upload
                if (isUpload) {

                    if (typeof options.data === 'string') {
                        if (options.data.indexOf('media_folder=') === -1) {
                            options.data += (options.data.indexOf('?') !== -1 ? '&' : '') + '&media_folder=' + encodeURIComponent(self.selectedFolder);
                            options.data += '&fbv=' + encodeURIComponent(self.selectedFolder);
                        }
                        if (options.data.indexOf('hdwemefo_nonce=') === -1) {
                            options.data += '&hdwemefo_nonce=' + encodeURIComponent(hdwemefo_data.nonce);
                        }
                    } else if (options.data instanceof FormData) {
                        if (!options.data.has('media_folder')) {
                            options.data.append('media_folder', self.selectedFolder);
                            options.data.append('fbv', self.selectedFolder);
                        }
                        if (!options.data.has('hdwemefo_nonce')) {
                            options.data.append('hdwemefo_nonce', hdwemefo_data.nonce);
                        }
                    } else if (typeof options.data === 'object' && options.data !== null) {
                        if (!options.data.media_folder) {
                            options.data.media_folder = self.selectedFolder;
                            options.data.fbv = self.selectedFolder;
                        }
                        if (!options.data.hdwemefo_nonce) {
                            options.data.hdwemefo_nonce = hdwemefo_data.nonce;
                        }
                    }

                    // 3. Wrap Success for UI Update
                    const originalSuccess = options.success;
                    options.success = function (response) {
                        if (!self.useQueueUploadRefresh) {
                            self.schedulePostUploadRefresh();
                        }
                        
                        if (!self.useQueueUploadRefresh && response && (response.success || (typeof response === 'string' && response.indexOf('{"success":true') !== -1))) {
                            self.showNotice('Media uploaded and assigned to your folder!', 'success');
                        }
                        if (originalSuccess) originalSuccess.apply(this, arguments);
                    };
                }

                // 4. Inject parameter for GRID VIEW filtering (query-attachments)
                if (self.selectedFolder && self.selectedFolder !== 'all') {
                    // Normalize data checking
                    const isQueryAction = (
                        (typeof data === 'object' && data !== null && data.action === 'query-attachments') ||
                        (typeof data === 'string' && data.indexOf('action=query-attachments') !== -1)
                    );

                    if (isQueryAction) {
                        if (typeof options.data === 'object' && options.data !== null) {
                            options.data.query = options.data.query || {};
                            options.data.query.media_folder = self.selectedFolder;
                        } else if (typeof options.data === 'string') {
                             if (options.data.indexOf('query%5Bmedia_folder%5D=') === -1 && options.data.indexOf('query[media_folder]=') === -1) {
                                 options.data += '&query%5Bmedia_folder%5D=' + encodeURIComponent(self.selectedFolder);
                             }
                        }
                    }
                }
            });
        },

        waitForFrame: function () {
            const self = this;

            const findAndSetup = () => {
                const $gridFrame = $('.media-frame.mode-grid .media-frame-content');
                const $genericFrame = $('.media-frame-content');
                const $target = $gridFrame.length ? $gridFrame : ($genericFrame.length ? $genericFrame : null);

                if ($target && $target.length && !$target.find('#hdwemefo-sidebar-overlay').length) {
                    self.setupSidebar($target);
                    return true;
                }
                return false;
            };

            if (findAndSetup()) return;

            const checkFrame = setInterval(() => {
                if (findAndSetup()) {
                    clearInterval(checkFrame);
                }
            }, 50);

            if (window.MutationObserver) {
                const observer = new MutationObserver((mutations) => {
                    if (findAndSetup()) {
                        observer.disconnect();
                        clearInterval(checkFrame);
                    }
                });
                observer.observe(document.body, { childList: true, subtree: true });
            }

            setTimeout(() => clearInterval(checkFrame), 15000);
        },

        setupSidebar: function ($container) {
            const $overlay = $('#hdwemefo-sidebar-overlay');
            if ($overlay.length) {
                $container.addClass('hdwemefo-grid-container');
                $overlay.prependTo($container).css('display', 'flex');
                $('body').addClass('hdwemefo-ready');

                this.setupResizer();
                setTimeout(() => this.triggerMediaRefresh(), 300);
            }
        },

        setupResizer: function () {
            const self = this;
            const $resizer = $('#hdwemefo-sidebar-resizer');
            const $sidebar = $('#hdwemefo-sidebar');
            const $collapseBtn = $('#hdwemefo-sidebar-collapse');
            let isResizing = false;

            $resizer.on('mousedown', function (e) {
                isResizing = true;
                $('body').addClass('hdwemefo-resizing');
                $(document).on('mousemove.hdwemefo_resize', function (e) {
                    if (!isResizing) return;
                    let offset = e.pageX - $sidebar.offset().left;
                    if (offset >= 150 && offset <= 600) {
                        self.sidebarWidth = offset;
                        $sidebar.css('width', offset + 'px');
                        $collapseBtn.css('left', (offset - 2) + 'px');
                    }
                });
                $(document).on('mouseup.hdwemefo_resize', function () {
                    isResizing = false;
                    $('body').removeClass('hdwemefo-resizing');
                    $(document).off('.hdwemefo_resize');
                });
            });
        },

        loadFolders: function (onDone) {
            $.ajax({
                url: hdwemefo_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'hdwemefo_get_folders',
                    nonce: hdwemefo_data.nonce,
                    _: new Date().getTime() // Cache buster
                },
                success: (response) => {
                    if (response.success) {
                        this.folders = response.data.folders;
                        $('#hdwemefo-count-all').text(response.data.all_count);
                        $('#hdwemefo-count-unassigned').text(response.data.unassigned_count);
                        this.renderFolders();
                        if (typeof onDone === 'function') {
                            onDone(response);
                        }
                    } else {
                        if (typeof onDone === 'function') {
                            onDone(response);
                        }
                    }
                },
                error: (xhr, status, error) => {
                    if (typeof onDone === 'function') {
                        onDone({ success: false });
                    }
                }
            });
        },

        renderFolders: function () {
            const $container = $('#hdwemefo-custom-folders');

            const buildTree = (parentId) => {
                let html = '<ul>';
                const children = this.folders.filter(f => f.parent == parentId);

                children.forEach(folder => {
                    const hasChildren = this.folders.some(f => f.parent == folder.id);
                    const isExpanded = this.expandedFolders.includes(folder.id);

                    html += `
                    <li data-id="${folder.id}" class="hdwemefo-folder-item ${this.selectedFolder == folder.id ? 'active' : ''}">
                        <div class="hdwemefo-folder-row">
                            ${hasChildren ? `<span class="hdwemefo-toggle dashicons ${isExpanded ? 'dashicons-arrow-down-alt2' : 'dashicons-arrow-right-alt2'}"></span>` : '<span class="hdwemefo-no-toggle"></span>'}
                            <span class="dashicons dashicons-category"></span>
                            <span class="hdwemefo-folder-name" title="${folder.name}">${folder.name}</span>
                            <span class="hdwemefo-count">${folder.count}</span>
                        </div>
                        ${hasChildren && isExpanded ? buildTree(folder.id) : ''}
                    </li>`;
                });
                html += '</ul>';
                return children.length ? html : '';
            };

            $container.html(buildTree(0));
            this.setupDroppable();
        },

        bindEvents: function () {
            const self = this;

            $(document).on('click', '.hdwemefo-folder-row', function (e) {
                const $item = $(this).closest('.hdwemefo-folder-item');
                $('.hdwemefo-folder-item').removeClass('active');
                $item.addClass('active');
                self.selectedFolder = $item.data('id');

                if ($.isNumeric(self.selectedFolder)) {
                    $('#hdwemefo-rename-folder, #hdwemefo-delete-folder').prop('disabled', false);
                } else {
                    $('#hdwemefo-rename-folder, #hdwemefo-delete-folder').prop('disabled', true);
                }

                self.updateUploaderParams();
                self.syncActiveFolder();
                
                // Force a fresh grid refresh
                setTimeout(() => {
                    self.triggerMediaRefresh();
                }, 50);
            });

            $(document).on('click', '.page-title-action, .add-new-h2, #insert-media-button, .media-button', function () {
                self.updateUploaderParams();
                setTimeout(() => self.updateUploaderParams(), 100);
                setTimeout(() => self.updateUploaderParams(), 500);
            });

            $(document).on('click', '.hdwemefo-toggle', function (e) {
                e.stopPropagation();
                const folderId = $(this).closest('.hdwemefo-folder-item').data('id');
                const index = self.expandedFolders.indexOf(folderId);
                if (index > -1) {
                    self.expandedFolders.splice(index, 1);
                } else {
                    self.expandedFolders.push(folderId);
                }
                self.renderFolders();
            });

            $(document).on('keypress', '#hdwemefo-inline-input', function (e) {
                if (e.which == 13) {
                    const name = $(this).val();
                    if (name) {
                        const parentId = $.isNumeric(self.selectedFolder) ? self.selectedFolder : 0;
                        self.ajaxCall('hdwemefo_create_folder', { name: name, parent: parentId }, (res) => {
                            $(this).val('');
                            if (parentId) self.expandedFolders.push(parentId);
                            self.loadFolders();
                            self.showNotice('Folder created: ' + name, 'success');
                        });
                    }
                }
            });

            $('#hdwemefo-add-folder').on('click', function () {
                $('#hdwemefo-inline-input').focus();
            });

            $('#hdwemefo-rename-folder').on('click', function () {
                const $active = $('.hdwemefo-folder-item.active');
                if (!$active.length) return;
                const id = $active.data('id');
                const oldName = $active.find('.hdwemefo-folder-name').first().text();
                const newName = prompt(hdwemefo_data.i18n.enter_name, oldName);
                if (newName && newName !== oldName) {
                    self.ajaxCall('hdwemefo_rename_folder', { id: id, name: newName }, () => {
                        self.loadFolders();
                    });
                }
            });

            $('#hdwemefo-delete-folder').on('click', function () {
                if (confirm(hdwemefo_data.i18n.confirm_delete)) {
                    const id = $('.hdwemefo-folder-item.active').data('id');
                    self.ajaxCall('hdwemefo_delete_folder', { id: id }, () => {
                        self.selectedFolder = 'all';
                        self.loadFolders();
                        self.triggerMediaRefresh();
                    });
                }
            });

            $(document).on('click', '#hdwemefo-sidebar-collapse', function () {
                self.isCollapsed = !self.isCollapsed;
                const $sidebar = $('#hdwemefo-sidebar');
                const $overlay = $('#hdwemefo-sidebar-overlay');
                const $icon = $(this).find('.dashicons');

                if (self.isCollapsed) {
                    $sidebar.addClass('collapsed');
                    $overlay.addClass('collapsed');
                    $icon.removeClass('dashicons-arrow-left-alt2').addClass('dashicons-arrow-right-alt2');
                    $(this).css('left', '0');
                } else {
                    $sidebar.removeClass('collapsed');
                    $overlay.removeClass('collapsed');
                    $icon.removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-left-alt2');
                    $(this).css('left', self.sidebarWidth + 'px');
                }
            });
        },

        setupDroppable: function () {
            const self = this;
            $('.hdwemefo-folder-item').droppable({
                accept: '.attachment',
                hoverClass: 'ui-state-hover',
                drop: function (event, ui) {
                    const folderId = $(this).data('id');
                    const $attachment = ui.draggable;
                    const attachmentId = $attachment.data('id');

                    self.ajaxCall('hdwemefo_move_media', {
                        media_ids: [attachmentId],
                        folder_id: folderId
                    }, () => {
                        self.loadFolders();
                        self.triggerMediaRefresh();
                        self.showNotice('Media moved successfully.', 'success');
                    });
                }
            });
        },

        ajaxCall: function (action, data, successCallback) {
            $.ajax({
                url: hdwemefo_data.ajax_url,
                type: 'POST',
                data: $.extend({
                    action: action,
                    nonce: hdwemefo_data.nonce
                }, data),
                success: (response) => {
                    if (response.success) {
                        if (successCallback) successCallback(response);
                    } else {
                        alert(response.data || 'Error occurred');
                    }
                }
            });
        },

        showNotice: function (message, type = 'info') {
            const $notice = $('<div class="hdwemefo-notice hdwemefo-notice-' + type + '"></div>');
            $notice.text(message);
            $('body').append($notice);
            $notice.fadeIn();
            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 3000);
        },

        triggerCurrentFolderClick: function () {
            const selected = String(this.selectedFolder);
            const selector = '.hdwemefo-folder-item[data-id="' + selected + '"] .hdwemefo-folder-row';
            const $row = $(selector).first();

            if ($row.length) {
                $row.trigger('click');
                return true;
            }

            return false;
        },

        reapplyActiveFolderFilter: function () {
            if (this.refreshCollectionByFolderToggle()) {
                return;
            }

            const selected = String(this.selectedFolder);
            const selector = '.hdwemefo-folder-item[data-id="' + selected + '"] .hdwemefo-folder-row';
            const $row = $(selector).first();
            const $allRow = $('#hdwemefo-item-all .hdwemefo-folder-row').first();

            if ($row.length) {

                // Force a re-query even when selected folder did not change by toggling once.
                if (selected !== 'all' && $allRow.length) {
                    $allRow.trigger('click');
                    setTimeout(() => {
                        $row.trigger('click');
                    }, 70);
                } else {
                    $row.trigger('click');
                }
                return;
            }

            // Fallback if row not found yet.
            this.triggerMediaRefresh();
        },

        refreshCollectionByFolderToggle: function () {
            const selected = String(this.selectedFolder);
            let refreshed = false;

            const toggleCollection = (collection) => {
                if (!collection || !collection.props) {
                    return;
                }

                const token = new Date().getTime();

                if (selected !== 'all') {
                    collection.props.set({
                        'media_folder': 'all',
                        'hdwemefo_refresh': token,
                    });
                }

                setTimeout(() => {
                    collection.props.set({
                        'media_folder': this.selectedFolder,
                        'hdwemefo_refresh': new Date().getTime(),
                    });
                }, 70);

                refreshed = true;
            };

            if (typeof wp !== 'undefined' && wp.media && wp.media.frame) {
                try {
                    const state = wp.media.frame.state();
                    if (state && state.get('library')) {
                        toggleCollection(state.get('library'));
                    }
                } catch (e) {
                }
            }

            if (this.lastLibrary) {
                try {
                    toggleCollection(this.lastLibrary);
                } catch (e) {
                }
            }

            if (refreshed) {
            }

            return refreshed;
        },

        schedulePostUploadRefresh: function () {
            const now = Date.now();
            if (this.lastUploadRefreshAt && (now - this.lastUploadRefreshAt) < 1200) {
                return;
            }

            if (this.uploadRefreshTimer) {
                clearTimeout(this.uploadRefreshTimer);
            }

            // Single-pass refresh per upload completion.
            this.uploadRefreshTimer = setTimeout(() => {
                this.lastUploadRefreshAt = Date.now();
                this.loadFolders(() => {
                    // Trigger current folder click after upload success.
                    setTimeout(() => {
                        if (!this.triggerCurrentFolderClick()) {
                            this.reapplyActiveFolderFilter();
                        }
                    }, 80);
                });
            }, 500);
        },

        schedulePostDeleteRefresh: function () {
            if (this.deleteRefreshTimer) {
                clearTimeout(this.deleteRefreshTimer);
            }

            // Debounced refresh for single and bulk delete operations.
            this.deleteRefreshTimer = setTimeout(() => {
                this.loadFolders(() => {
                    setTimeout(() => {
                        this.reapplyActiveFolderFilter();
                    }, 80);
                });
            }, 450);
        },

        triggerMediaRefresh: function () {
            const refreshToken = new Date().getTime();

            const applyProps = (collection) => {
                if (collection && collection.props) {
                    collection.props.set({
                        'media_folder': this.selectedFolder,
                        'hdwemefo_refresh': refreshToken,
                    });
                }
            };
            
            // Strategy 1: Update WordPress Media Frame (Modal)
            if (typeof wp !== 'undefined' && wp.media && wp.media.frame) {
                try {
                    const state = wp.media.frame.state();
                    if (state && state.get('library')) {
                        const library = state.get('library');

                        applyProps(library);
                    }
                } catch (e) { }
            }
            
            // Strategy 2: Update lastLibrary (Direct grid view)
            if (this.lastLibrary && this.lastLibrary.props) {
                try {
                    applyProps(this.lastLibrary);
                } catch (e) { }
            }
        },

        interceptMediaQueries: function () {
            if (typeof wp === 'undefined' || !wp.media || !wp.media.model) return;

            const Attachments = wp.media.model.Attachments;
            const originalInit = Attachments.prototype.initialize;

            Attachments.prototype.initialize = function () {
                originalInit.apply(this, arguments);

                // Capture this library instance for later refreshing
                // We prefer libraries that are "mirroring" (main view) but take what we get
                if (this.props) {
                    hdwemefo.lastLibrary = this;
                }

                // Define the listener logic once
                const refreshMirror = () => {
                   // If this library is mirroring another (e.g., Query mirroring Selection? No, usually Query mirrors backend)
                   // Actually, in grid view: 
                   // The visible collection is often mirroring the query collection.
                   if (this.mirroring && this.mirroring.props) {
                        try {
                           // Sync main prop
                           this.mirroring.props.set('media_folder', this.props.get('media_folder'));
                           // Force change event with timestamp
                           this.mirroring.props.set('hdwemefo_timestamp', new Date().getTime());
                        } catch (e) { }
                   }
                };

                // Listen for changes
                this.props.on('change:media_folder', refreshMirror);
                this.props.on('change:hdwemefo_refresh', refreshMirror);

                // Refresh counts after delete operations reflected in collection.
                this.on('remove', function () {
                    hdwemefo.schedulePostDeleteRefresh();
                });
            };

            const AttachmentView = wp.media.view.Attachment;
            if (AttachmentView) {
                const originalReady = AttachmentView.prototype.ready;
                AttachmentView.prototype.ready = function () {
                    if (originalReady) originalReady.apply(this, arguments);
                    if (this.$el && this.$el.draggable) {
                        this.$el.draggable({
                            revert: 'invalid',
                            appendTo: 'body',
                            helper: 'clone',
                            start: function (event, ui) {
                                $(ui.helper).css('z-index', 99999);
                                $(this).css('opacity', '0.5');
                            },
                            stop: function () {
                                $(this).css('opacity', '1');
                            }
                        });
                    }
                };
            }
        },

        setupUploaderHooks: function () {
            const self = this;

            // 1. Use native WordPress uploader queue events for robust completion detection.
            if (typeof wp !== 'undefined' && wp.Uploader && wp.Uploader.queue) {
                this.useQueueUploadRefresh = true;

                if (!this._queueAddHandler) {
                    this._queueAddHandler = function () {
                        // Keep multipart params synced for queued uploads.
                        self.updateUploaderParams();
                    };
                    wp.Uploader.queue.on('add', this._queueAddHandler);
                }

                if (!this._queueResetHandler) {
                    this._queueResetHandler = function () {
                        // WordPress resets queue when all uploads are complete.
                        self.schedulePostUploadRefresh();
                        self.showNotice('Media uploaded successfully.', 'success');
                    };
                    wp.Uploader.queue.on('reset', this._queueResetHandler);
                }
            }

            // 2. Hook into existing global uploader if present (e.g. media-new.php)
            if (typeof window.uploader !== 'undefined' && window.uploader.bind) {
                window.uploader.bind('BeforeUpload', function (up, file) {
                    up.settings.multipart_params = up.settings.multipart_params || {};
                    up.settings.multipart_params.media_folder = self.selectedFolder;
                    up.settings.multipart_params.fbv = self.selectedFolder;
                    up.settings.multipart_params.hdwemefo_nonce = hdwemefo_data.nonce;
                });
                
                window.uploader.bind('FileUploaded', function (up, file, response) {
                    if (self.useQueueUploadRefresh) {
                        return;
                    }

                    try {
                        // Check response success
                        const res = JSON.parse(response.response);
                        if (res.success) {
                            self.schedulePostUploadRefresh();
                            self.showNotice('Media uploaded successfully.', 'success');
                        }
                    } catch (e) {
                        // Even if parsing fails, force a refresh if the upload finished
                        self.schedulePostUploadRefresh();
                    }
                });
            }

            // 3. Fallback: Listen to all AJAX complete events for async-upload
            $(document).ajaxComplete(function(event, xhr, settings) {
                const requestData = settings ? settings.data : null;
                const isAsyncUpload = (settings && settings.url && settings.url.indexOf('async-upload.php') !== -1);
                const isAjaxUploadString = (typeof requestData === 'string' && /action=(upload-attachment|async-upload)/.test(requestData));
                const isAjaxUploadObject = (requestData && typeof requestData === 'object' && requestData.action && (requestData.action === 'upload-attachment' || requestData.action === 'async-upload'));
                const isUploadRequest = isAsyncUpload || isAjaxUploadString || isAjaxUploadObject;

                const isDeleteString = (typeof requestData === 'string' && /action=delete-post/.test(requestData));
                const isDeleteObject = (requestData && typeof requestData === 'object' && requestData.action === 'delete-post');
                const isDeleteRequest = isDeleteString || isDeleteObject;

                if (isUploadRequest) {
                    if (!self.useQueueUploadRefresh && xhr.status === 200) {
                        self.schedulePostUploadRefresh();
                    }
                }

                if (isDeleteRequest) {
                    if (xhr.status === 200) {
                        self.schedulePostDeleteRefresh();
                    }
                }
            });
        },

        updateUploaderParams: function () {
            if (typeof wp !== 'undefined' && wp.Uploader && wp.Uploader.defaults) {
                wp.Uploader.defaults.params = wp.Uploader.defaults.params || {};
                wp.Uploader.defaults.params.media_folder = this.selectedFolder;
                wp.Uploader.defaults.params.fbv = this.selectedFolder;
                wp.Uploader.defaults.params.hdwemefo_nonce = hdwemefo_data.nonce;
            }

            $('#hdwemefo-direct-upload-folder, #hdwemefo-direct-upload-folder-fbv').val(this.selectedFolder);
            $('#hdwemefo-direct-upload-nonce').val(hdwemefo_data.nonce);

            if (typeof plupload !== 'undefined' && plupload.uploader && Array.isArray(plupload.uploader)) {
                for (let i = 0; i < plupload.uploader.length; i++) {
                    const up = plupload.uploader[i];
                    if (up && up.setOption) {
                        const newParams = $.extend({}, up.settings.multipart_params, {
                            media_folder: this.selectedFolder,
                            fbv: this.selectedFolder,
                            hdwemefo_nonce: hdwemefo_data.nonce
                        });
                        up.setOption('multipart_params', newParams);
                        up.settings.multipart_params = newParams;
                    }
                }
            }
        },

        syncActiveFolder: function () {
            $.ajax({
                url: hdwemefo_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'hdwemefo_sync_selected_folder',
                    folder_id: this.selectedFolder,
                    nonce: hdwemefo_data.nonce
                }
            });
        }
    };

    hdwemefo.init();
});
