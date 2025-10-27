/* eslint-env browser, jquery */
/* global jQuery, GFFORMID */
/**
 * Disable Already-Selected Choices in a Nested Forms Child Dropdown (Batch-Scope)
 *
 * GOAL:
 * - Disables options in a specific child dropdown if those values are already used by other child entries in
 *   the current Nested Forms batch/session. Re-enables options when entries are removed or edited.
 *
 * REQUIREMENTS:
 * - Gravity Forms and Gravity Perks Nested Forms.
 *
 * CONFIGURATION:
 * - Edit the cfg block below:
 *   - parentFormId: ID of the parent form that contains the Nested Form field
 *   - nestedFieldId: Field ID of the Nested Form field on the parent form
 *   - childDropdownFieldId: Field ID of the dropdown on the child form
 *   - compareBy: 'value' (recommended) or 'label' to compare against saved values or visible labels
 *
 * NOTES:
 * - Adds class 'bld-disabled-choice' to disabled <option> and sets a helpful title.
 * @param $
 */
(function ($) {
    'use strict';

    // === Configuration (edit these) ============================================
    const cfg = {
        parentFormId: 23,
        nestedFieldId: 1,
        childDropdownFieldId: 1,
        compareBy: 'value', // 'value' | 'label'
    };
    // ==========================================================================

    function ns() {
        const key = `GPNestedForms_${cfg.parentFormId}_${cfg.nestedFieldId}`;
        return window[key];
    }

    function buildUsedSet(namespace) {
        const used = new Set();
        if (!namespace || !Array.isArray(namespace.entries)) {
            return used;
        }
        namespace.entries.forEach(function (entry) {
            const field = entry[cfg.childDropdownFieldId] || {};
            const str =
                cfg.compareBy === 'value'
                    ? field.value || field.label
                    : field.label || field.value;
            if (str) {
                used.add(String(str).trim());
            }
        });
        return used;
    }

    function updateDisabledOptions() {
        // Ensure child select exists on current page.
        const select = document.querySelector(
            `#input_${window.GFFORMID}_${cfg.childDropdownFieldId}`
        );
        if (!select) {
            return;
        }
        const namespace = ns();
        const used = buildUsedSet(namespace);
        Array.prototype.forEach.call(select.options, function (opt) {
            const toCheck =
                cfg.compareBy === 'value'
                    ? opt.value
                    : (opt.textContent || '').trim();
            const disabled = used.has(String(toCheck));
            opt.disabled = disabled;
            opt.classList.toggle('bld-disabled-choice', disabled);
            opt.title = disabled ? 'Already selected in this batch' : '';
        });
    }

    $(document).on(
        'gpnf_after_entry_added gpnf_after_entry_removed gpnf_after_edit_entry gform_post_render',
        updateDisabledOptions
    );
    $(updateDisabledOptions);
})(jQuery);
