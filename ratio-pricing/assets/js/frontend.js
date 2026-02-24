/**
 * Ratio Pricing Frontend JavaScript
 *
 * Bricks form: select name="size", radios name="texture[]" (values: without / with).
 * Syncs to Woo hidden inputs ratio_pricing_size, ratio_pricing_texture.
 * Price display is UX only; server is source of truth.
 * Boot runs on DOM ready + Bricks render hooks only (no MutationObserver, no setTimeout fallbacks).
 */

(function($) {
    'use strict';

    var BRICKS_SELECT_SIZE = 'select[name="size"]';
    var BRICKS_TEXTURE_RADIOS = 'input[name="texture[]"]';
    var WOO_HIDDEN_SIZE = 'ratio_pricing_size';
    var WOO_HIDDEN_TEXTURE = 'ratio_pricing_texture';
    var EVENT_NS = 'ratioPricing';

    $(document).ready(function() {
        if (typeof ratioPricingConfig === 'undefined') {
            return;
        }
        if (typeof ratioPricingConfig.sizes === 'undefined') {
            return;
        }

        var $sizeSelect = $(BRICKS_SELECT_SIZE).first();
        var $textureRadios = $(BRICKS_TEXTURE_RADIOS);
        if ($sizeSelect.length === 0) {
            console.warn('Ratio Pricing: Bricks size field not found at ready (expected ' + BRICKS_SELECT_SIZE + '). Will retry after delay for late-rendered templates (e.g. Polylang secondary language).');
        }
        if ($textureRadios.length === 0) {
            console.warn('Ratio Pricing: Bricks texture field not found (expected ' + BRICKS_TEXTURE_RADIOS + ').');
        }

        var $priceEl = $('.ratio-pricing-price .price').first();
        if ($priceEl.length === 0) {
            $priceEl = $('.single-product .summary .price').first();
        }
        if ($priceEl.length === 0) {
            $priceEl = $('.summary .price').first();
        }
        if ($priceEl.length === 0) {
            return;
        }
        var originalPriceHtml = $priceEl.html();

        var $cartForm = $('form.cart').first();
        if ($cartForm.length === 0) {
            return;
        }

        function ensureHiddenInput(name) {
            var $input = $cartForm.find('input[name="' + name + '"]');
            if ($input.length === 0) {
                $input = $('<input type="hidden" name="' + name + '" value="">');
                $cartForm.append($input);
            }
            return $input;
        }
        var $hiddenSize = ensureHiddenInput(WOO_HIDDEN_SIZE);
        var $hiddenTexture = ensureHiddenInput(WOO_HIDDEN_TEXTURE);

        var sizes = ratioPricingConfig.sizes;
        var cfg = ratioPricingConfig;
        var decimals = typeof cfg.priceDecimals === 'number' ? cfg.priceDecimals : 2;
        var decimalSep = (typeof cfg.decimalSeparator === 'string' && cfg.decimalSeparator !== '') ? cfg.decimalSeparator : '.';
        var thousandSep = typeof cfg.thousandSeparator === 'string' ? cfg.thousandSeparator : ',';
        var currencySym = (typeof cfg.currencySymbol === 'string' && cfg.currencySymbol !== '') ? cfg.currencySymbol : '₪';
        var currencyPos = (typeof cfg.currencyPosition === 'string' && cfg.currencyPosition !== '') ? cfg.currencyPosition : 'left';
        var optionFormat = (typeof cfg.format === 'string' && cfg.format !== '') ? cfg.format : '{{size}} - {{price}} {{currency}}';
        var placeholderText = (typeof cfg.placeholder === 'string' && cfg.placeholder !== '') ? cfg.placeholder : 'Choose a size...';

        function formatPriceNumber(price) {
            var n = parseFloat(price);
            if (isNaN(n)) {
                return '0';
            }
            var parts = n.toFixed(decimals).split('.');
            var intPart = parts[0];
            if (thousandSep) {
                intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, thousandSep);
            }
            return parts.length > 1 ? intPart + decimalSep + parts[1] : intPart;
        }

        function formatPrice(price) {
            var numStr = formatPriceNumber(price);
            switch (currencyPos) {
                case 'right':
                    return numStr + currencySym;
                case 'left_space':
                    return currencySym + ' ' + numStr;
                case 'right_space':
                    return numStr + ' ' + currencySym;
                default:
                    return currencySym + numStr;
            }
        }

        function getCurrencySymbol() {
            if (currencySym) {
                return currencySym;
            }
            if (typeof wc_add_to_cart_params !== 'undefined' && wc_add_to_cart_params.currency_format_symbol) {
                return wc_add_to_cart_params.currency_format_symbol;
            }
            if (typeof woocommerce_params !== 'undefined' && woocommerce_params.currency_format_symbol) {
                return woocommerce_params.currency_format_symbol;
            }
            var match = $priceEl.text().match(/[^\d\s.,–-]+/);
            return match ? match[0] : '₪';
        }

        function formatOptionLabel(sizeKey, price) {
            var priceStr = formatPriceNumber(price);
            var sym = getCurrencySymbol();
            return optionFormat
                .replace(/\{\{size\}\}/g, sizeKey)
                .replace(/\{\{price\}\}/g, priceStr)
                .replace(/\{\{currency\}\}/g, sym);
        }

        function renderSizeOptions() {
            var $select = $(BRICKS_SELECT_SIZE).first();
            if ($select.length === 0) {
                return;
            }
            $select.find('option').remove();
            $select.append($('<option value="">').text(placeholderText));
            $.each(sizes, function(sizeKey, price) {
                var label = formatOptionLabel(sizeKey, price);
                $select.append($('<option>').attr('value', sizeKey).attr('data-price', price).text(label));
            });
        }

        function getTextureValue() {
            var checked = $(BRICKS_TEXTURE_RADIOS).filter(':checked');
            if (checked.length === 0) {
                return '';
            }
            var val = (checked.val() || '').toLowerCase();
            return (val === 'with') ? 'with' : '';
        }

        function syncHiddenInputs() {
            var $select = $(BRICKS_SELECT_SIZE).first();
            var sizeVal = $select.length ? ($select.val() || '') : '';
            var textureVal = getTextureValue();
            $hiddenSize.val(sizeVal);
            $hiddenTexture.val(textureVal);
        }

        function updateDisplayedPrice() {
            var $select = $(BRICKS_SELECT_SIZE).first();
            var sizeVal = $select.length ? $select.val() : '';
            if (!sizeVal) {
                $priceEl.html(originalPriceHtml);
                return;
            }
            var price = sizes[sizeVal];
            if (price === undefined || isNaN(parseFloat(price))) {
                $priceEl.html(originalPriceHtml);
                return;
            }
            var basePrice = parseFloat(price);
            var texturePct = ratioPricingConfig.texturePercentage || 30;
            var hasTexture = getTextureValue() === 'with';
            var finalPrice = hasTexture ? basePrice * (1 + texturePct / 100) : basePrice;
            $priceEl.html('<span class="price">' + formatPrice(finalPrice) + '</span>');
        }

        function onSizeOrTextureChange() {
            syncHiddenInputs();
            updateDisplayedPrice();
        }

        function bindEvents() {
            $(document).off('change.' + EVENT_NS, BRICKS_SELECT_SIZE).on('change.' + EVENT_NS, BRICKS_SELECT_SIZE, onSizeOrTextureChange);
            $(document).off('change.' + EVENT_NS, BRICKS_TEXTURE_RADIOS).on('change.' + EVENT_NS, BRICKS_TEXTURE_RADIOS, onSizeOrTextureChange);
            $cartForm.off('submit.' + EVENT_NS).on('submit.' + EVENT_NS, function(e) {
                var sizeVal = $hiddenSize.val();
                if (!sizeVal || sizeVal.trim() === '') {
                    e.preventDefault();
                    alert('Please select a size before adding to cart.');
                    $(BRICKS_SELECT_SIZE).first().focus();
                    return false;
                }
            });
        }

        function bootRatioPricing() {
            renderSizeOptions();
            bindEvents();
            syncHiddenInputs();
            updateDisplayedPrice();
        }

        bootRatioPricing();

        $(window).on('bricks/frontend/render', bootRatioPricing);
        document.addEventListener('bricks-frontend-rendered', bootRatioPricing);

        // Secondary language / Polylang: Bricks may render the form after ready. Run boot once more if the select was missing so we can populate it when it appears.
        if ($sizeSelect.length === 0) {
            setTimeout(function() {
                bootRatioPricing();
            }, 450);
        }
    });

})(jQuery);
