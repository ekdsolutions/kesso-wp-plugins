/**
 * Kesso Cookies Admin JavaScript
 */
(function() {
    'use strict';

    // Color Picker Component
    class KessoCookiesColorPicker {
        constructor(wrapper) {
            this.wrapper = wrapper;
            this.input = wrapper.querySelector('.kesso-cookies-color-picker-input');
            this.preview = wrapper.querySelector('.kesso-cookies-color-picker-preview');
            this.toggle = wrapper.querySelector('.kesso-cookies-color-picker-toggle');
            this.dropdown = wrapper.querySelector('.kesso-cookies-color-picker-dropdown');
            this.spectrumCanvas = wrapper.querySelector('.kesso-cookies-color-picker-canvas');
            this.spectrumPointer = wrapper.querySelector('.kesso-cookies-color-picker-pointer');
            this.hueSlider = wrapper.querySelector('.kesso-cookies-color-picker-hue');
            this.hueCanvas = this.hueSlider.querySelector('.kesso-cookies-color-picker-slider-canvas');
            this.hueThumb = this.hueSlider.querySelector('.kesso-cookies-color-picker-slider-thumb');
            this.alphaSlider = wrapper.querySelector('.kesso-cookies-color-picker-alpha');
            this.alphaCanvas = this.alphaSlider.querySelector('.kesso-cookies-color-picker-slider-canvas');
            this.alphaThumb = this.alphaSlider.querySelector('.kesso-cookies-color-picker-slider-thumb');
            this.hexInput = wrapper.querySelector('.kesso-cookies-color-picker-hex');
            this.rgbaInput = wrapper.querySelector('.kesso-cookies-color-picker-rgba');

            this.hue = 210; // 0-360
            this.saturation = 87; // 0-100
            this.lightness = 50; // 0-100
            this.alpha = 1; // 0-1

            this.isDragging = false;
            this.dragTarget = null;

            this.init();
        }

        init() {
            // Parse initial color
            const initialColor = this.input.value || '#137fec';
            this.parseColor(initialColor);
            this.updateUI();

            // Event listeners
            this.toggle.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleDropdown();
            });

            // Close on outside click
            document.addEventListener('click', (e) => {
                if (!this.wrapper.contains(e.target) && this.dropdown.style.display !== 'none') {
                    this.closeDropdown();
                }
            });

            // Spectrum canvas
            this.spectrumCanvas.addEventListener('mousedown', (e) => this.startDrag(e, 'spectrum'));
            this.spectrumCanvas.addEventListener('mousemove', (e) => this.handleDrag(e, 'spectrum'));
            this.spectrumCanvas.addEventListener('mouseup', () => this.stopDrag());
            this.spectrumCanvas.addEventListener('click', (e) => this.handleSpectrumClick(e));

            // Hue slider
            this.hueCanvas.addEventListener('mousedown', (e) => this.startDrag(e, 'hue'));
            this.hueCanvas.addEventListener('mousemove', (e) => this.handleDrag(e, 'hue'));
            this.hueCanvas.addEventListener('mouseup', () => this.stopDrag());
            this.hueCanvas.addEventListener('click', (e) => this.handleHueClick(e));

            // Alpha slider
            this.alphaCanvas.addEventListener('mousedown', (e) => this.startDrag(e, 'alpha'));
            this.alphaCanvas.addEventListener('mousemove', (e) => this.handleDrag(e, 'alpha'));
            this.alphaCanvas.addEventListener('mouseup', () => this.stopDrag());
            this.alphaCanvas.addEventListener('click', (e) => this.handleAlphaClick(e));

            // Manual inputs
            this.hexInput.addEventListener('input', () => this.handleHexInput());
            this.rgbaInput.addEventListener('input', () => this.handleRgbaInput());

            // Draw initial canvases
            this.drawSpectrum();
            this.drawHueSlider();
            this.drawAlphaSlider();
        }

        toggleDropdown() {
            if (this.dropdown.style.display === 'none') {
                this.openDropdown();
            } else {
                this.closeDropdown();
            }
        }

        openDropdown() {
            this.dropdown.style.display = 'block';
            this.drawSpectrum();
            this.drawHueSlider();
            this.drawAlphaSlider();
        }

        closeDropdown() {
            this.dropdown.style.display = 'none';
        }

        parseColor(color) {
            if (!color) return;

            // Try to parse different formats
            if (color.startsWith('#')) {
                this.parseHex(color);
            } else if (color.startsWith('rgb')) {
                this.parseRgb(color);
            } else if (color.startsWith('hsl')) {
                this.parseHsl(color);
            }
        }

        parseHex(hex) {
            hex = hex.replace('#', '');
            if (hex.length === 3) {
                hex = hex.split('').map(c => c + c).join('');
            }
            const r = parseInt(hex.substr(0, 2), 16);
            const g = parseInt(hex.substr(2, 2), 16);
            const b = parseInt(hex.substr(4, 2), 16);
            this.rgbToHsl(r, g, b);
        }

        parseRgb(rgb) {
            const match = rgb.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/);
            if (match) {
                const r = parseInt(match[1]);
                const g = parseInt(match[2]);
                const b = parseInt(match[3]);
                // Round alpha to 2 decimal places
                this.alpha = match[4] ? Math.round(parseFloat(match[4]) * 100) / 100 : 1;
                this.rgbToHsl(r, g, b);
            }
        }

        parseHsl(hsl) {
            const match = hsl.match(/hsla?\((\d+),\s*(\d+)%,\s*(\d+)%(?:,\s*([\d.]+))?\)/);
            if (match) {
                this.hue = parseInt(match[1]);
                this.saturation = parseInt(match[2]);
                this.lightness = parseInt(match[3]);
                // Round alpha to 2 decimal places
                this.alpha = match[4] ? Math.round(parseFloat(match[4]) * 100) / 100 : 1;
            }
        }

        rgbToHsl(r, g, b) {
            r /= 255;
            g /= 255;
            b /= 255;

            const max = Math.max(r, g, b);
            const min = Math.min(r, g, b);
            let h, s, l = (max + min) / 2;

            if (max === min) {
                h = s = 0;
            } else {
                const d = max - min;
                s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
                switch (max) {
                    case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6; break;
                    case g: h = ((b - r) / d + 2) / 6; break;
                    case b: h = ((r - g) / d + 4) / 6; break;
                }
            }

            this.hue = Math.round(h * 360);
            this.saturation = Math.round(s * 100);
            this.lightness = Math.round(l * 100);
        }

        hslToRgb(h, s, l) {
            h = h / 360;
            s = s / 100;
            l = l / 100;

            let r, g, b;

            if (s === 0) {
                r = g = b = l;
            } else {
                const hue2rgb = (p, q, t) => {
                    if (t < 0) t += 1;
                    if (t > 1) t -= 1;
                    if (t < 1/6) return p + (q - p) * 6 * t;
                    if (t < 1/2) return q;
                    if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
                    return p;
                };

                const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
                const p = 2 * l - q;
                r = hue2rgb(p, q, h + 1/3);
                g = hue2rgb(p, q, h);
                b = hue2rgb(p, q, h - 1/3);
            }

            return {
                r: Math.round(r * 255),
                g: Math.round(g * 255),
                b: Math.round(b * 255)
            };
        }

        getColor() {
            const rgb = this.hslToRgb(this.hue, this.saturation, this.lightness);
            return {
                r: rgb.r,
                g: rgb.g,
                b: rgb.b,
                h: this.hue,
                s: this.saturation,
                l: this.lightness,
                a: this.alpha
            };
        }

        getHex() {
            const rgb = this.hslToRgb(this.hue, this.saturation, this.lightness);
            const toHex = (n) => {
                const hex = n.toString(16);
                return hex.length === 1 ? '0' + hex : hex;
            };
            return '#' + toHex(rgb.r) + toHex(rgb.g) + toHex(rgb.b);
        }

        getRgba() {
            const rgb = this.hslToRgb(this.hue, this.saturation, this.lightness);
            // Round alpha to 2 decimal places
            const alpha = Math.round(this.alpha * 100) / 100;
            return `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${alpha})`;
        }

        updateUI() {
            const color = this.getColor();
            const hex = this.getHex();
            const rgba = this.getRgba();

            // Update preview
            this.preview.style.backgroundColor = rgba;

            // Update hidden input - save as RGBA if opacity < 1, otherwise save as hex
            if (this.alpha < 1) {
                this.input.value = rgba;
            } else {
                this.input.value = hex;
            }
            this.input.dispatchEvent(new Event('change', { bubbles: true }));

            // Update manual inputs
            this.hexInput.value = hex;
            // Format RGBA with alpha rounded to 2 decimal places
            const rgb = this.hslToRgb(this.hue, this.saturation, this.lightness);
            const formattedAlpha = this.formatAlpha(this.alpha);
            this.rgbaInput.value = `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${formattedAlpha})`;

            // Update pointer position
            const x = (this.saturation / 100) * 200;
            const y = 200 - (this.lightness / 100) * 200;
            this.spectrumPointer.style.left = x + 'px';
            this.spectrumPointer.style.top = y + 'px';

            // Update hue thumb
            const hueX = (this.hue / 360) * 200;
            this.hueThumb.style.left = hueX + 'px';

            // Update alpha thumb
            const alphaX = this.alpha * 200;
            this.alphaThumb.style.left = alphaX + 'px';

            // Redraw canvases
            this.drawSpectrum();
            this.drawAlphaSlider();
        }

        drawSpectrum() {
            const ctx = this.spectrumCanvas.getContext('2d');
            const width = 200;
            const height = 200;

            // Draw saturation/lightness spectrum
            for (let y = 0; y < height; y++) {
                for (let x = 0; x < width; x++) {
                    const s = x / width;
                    const l = 1 - (y / height);
                    const rgb = this.hslToRgb(this.hue, s * 100, l * 100);
                    ctx.fillStyle = `rgb(${rgb.r}, ${rgb.g}, ${rgb.b})`;
                    ctx.fillRect(x, y, 1, 1);
                }
            }
        }

        drawHueSlider() {
            const ctx = this.hueCanvas.getContext('2d');
            const width = 200;
            const height = 20;

            for (let x = 0; x < width; x++) {
                const hue = (x / width) * 360;
                const rgb = this.hslToRgb(hue, 100, 50);
                ctx.fillStyle = `rgb(${rgb.r}, ${rgb.g}, ${rgb.b})`;
                ctx.fillRect(x, 0, 1, height);
            }
        }

        drawAlphaSlider() {
            const ctx = this.alphaCanvas.getContext('2d');
            const width = 200;
            const height = 20;
            const rgb = this.hslToRgb(this.hue, this.saturation, this.lightness);

            // Draw checkerboard pattern
            const size = 10;
            for (let y = 0; y < height; y += size) {
                for (let x = 0; x < width; x += size) {
                    const isEven = ((x / size) + (y / size)) % 2 === 0;
                    ctx.fillStyle = isEven ? '#ffffff' : '#cccccc';
                    ctx.fillRect(x, y, size, size);
                }
            }

            // Draw gradient
            const gradient = ctx.createLinearGradient(0, 0, width, 0);
            gradient.addColorStop(0, `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, 0)`);
            gradient.addColorStop(1, `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, 1)`);
            ctx.fillStyle = gradient;
            ctx.fillRect(0, 0, width, height);
        }

        startDrag(e, target) {
            this.isDragging = true;
            this.dragTarget = target;
            this.handleDrag(e, target);
        }

        handleDrag(e, target) {
            if (!this.isDragging && target !== this.dragTarget) return;
            if (!this.isDragging) return;

            const rect = e.currentTarget.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            if (target === 'spectrum') {
                this.saturation = Math.max(0, Math.min(100, (x / 200) * 100));
                this.lightness = Math.max(0, Math.min(100, 100 - (y / 200) * 100));
            } else if (target === 'hue') {
                this.hue = Math.max(0, Math.min(360, (x / 200) * 360));
            } else if (target === 'alpha') {
                // Round alpha to 2 decimal places
                this.alpha = Math.round(Math.max(0, Math.min(1, x / 200)) * 100) / 100;
            }

            this.updateUI();
        }

        stopDrag() {
            this.isDragging = false;
            this.dragTarget = null;
        }

        handleSpectrumClick(e) {
            if (this.isDragging) return;
            const rect = this.spectrumCanvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            this.saturation = Math.max(0, Math.min(100, (x / 200) * 100));
            this.lightness = Math.max(0, Math.min(100, 100 - (y / 200) * 100));
            this.updateUI();
        }

        handleHueClick(e) {
            if (this.isDragging) return;
            const rect = this.hueCanvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            this.hue = Math.max(0, Math.min(360, (x / 200) * 360));
            this.updateUI();
        }

        handleAlphaClick(e) {
            if (this.isDragging) return;
            const rect = this.alphaCanvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            // Round alpha to 2 decimal places
            this.alpha = Math.round(Math.max(0, Math.min(1, x / 200)) * 100) / 100;
            this.updateUI();
        }

        handleHexInput() {
            const hex = this.hexInput.value;
            if (/^#?[0-9a-fA-F]{6}$/.test(hex) || /^#?[0-9a-fA-F]{3}$/.test(hex)) {
                this.parseHex(hex.startsWith('#') ? hex : '#' + hex);
                this.updateUI();
            }
        }

        handleRgbaInput() {
            const rgba = this.rgbaInput.value;
            if (rgba.match(/^rgba?\(/)) {
                this.parseRgb(rgba);
                this.updateUI();
            }
        }

        // Format alpha value to 2 decimal places when displayed
        formatAlpha(alpha) {
            return Math.round(alpha * 100) / 100;
        }
    }

    // Store color picker instances
    const colorPickerInstances = new Map();

    // Initialize color pickers when DOM is ready
    function initColorPickers() {
        const wrappers = document.querySelectorAll('.kesso-cookies-color-picker-wrapper');
        wrappers.forEach(wrapper => {
            const fieldId = wrapper.getAttribute('data-field-id');
            const picker = new KessoCookiesColorPicker(wrapper);
            colorPickerInstances.set(fieldId, picker);
        });
    }

    // Update color picker when hidden input changes (for reset functionality)
    function updateColorPicker(fieldId, colorValue) {
        const picker = colorPickerInstances.get(fieldId);
        if (picker) {
            picker.parseColor(colorValue);
            picker.updateUI();
        }
    }

    // Expose update function globally
    window.kessoCookiesUpdateColorPicker = updateColorPicker;

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initColorPickers);
    } else {
        initColorPickers();
    }

})();
