/**
 * Confirm Specific Radio Selections Before Submitting (per‑form, configurable)
 *
 * GOAL:
 * - Shows a confirmation dialog when a selected radio value matches configured choices. Prevents
 *   accidental submissions for critical options.
 *
 * CONFIGURATION:
 * - Edit cfg below:
 *   - fieldId: numeric field ID of the radio field (e.g., 3)
 *   - messages: map of radio option value => confirmation message
 *   - applyToFormIds: optional array of form IDs; empty means apply to any form on the page
 */

(function ($) {
    'use strict';

    // === Configuration (edit these) ============================================
    const cfg = {
        fieldId: 3,
        messages: {
            'First Choice': 'Are you sure you want to submit First Choice?',
            'Second Choice': 'Are you sure you want to submit Second Choice?',
        },
        applyToFormIds: [], // e.g., [1, 7]
    };
    // ==========================================================================

    function shouldApply(formId) {
        if (
            !Array.isArray(cfg.applyToFormIds) ||
            0 === cfg.applyToFormIds.length
        ) {
            return true;
        }
        return cfg.applyToFormIds.indexOf(Number(formId)) !== -1;
    }

    function bindForForm(formId) {
        if (!formId || !shouldApply(formId)) {
            return;
        }
        const form = document.getElementById('gform_' + String(formId));
        if (!form || form.dataset.bldConfirmBound === '1') {
            return;
        }
        form.dataset.bldConfirmBound = '1';

        form.addEventListener(
            'submit',
            function (e) {
                const selector =
                    'input[name="input_' + String(cfg.fieldId) + '"]:checked';
                const input = form.querySelector(selector);
                const val = input ? input.value : '';
                const msg = Object.prototype.hasOwnProperty.call(
                    cfg.messages,
                    val
                )
                    ? cfg.messages[val]
                    : '';
                if (msg) {
                    const ok = window.confirm(msg);
                    if (!ok) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                }
            },
            true
        );
    }

    $(document).on(
        'gform_post_render gform_page_loaded',
        function (ev, formId) {
            const id = typeof formId === 'undefined' ? window.GFFORMID : formId;
            if (typeof id !== 'undefined' && id !== null) {
                bindForForm(parseInt(id, 10));
            }
        }
    );

    $(function () {
        if (
            typeof window.GFFORMID !== 'undefined' &&
            window.GFFORMID !== null
        ) {
            bindForForm(parseInt(window.GFFORMID, 10));
        }
    });
})(jQuery);
