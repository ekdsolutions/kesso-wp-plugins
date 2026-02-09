/**
 * Kesso Init Admin JavaScript
 */

(function() {
    'use strict';

    const kessoInit = window.kessoInit || {};

    // DOM elements
    const form = document.getElementById('kesso-init-form');
    const submitButton = document.getElementById('kesso-apply-all') || document.getElementById('kesso-submit-button');
    const progressFill = document.getElementById('kesso-progress-fill');
    const progressText = document.getElementById('kesso-progress-text');
    const resultsDiv = document.getElementById('kesso-results');
    const pluginsGrid = document.getElementById('kesso-plugins-grid');
    const pluginCount = document.getElementById('kesso-plugin-count');
    const progressModal = document.getElementById('kesso-progress-modal');
    const progressModalClose = document.getElementById('kesso-progress-close');
    const progressSubtitle = document.getElementById('kesso-progress-subtitle');
    const progressCard = document.getElementById('kesso-progress-card');
    const progressCurrentStep = document.getElementById('kesso-progress-current-step');
    const progressGoPlugins = document.getElementById('kesso-progress-go-plugins');

    let progressCycleTimer = null;
    let progressCycleSteps = [];
    let progressCycleIndex = 0;

    // State
    let isProcessing = false;
    let bricksFileUploaded = false;

    /**
     * Initialize
     */
    function init() {
        if (!form) return;

        populateLanguages();
        populateTimezones();
        populateDateFormats();
        populateTimeFormats();
        populateWeekDays();
        populatePlugins();
        populateCurrentSettings();
        checkBuilderStatus();
        checkChildThemeStatus();
        setupFaviconUpload();
        setupBricksUpload();
        setupFormSubmit();
        setupBuilderSelect();
        setupSelectAllButtons();
        setupApplyButton();
        updatePluginCount();
    }

    /**
     * Populate languages dropdown
     */
    function populateLanguages() {
        const select = document.getElementById('WPLANG');
        if (!select || !kessoInit.languages) return;

        kessoInit.languages.forEach(lang => {
            const option = document.createElement('option');
            option.value = lang.value;
            option.textContent = lang.label;
            select.appendChild(option);
        });
    }

    /**
     * Populate timezones dropdown (searchable)
     */
    function populateTimezones() {
        const input = document.getElementById('timezone_string');
        const hiddenInput = document.getElementById('timezone_string_value');
        const dropdown = document.getElementById('timezone_dropdown');
        
        if (!input || !hiddenInput || !dropdown || !kessoInit.timezones) return;

        let selectedTimezone = null;
        let filteredTimezones = [...kessoInit.timezones];
        let highlightedIndex = -1;

        // Function to filter timezones based on search query
        function filterTimezones(query) {
            if (!query || query.trim() === '') {
                filteredTimezones = [...kessoInit.timezones];
            } else {
                const lowerQuery = query.toLowerCase();
                filteredTimezones = kessoInit.timezones.filter(tz => 
                    tz.label.toLowerCase().includes(lowerQuery) ||
                    tz.value.toLowerCase().includes(lowerQuery)
                );
            }
            highlightedIndex = -1;
            renderDropdown();
        }

        // Function to render dropdown options
        function renderDropdown() {
            dropdown.innerHTML = '';
            
            if (filteredTimezones.length === 0) {
                const noResults = document.createElement('div');
                noResults.className = 'kesso-timezone-option kesso-timezone-no-results';
                noResults.textContent = 'No timezones found';
                dropdown.appendChild(noResults);
                dropdown.style.display = 'block';
                return;
            }

            // Limit to 10 results for performance
            const displayResults = filteredTimezones.slice(0, 10);
            
            displayResults.forEach((tz, index) => {
                const option = document.createElement('div');
                option.className = 'kesso-timezone-option';
                if (index === highlightedIndex) {
                    option.classList.add('is-highlighted');
                }
                option.textContent = tz.label;
                option.dataset.value = tz.value;
                
                option.addEventListener('click', () => {
                    selectTimezone(tz);
                });

                option.addEventListener('mouseenter', () => {
                    highlightedIndex = index;
                    renderDropdown();
                });

                dropdown.appendChild(option);
            });

            if (filteredTimezones.length > 10) {
                const moreResults = document.createElement('div');
                moreResults.className = 'kesso-timezone-option kesso-timezone-more';
                moreResults.textContent = `+${filteredTimezones.length - 10} more results. Type to narrow search.`;
                dropdown.appendChild(moreResults);
            }

            dropdown.style.display = 'block';
        }

        // Function to select a timezone
        function selectTimezone(tz) {
            selectedTimezone = tz;
            input.value = tz.label;
            hiddenInput.value = tz.value;
            dropdown.style.display = 'none';
            input.classList.remove('is-invalid');
        }

        // Input event - filter as user types
        input.addEventListener('input', (e) => {
            const query = e.target.value;
            filterTimezones(query);
        });

        // Focus event - show dropdown
        input.addEventListener('focus', () => {
            if (filteredTimezones.length > 0) {
                renderDropdown();
            }
        });

        // Keyboard navigation
        input.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (highlightedIndex < filteredTimezones.length - 1 && highlightedIndex < 9) {
                    highlightedIndex++;
                    renderDropdown();
                    // Scroll highlighted option into view
                    const highlighted = dropdown.querySelector('.is-highlighted');
                    if (highlighted) {
                        highlighted.scrollIntoView({ block: 'nearest' });
                    }
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (highlightedIndex > 0) {
                    highlightedIndex--;
                    renderDropdown();
                    const highlighted = dropdown.querySelector('.is-highlighted');
                    if (highlighted) {
                        highlighted.scrollIntoView({ block: 'nearest' });
                    }
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (highlightedIndex >= 0 && filteredTimezones[highlightedIndex]) {
                    selectTimezone(filteredTimezones[highlightedIndex]);
                }
            } else if (e.key === 'Escape') {
                dropdown.style.display = 'none';
                input.blur();
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });

        // Initialize with default value if set
        if (kessoInit.currentSettings && kessoInit.currentSettings.timezone_string) {
            const currentTz = kessoInit.timezones.find(tz => tz.value === kessoInit.currentSettings.timezone_string);
            if (currentTz) {
                selectTimezone(currentTz);
            }
        } else {
            // Default to UTC
            const utcTz = kessoInit.timezones.find(tz => tz.value === 'UTC');
            if (utcTz) {
                selectTimezone(utcTz);
            }
        }
    }

    /**
     * Populate date formats
     */
    function populateDateFormats() {
        const select = document.getElementById('date_format');
        if (!select || !kessoInit.dateFormats) return;

        Object.keys(kessoInit.dateFormats).forEach(format => {
            const option = document.createElement('option');
            option.value = format;
            option.textContent = kessoInit.dateFormats[format];
            select.appendChild(option);
        });
    }

    /**
     * Populate time formats
     */
    function populateTimeFormats() {
        const select = document.getElementById('time_format');
        if (!select || !kessoInit.timeFormats) return;

        Object.keys(kessoInit.timeFormats).forEach(format => {
            const option = document.createElement('option');
            option.value = format;
            option.textContent = kessoInit.timeFormats[format];
            select.appendChild(option);
        });
    }

    /**
     * Populate week days
     */
    function populateWeekDays() {
        const select = document.getElementById('start_of_week');
        if (!select || !kessoInit.weekDays) return;

        kessoInit.weekDays.forEach(day => {
            const option = document.createElement('option');
            option.value = day.value;
            option.textContent = day.label;
            select.appendChild(option);
        });
    }

    /**
     * Populate plugins grid
     */
    function populatePlugins() {
        if (!pluginsGrid || !kessoInit.plugins) return;

        pluginsGrid.innerHTML = '';

        // Sort plugins by custom category order, then by name
        const categoryOrder = ['tracking', 'content', 'optimize', 'develop', 'ecommerce', 'security'];
        
        const sortedPlugins = [...kessoInit.plugins].sort((a, b) => {
            const categoryA = (a.category || '').toLowerCase();
            const categoryB = (b.category || '').toLowerCase();
            
            // Get index in custom order (higher number = lower priority)
            const indexA = categoryOrder.indexOf(categoryA);
            const indexB = categoryOrder.indexOf(categoryB);
            
            // If both categories are in the order list, sort by their position
            if (indexA !== -1 && indexB !== -1) {
                if (indexA !== indexB) {
                    return indexA - indexB;
                }
            } else if (indexA !== -1) {
                // A is in order, B is not - A comes first
                return -1;
            } else if (indexB !== -1) {
                // B is in order, A is not - B comes first
                return 1;
            } else {
                // Neither is in order, sort alphabetically
                if (categoryA !== categoryB) {
                    return categoryA.localeCompare(categoryB);
                }
            }
            
            // Then sort by name within same category
            return (a.name || '').localeCompare(b.name || '');
        });

        sortedPlugins.forEach(plugin => {
            const item = document.createElement('div');
            item.className = 'kesso-plugin-card';
            item.setAttribute('role', 'button');
            item.setAttribute('tabindex', '0');
            
            // Check if plugin is installed/active
            const isInstalled = plugin.installed || false;
            const isActive = plugin.active || false;
            
            if (isInstalled || isActive) {
                item.classList.add('kesso-plugin-installed');
            }

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.id = `plugin-${plugin.slug}`;
            checkbox.name = 'plugins[]';
            checkbox.value = plugin.slug;
            checkbox.disabled = isInstalled || isActive;

            const text = document.createElement('div');
            text.className = 'kesso-plugin-text';
            
            const name = document.createElement('div');
            name.className = 'kesso-plugin-name';
            
            // Add category badge first if available
            if (plugin.category) {
                const badge = document.createElement('span');
                badge.className = 'kesso-plugin-badge';
                badge.textContent = plugin.category;
                badge.setAttribute('data-category', plugin.category.toLowerCase().replace(/\s+/g, '-'));
                name.appendChild(badge);
            }
            
            // Create text node for plugin name
            let nameText = plugin.name;
            if (isActive) {
                nameText += ' (Active)';
            } else if (isInstalled) {
                nameText += ' (Installed)';
            }
            name.appendChild(document.createTextNode(nameText));
            
            const desc = document.createElement('div');
            desc.className = 'kesso-plugin-desc';
            desc.textContent = plugin.description;

            text.appendChild(name);
            text.appendChild(desc);

            item.appendChild(checkbox);
            item.appendChild(text);
            pluginsGrid.appendChild(item);

            // Update count when checkbox changes
            checkbox.addEventListener('change', updatePluginCount);

            // Make the whole card clickable to toggle the checkbox (unless disabled)
            const toggle = () => {
                if (checkbox.disabled) return;
                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            };

            item.addEventListener('click', (e) => {
                // If user clicked the checkbox itself, let the browser handle it.
                if (e.target && e.target.tagName === 'INPUT') return;
                toggle();
            });

            item.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggle();
                }
            });
        });
    }

    /**
     * Setup select all / clear selection buttons
     */
    function setupSelectAllButtons() {
        const selectAllBtn = document.getElementById('kesso-select-all');
        const clearBtn = document.getElementById('kesso-clear-selection');
        
        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function() {
                const checkboxes = pluginsGrid.querySelectorAll('input[type="checkbox"]:not(:disabled)');
                checkboxes.forEach(cb => cb.checked = true);
                updatePluginCount();
            });
        }
        
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                const checkboxes = pluginsGrid.querySelectorAll('input[type="checkbox"]:not(:disabled)');
                checkboxes.forEach(cb => cb.checked = false);
                updatePluginCount();
            });
        }
    }

    /**
     * Update plugin count
     */
    function updatePluginCount() {
        if (!pluginCount) return;
        
        const checked = pluginsGrid.querySelectorAll('input[type="checkbox"]:checked:not(:disabled)').length;
        pluginCount.textContent = `${checked} selected`;
    }

    /**
     * Populate current settings
     */
    function populateCurrentSettings() {
        if (!kessoInit.currentSettings) return;

        const settings = kessoInit.currentSettings;

        if (settings.blogname) {
            const field = document.getElementById('blogname');
            if (field) field.value = settings.blogname;
        }

        if (settings.blogdescription) {
            const field = document.getElementById('blogdescription');
            if (field) field.value = settings.blogdescription;
        }

        if (settings.WPLANG !== undefined) {
            const field = document.getElementById('WPLANG');
            if (field) field.value = settings.WPLANG;
        }

        if (settings.timezone_string) {
            const field = document.getElementById('timezone_string');
            const hiddenField = document.getElementById('timezone_string_value');
            if (field && hiddenField && kessoInit.timezones) {
                const tz = kessoInit.timezones.find(t => t.value === settings.timezone_string);
                if (tz) {
                    field.value = tz.label;
                    hiddenField.value = tz.value;
                }
            }
        }

        if (settings.date_format) {
            const field = document.getElementById('date_format');
            if (field) field.value = settings.date_format;
        }

        if (settings.time_format) {
            const field = document.getElementById('time_format');
            if (field) field.value = settings.time_format;
        }

        if (settings.start_of_week !== undefined) {
            const field = document.getElementById('start_of_week');
            if (field) field.value = settings.start_of_week;
        }

        if (settings.site_icon) {
            const field = document.getElementById('site_icon');
            if (field) field.value = settings.site_icon;
        }

        // Display existing favicon if available
        if (settings.site_icon_url) {
            const preview = document.getElementById('kesso-favicon-preview');
            if (preview) {
                preview.innerHTML = '';
                const img = document.createElement('img');
                img.src = settings.site_icon_url;
                img.alt = 'Site icon';
                preview.appendChild(img);
            }
        }
    }

    /**
     * Check builder status and update UI
     */
    function checkBuilderStatus() {
        if (!kessoInit.themeStatus) return;

        const status = kessoInit.themeStatus;
        const builderCards = document.querySelectorAll('.kesso-builder-card');

        builderCards.forEach(card => {
            const builder = card.dataset.builder;
            const input = card.querySelector('input[type="radio"]');
            const badge = card.querySelector('.kesso-builder-badge');

            let isInstalled = false;

            if (builder === 'bricks') {
                isInstalled = status.bricks_installed || status.bricks_active || status.active_builder === 'bricks';
                
                // If Bricks is installed, show success state in upload field
                if (isInstalled) {
                    const bricksUpload = document.getElementById('kesso-bricks-upload');
                    if (bricksUpload && bricksUpload.style.display !== 'none') {
                        setBricksUploadSuccess();
                        bricksFileUploaded = true;
                    }
                }
            } else if (builder === 'elementor') {
                isInstalled = (status.elementor_installed || status.elementor_active) && 
                             (status.hello_elementor_installed || status.hello_elementor_active || status.active_builder === 'elementor');
            }

            if (isInstalled) {
                card.classList.add('kesso-builder-installed');
                if (input) input.disabled = true;
                if (badge) badge.textContent = '(Installed)';
            }
        });
    }

    /**
     * Check child theme status
     */
    function checkChildThemeStatus() {
        if (!kessoInit.themeStatus) return;

        const status = kessoInit.themeStatus;
        const childThemeRow = document.querySelector('.kesso-child-theme-row');
        const checkbox = document.getElementById('install_child_theme');
        const badge = document.querySelector('.kesso-child-theme-badge');

        if (status.is_child_theme && childThemeRow && checkbox && badge) {
            childThemeRow.classList.add('kesso-child-theme-installed');
            checkbox.disabled = true;
            badge.textContent = '(Already Installed)';
        }
    }

    /**
     * Setup favicon upload
     */
    function setupFaviconUpload() {
        const button = document.getElementById('kesso-favicon-button');
        const preview = document.getElementById('kesso-favicon-preview');
        const hiddenInput = document.getElementById('site_icon');
        const uploader = document.getElementById('kesso-favicon-uploader');

        if (!button || !window.wp || !window.wp.media) return;

        const openMedia = function(e) {
            e.preventDefault();

            const mediaUploader = window.wp.media({
                title: kessoInit.strings?.selectFavicon || 'Select Site Icon',
                button: {
                    text: kessoInit.strings?.useFavicon || 'Use as Site Icon'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });

            mediaUploader.on('select', function() {
                const attachment = mediaUploader.state().get('selection').first().toJSON();
                
                if (preview && hiddenInput) {
                    preview.innerHTML = '';
                    const img = document.createElement('img');
                    img.src = attachment.url;
                    img.alt = 'Site icon';
                    preview.appendChild(img);
                    hiddenInput.value = attachment.id;
                }
            });

            mediaUploader.open();
        };

        // Button click (explicit)
        button.addEventListener('click', openMedia);

        // Card click (matches reference UX)
        if (uploader) {
            uploader.addEventListener('click', function(e) {
                // Don’t double-trigger if the click is on the button itself.
                if (e.target === button || button.contains(e.target)) return;
                openMedia(e);
            });

            // Keyboard accessibility
            uploader.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    openMedia(e);
                }
            });
        }
    }

    /**
     * Setup Bricks ZIP upload
     */
    function setupBricksUpload() {
        const bricksInput = document.getElementById('bricks_zip');
        if (!bricksInput) return;

        bricksInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            // Upload file via REST API
            uploadBricksFile(file);
        });
    }

    /**
     * Upload Bricks ZIP file
     */
    function uploadBricksFile(file) {
        const formData = new FormData();
        formData.append('bricks_zip', file);
        const bricksUpload = document.getElementById('kesso-bricks-upload');
        const bricksInput = document.getElementById('bricks_zip');

        // Show uploading state
        if (bricksUpload) {
            bricksUpload.classList.add('is-uploading');
            bricksUpload.classList.remove('is-success', 'is-error');
        }

        updateProgress(10, 'Uploading Bricks...');

        fetch(kessoInit.restUrl + 'theme/upload-bricks', {
            method: 'POST',
            headers: {
                'X-WP-Nonce': kessoInit.nonce
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bricksFileUploaded = true;
                updateProgress(20, 'Bricks uploaded successfully');
                // Show success state in upload field
                setBricksUploadSuccess();
            } else {
                // Show error state
                if (bricksUpload) {
                    bricksUpload.classList.remove('is-uploading');
                    bricksUpload.classList.add('is-error');
                }
                alert(data.message || 'Failed to upload Bricks file');
            }
        })
        .catch(error => {
            console.error('Error uploading Bricks:', error);
            // Show error state
            if (bricksUpload) {
                bricksUpload.classList.remove('is-uploading');
                bricksUpload.classList.add('is-error');
            }
            alert('Error uploading Bricks file');
        });
    }

    /**
     * Set Bricks upload field to success state
     */
    function setBricksUploadSuccess() {
        const bricksUpload = document.getElementById('kesso-bricks-upload');
        const bricksInput = document.getElementById('bricks_zip');
        
        if (!bricksUpload) return;

        // Remove uploading/error states
        bricksUpload.classList.remove('is-uploading', 'is-error');
        bricksUpload.classList.add('is-success');

        // Update icon to checkmark
        const icon = bricksUpload.querySelector('.kesso-favicon-icon');
        if (icon) {
            icon.innerHTML = '<span class="material-symbols-outlined kesso-upload-symbol">check_circle</span>';
        }

        // Update text
        const title = bricksUpload.querySelector('.kesso-favicon-title');
        if (title) {
            title.textContent = 'Bricks theme installed successfully';
        }
        const subtitle = bricksUpload.querySelector('.kesso-favicon-subtitle');
        if (subtitle) {
            subtitle.textContent = 'The theme has been installed and activated.';
        }

        // Disable file input
        if (bricksInput) {
            bricksInput.disabled = true;
        }
    }

    /**
     * Setup builder selection
     */
    function setupBuilderSelect() {
        const builderInputs = document.querySelectorAll('input[name="builder"]');
        
        builderInputs.forEach(input => {
            input.addEventListener('change', function() {
                handleBuilderSelect(this.value);
                updateBuilderActiveState();
            });
        });
        
        // Initial state
        updateBuilderActiveState();
    }
    
    /**
     * Update builder card active state
     */
    function updateBuilderActiveState() {
        const builderCards = document.querySelectorAll('.kesso-builder-card');
        const checkedInput = document.querySelector('input[name="builder"]:checked');
        
        builderCards.forEach(card => {
            card.classList.remove('kesso-builder-card--active');
        });
        
        if (checkedInput) {
            const card = checkedInput.closest('.kesso-builder-card');
            if (card) {
                card.classList.add('kesso-builder-card--active');
            }
        }
    }

    /**
     * Handle builder selection
     */
    function handleBuilderSelect(builder) {
        const bricksCard = document.querySelector('[data-builder="bricks"]');
        const bricksUpload = document.getElementById('kesso-bricks-upload');
        const bricksInput = document.getElementById('bricks_zip');

        // Check if builder is already installed
        if (bricksCard && bricksCard.classList.contains('kesso-builder-installed')) {
            if (bricksUpload) {
                bricksUpload.style.display = 'flex';
                // Show success state if already installed
                setBricksUploadSuccess();
            }
            return;
        }

        if (builder === 'bricks') {
            if (bricksUpload) {
                bricksUpload.style.display = 'flex';
                // Reset state when showing (in case it was in error/uploading state)
                bricksUpload.classList.remove('is-success', 'is-error', 'is-uploading');
                // Reset icon and text
                const icon = bricksUpload.querySelector('.kesso-favicon-icon');
                if (icon) {
                    icon.innerHTML = '<span class="material-symbols-outlined kesso-upload-symbol">upload_file</span>';
                }
                const title = bricksUpload.querySelector('.kesso-favicon-title');
                if (title) {
                    title.textContent = 'Upload Bricks ZIP';
                }
                const subtitle = bricksUpload.querySelector('.kesso-favicon-subtitle');
                if (subtitle) {
                    subtitle.textContent = 'Choose the ZIP file you downloaded from your Bricks account.';
                }
                if (bricksInput) {
                    bricksInput.disabled = false;
                }
            }
        } else {
            if (bricksUpload) bricksUpload.style.display = 'none';
            if (bricksInput) {
                bricksInput.value = '';
                bricksInput.disabled = false;
            }
            bricksFileUploaded = false;
        }
    }

    /**
     * Setup form submission
     */
    function setupFormSubmit() {
        if (!form) return;

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            if (isProcessing) return;

            applyAllChanges();
        });
    }

    /**
     * Apply all changes
     */
    function applyAllChanges() {
        isProcessing = true;
        submitButton.disabled = true;
        resultsDiv.style.display = 'none';

        const formData = new FormData(form);
        const data = {
            plugins: [],
            builder: '',
            install_child_theme: false,
            settings: {}
        };

        // Collect plugins
        const pluginCheckboxes = form.querySelectorAll('input[name="plugins[]"]:checked:not(:disabled)');
        pluginCheckboxes.forEach(cb => {
            data.plugins.push(cb.value);
        });

        // Get builder
        const builderInput = form.querySelector('input[name="builder"]:checked:not(:disabled)');
        if (builderInput) {
            data.builder = builderInput.value;
        }

        // Check if Bricks file was uploaded
        if (data.builder === 'bricks' && !bricksFileUploaded) {
            const bricksInput = document.getElementById('bricks_zip');
            if (!bricksInput || !bricksInput.files[0]) {
                alert(kessoInit.strings?.uploadBricks || 'Please upload the Bricks theme ZIP file.');
                isProcessing = false;
                submitButton.disabled = false;
                return;
            }
        }

        // Get child theme option
        const childThemeCheckbox = document.getElementById('install_child_theme');
        if (childThemeCheckbox && childThemeCheckbox.checked && !childThemeCheckbox.disabled) {
            data.install_child_theme = true;
        }

        // Collect settings
        data.settings.blogname = formData.get('blogname') || '';
        data.settings.blogdescription = formData.get('blogdescription') || '';
        data.settings.WPLANG = formData.get('WPLANG') || '';
        // Get timezone from hidden input (the actual value)
        const timezoneHiddenInput = document.getElementById('timezone_string_value');
        data.settings.timezone_string = timezoneHiddenInput ? timezoneHiddenInput.value : '';
        data.settings.date_format = formData.get('date_format') || '';
        data.settings.time_format = formData.get('time_format') || '';
        data.settings.start_of_week = formData.get('start_of_week') || '0';
        data.settings.site_icon = formData.get('site_icon') || '';
        data.settings.create_privacy_policy = formData.get('create_privacy_policy') === '1' ? true : false;

        // Show loading state
        showLoadingState();
        updateProgress(10, kessoInit.strings?.applying || 'Applying changes...');

        // Show progress modal with steps (settings > builder > plugins)
        showProgressModal({
            hasSettings: true,
            hasBuilder: !!data.builder || !!data.install_child_theme,
            hasPlugins: Array.isArray(data.plugins) && data.plugins.length > 0,
        });

        // Process operations sequentially: settings/theme first, then plugins one by one
        processOperationsSequentially(data);
    }

    /**
     * Process all operations sequentially (settings/theme first, then plugins queue)
     */
    async function processOperationsSequentially(data) {
        const results = {
            plugins: {
                installed: [],
                activated: [],
                skipped: [],
                failed: []
            },
            theme: null,
            child_theme: null,
            settings: null,
            errors: []
        };

        try {
            // Step 1: Process settings, theme, and child theme (non-plugin operations)
            const nonPluginData = {
                builder: data.builder,
                install_child_theme: data.install_child_theme,
                settings: data.settings
            };

            // Only call batch endpoint if there are non-plugin operations
            const hasNonPluginOps = nonPluginData.builder || nonPluginData.install_child_theme || Object.keys(nonPluginData.settings).length > 0;
            
            if (hasNonPluginOps) {
                updateProgress(20, 'Configuring settings and theme...');
                setProgressCurrentStep('Settings', false);
                
                const batchResponse = await fetch(kessoInit.restUrl + 'batch/apply', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': kessoInit.nonce
                    },
                    body: JSON.stringify(nonPluginData)
                });

                if (!batchResponse.ok) {
                    throw new Error(`HTTP error! status: ${batchResponse.status}`);
                }

                let batchResult;
                try {
                    const rawText = await batchResponse.text();
                    batchResult = JSON.parse(rawText);
                } catch (e) {
                    // Try to extract JSON
                    const trimmed = await batchResponse.text();
                    const start = trimmed.indexOf('{');
                    const end = trimmed.lastIndexOf('}');
                    if (start !== -1 && end !== -1 && end > start) {
                        batchResult = JSON.parse(trimmed.substring(start, end + 1));
                    } else {
                        throw new Error("Invalid server response");
                    }
                }

                if (batchResult.data) {
                    results.theme = batchResult.data.theme;
                    results.child_theme = batchResult.data.child_theme;
                    results.settings = batchResult.data.settings;
                    if (batchResult.data.errors) {
                        results.errors.push(...batchResult.data.errors);
                    }
                }
            }

            // Step 2: Install plugins one by one (queue-based)
            if (data.plugins && data.plugins.length > 0) {
                updateProgress(30, `Installing plugins (0/${data.plugins.length})...`);
                setProgressCurrentStep('Plugins', false);
                
                for (let i = 0; i < data.plugins.length; i++) {
                    const slug = data.plugins[i];
                    
                    // Find plugin name for display
                    const plugin = kessoInit.plugins.find(p => p.slug === slug);
                    const pluginName = plugin ? plugin.name : slug;
                    
                    // Update progress
                    const progressPercent = 30 + Math.floor((i / data.plugins.length) * 60);
                    updateProgress(progressPercent, `Installing: ${pluginName} (${i + 1}/${data.plugins.length})...`);
                    setProgressCurrentStep(`Installing: ${pluginName}`, true);
                    
                    try {
                        // Small delay to allow browser to process events
                        await new Promise(resolve => setTimeout(resolve, 100));
                        
                        const pluginResponse = await fetch(kessoInit.restUrl + 'plugins/install-single', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': kessoInit.nonce
                            },
                            body: JSON.stringify({ slug: slug })
                        });

                        if (!pluginResponse.ok) {
                            throw new Error(`HTTP error! status: ${pluginResponse.status}`);
                        }

                        const pluginResult = await pluginResponse.json();
                        
                        if (pluginResult.success && pluginResult.data) {
                            const status = pluginResult.data.status;
                            if (status === 'activated') {
                                results.plugins.activated.push(slug);
                            } else if (status === 'installed') {
                                results.plugins.installed.push(slug);
                            } else if (status === 'skipped') {
                                results.plugins.skipped.push(slug);
                            } else if (status === 'failed') {
                                results.plugins.failed.push({
                                    slug: slug,
                                    error: pluginResult.data.error || 'Unknown error'
                                });
                            }
                        } else {
                            results.plugins.failed.push({
                                slug: slug,
                                error: pluginResult.message || 'Installation failed'
                            });
                        }
                    } catch (error) {
                        console.error(`Error installing ${pluginName}:`, error);
                        results.plugins.failed.push({
                            slug: slug,
                            error: error.message || 'Unknown error'
                        });
                    }
                }
            }

            // Mark wizard as completed if no critical errors
            if (results.errors.length === 0 && results.plugins.failed.length === 0) {
                try {
                    await fetch(kessoInit.restUrl + 'complete', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': kessoInit.nonce
                        }
                    });
                } catch (e) {
                    // Ignore errors on completion mark
                }
            }

            // Display results
            hideLoadingState();
            displayResults({
                success: results.errors.length === 0 && results.plugins.failed.length === 0,
                message: results.errors.length > 0 || results.plugins.failed.length > 0 
                    ? 'Setup completed with some errors.' 
                    : 'Setup completed successfully!',
                data: results
            });
            updateProgress(100, kessoInit.strings?.complete || 'Setup complete!');
            finalizeProgressModal({
                success: results.errors.length === 0 && results.plugins.failed.length === 0,
                data: results
            });

        } catch (error) {
            hideLoadingState();
            console.error('Error:', error);
            let errorMessage = error.message || 'An error occurred';
            
            displayResults({
                success: false,
                message: 'Error during setup',
                data: { 
                    ...results,
                    errors: [...results.errors, { 
                        step: 'general', 
                        message: errorMessage 
                    }]
                }
            });
            updateProgress(0, 'Error - please verify site');
            failProgressModal(errorMessage);
        } finally {
            isProcessing = false;
            submitButton.disabled = false;
            enableProgressModalClose();
        }
    }

    function showProgressModal(flags) {
        if (!progressModal || !progressCard || !progressCurrentStep) return;

        // Reset UI
        progressCard.classList.remove('is-done', 'is-failed');
        progressCard.classList.add('is-running');
        if (progressGoPlugins) progressGoPlugins.style.display = 'none';

        // Build cycle steps (always in this order)
        progressCycleSteps = [];
        progressCycleSteps.push('Settings');
        if (flags?.hasBuilder) progressCycleSteps.push('Builder');
        if (flags?.hasPlugins) progressCycleSteps.push('Plugins');
        if (progressCycleSteps.length === 0) progressCycleSteps = ['Settings'];

        progressCycleIndex = 0;
        setProgressCurrentStep(progressCycleSteps[0]);

        // Start cycling animation
        if (progressCycleTimer) {
            clearInterval(progressCycleTimer);
            progressCycleTimer = null;
        }
        progressCycleTimer = setInterval(() => {
            progressCycleIndex = (progressCycleIndex + 1) % progressCycleSteps.length;
            setProgressCurrentStep(progressCycleSteps[progressCycleIndex], true);
        }, 1200);

        if (progressSubtitle) {
            progressSubtitle.textContent = 'Applying changes. This may take a few minutes…';
        }

        progressModal.classList.add('is-open');
        progressModal.setAttribute('aria-hidden', 'false');

        // Disable close while running
        if (progressModalClose) {
            progressModalClose.disabled = true;
        }

        // Backdrop click closes only when enabled
        const backdrop = progressModal.querySelector('[data-kesso-close="1"]');
        if (backdrop) {
            backdrop.onclick = () => {
                if (progressModalClose && progressModalClose.disabled) return;
                hideProgressModal();
            };
        }
        if (progressModalClose) {
            progressModalClose.onclick = () => hideProgressModal();
        }
    }

    function enableProgressModalClose() {
        if (progressModalClose) progressModalClose.disabled = false;
    }

    function hideProgressModal() {
        if (!progressModal) return;
        if (progressCycleTimer) {
            clearInterval(progressCycleTimer);
            progressCycleTimer = null;
        }
        progressModal.classList.remove('is-open');
        progressModal.setAttribute('aria-hidden', 'true');
    }

    function setProgressCurrentStep(label, animate = false) {
        if (!progressCurrentStep) return;
        if (animate) {
            progressCurrentStep.classList.remove('is-switching');
            // Force reflow so animation re-triggers
            void progressCurrentStep.offsetWidth;
            progressCurrentStep.classList.add('is-switching');
        }
        progressCurrentStep.textContent = label;
    }

    function finalizeProgressModal(result) {
        try {
            if (progressCycleTimer) {
                clearInterval(progressCycleTimer);
                progressCycleTimer = null;
            }

            const ok = !!result?.success;
            if (progressCard) {
                progressCard.classList.remove('is-running');
                progressCard.classList.toggle('is-done', ok);
                progressCard.classList.toggle('is-failed', !ok);
            }

            setProgressCurrentStep(ok ? 'Done' : 'Completed with errors');
            if (progressSubtitle) {
                progressSubtitle.textContent = result?.success ? 'Completed successfully.' : 'Completed with errors.';
            }
            if (progressGoPlugins) {
                progressGoPlugins.style.display = 'inline-flex';
            }
        } catch (e) {
            // no-op
        }
    }

    function failProgressModal(message) {
        if (progressCycleTimer) {
            clearInterval(progressCycleTimer);
            progressCycleTimer = null;
        }
        if (progressCard) {
            progressCard.classList.remove('is-running');
            progressCard.classList.add('is-failed');
        }
        setProgressCurrentStep('Error');
        if (progressSubtitle) progressSubtitle.textContent = 'The process ended with an error.';
        if (progressGoPlugins) {
            progressGoPlugins.style.display = 'inline-flex';
        }
    }

    /**
     * Update progress
     */
    function updateProgress(percent, text) {
        if (progressFill) {
            progressFill.style.width = percent + '%';
        }
        if (progressText) {
            progressText.textContent = text || '';
        }
    }
    
    /**
     * Setup apply button click handler
     */
    function setupApplyButton() {
        if (submitButton) {
            submitButton.addEventListener('click', function(e) {
                e.preventDefault();
                if (form) {
                    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
                }
            });
        }
    }

    /**
     * Show loading state
     */
    function showLoadingState() {
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = kessoInit.strings?.applying || 'Applying changes...';
        }
        if (form) {
            form.style.opacity = '0.7';
            form.style.pointerEvents = 'none';
        }
        // Show results div with loading message
        if (resultsDiv) {
            resultsDiv.className = 'kesso-results kesso-results-loading';
            resultsDiv.style.display = 'block';
            resultsDiv.innerHTML = '<div class="kesso-loading-spinner"></div><p>Processing your request. This may take a few minutes...</p>';
            resultsDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    /**
     * Hide loading state
     */
    function hideLoadingState() {
        if (form) {
            form.style.opacity = '1';
            form.style.pointerEvents = 'auto';
        }
    }

    /**
     * Display results with comprehensive report
     */
    function displayResults(result) {
        if (!resultsDiv) return;

        resultsDiv.className = 'kesso-results ' + (result.success ? 'success' : 'error');
        resultsDiv.style.display = 'block';

        let html = '<div class="kesso-results-header">';
        html += '<h3>' + (result.success ? '✓ ' : '✗ ') + (result.message || '') + '</h3>';
        html += '</div>';

        if (result.data) {
            html += '<div class="kesso-results-content">';
            
            // Summary section
            let successCount = 0;
            let failCount = 0;
            
            // Count successes and failures
            if (result.data.plugins) {
                if (result.data.plugins.installed) successCount += result.data.plugins.installed.length;
                if (result.data.plugins.activated) successCount += result.data.plugins.activated.length;
                if (result.data.plugins.skipped) successCount += result.data.plugins.skipped.length;
                if (result.data.plugins.failed) failCount += result.data.plugins.failed.length;
            }
            if (result.data.theme) successCount++;
            if (result.data.child_theme) successCount++;
            if (result.data.settings && result.data.settings.updated) successCount++;
            if (result.data.errors) failCount += result.data.errors.length;

            html += '<div class="kesso-results-summary">';
            html += '<p><strong>Summary:</strong> ' + successCount + ' operation(s) succeeded, ' + failCount + ' operation(s) failed or had errors.</p>';
            html += '</div>';

            // Detailed report
            html += '<div class="kesso-results-details">';
            html += '<h4>Detailed Report</h4>';
            html += '<ul class="kesso-results-list">';

            // Plugins section
            if (result.data.plugins) {
                const plugins = result.data.plugins;
                if (plugins.installed && plugins.installed.length > 0) {
                    html += '<li class="kesso-result-success">';
                    html += '<strong>✓ Plugins Installed (' + plugins.installed.length + '):</strong> ';
                    html += '<span>' + plugins.installed.join(', ') + '</span>';
                    html += '</li>';
                }
                if (plugins.activated && plugins.activated.length > 0) {
                    html += '<li class="kesso-result-success">';
                    html += '<strong>✓ Plugins Activated (' + plugins.activated.length + '):</strong> ';
                    html += '<span>' + plugins.activated.join(', ') + '</span>';
                    html += '</li>';
                }
                if (plugins.skipped && plugins.skipped.length > 0) {
                    html += '<li class="kesso-result-skipped">';
                    html += '<strong>⊘ Plugins Skipped (already installed) (' + plugins.skipped.length + '):</strong> ';
                    html += '<span>' + plugins.skipped.join(', ') + '</span>';
                    html += '</li>';
                }
                if (plugins.failed && plugins.failed.length > 0) {
                    html += '<li class="kesso-result-error">';
                    html += '<strong>✗ Plugins Failed (' + plugins.failed.length + '):</strong>';
                    html += '<ul class="kesso-result-sublist">';
                    plugins.failed.forEach(fail => {
                        html += '<li><strong>' + fail.slug + ':</strong> ' + fail.error + '</li>';
                    });
                    html += '</ul>';
                    html += '</li>';
                }
            }

            // Theme section
            if (result.data.theme) {
                html += '<li class="kesso-result-success">';
                html += '<strong>✓ Theme:</strong> ';
                html += '<span>' + (result.data.theme.theme || result.data.theme.active_theme || 'Unknown') + '</span>';
                if (result.data.theme.active_theme) {
                    html += ' <em>(Active)</em>';
                }
                html += '</li>';
            }

            // Child theme section
            if (result.data.child_theme) {
                html += '<li class="kesso-result-success">';
                html += '<strong>✓ Child Theme:</strong> ';
                html += '<span>' + (result.data.child_theme.child_theme || result.data.child_theme.active_theme || 'Unknown') + '</span>';
                if (result.data.child_theme.parent_theme) {
                    html += ' <em>(Parent: ' + result.data.child_theme.parent_theme + ')</em>';
                }
                if (result.data.child_theme.status === 'already_exists') {
                    html += ' <em>(Already existed, activated)</em>';
                }
                html += '</li>';
            }

            // Settings section
            if (result.data.settings) {
                if (result.data.settings.updated) {
                    html += '<li class="kesso-result-success">';
                    html += '<strong>✓ Settings Updated:</strong> ';
                    if (result.data.settings.keys && result.data.settings.keys.length > 0) {
                        html += '<span>' + result.data.settings.keys.length + ' setting(s) updated</span>';
                    } else {
                        html += '<span>All settings saved successfully</span>';
                    }
                    html += '</li>';
                }
            }

            // Errors section
            if (result.data.errors && result.data.errors.length > 0) {
                html += '<li class="kesso-result-error">';
                html += '<strong>✗ Errors (' + result.data.errors.length + '):</strong>';
                html += '<ul class="kesso-result-sublist">';
                result.data.errors.forEach(error => {
                    html += '<li><strong>' + error.step + ':</strong> ' + error.message + '</li>';
                });
                html += '</ul>';
                html += '</li>';
            }
            
            // Note section (for response errors or important info)
            if (result.data.note) {
                html += '<li class="kesso-result-note">';
                html += '<strong>ℹ Important Note:</strong> ';
                html += '<span>' + result.data.note + '</span>';
                html += '</li>';
            }

            html += '</ul>';
            html += '</div>';
            html += '</div>';
        }

        resultsDiv.innerHTML = html;

        // Scroll to results
        resultsDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

