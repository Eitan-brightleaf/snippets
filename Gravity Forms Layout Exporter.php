<?php
/**
 * Gravity Forms Layout Exporter
 *
 * GOAL
 * Adds "Export Layout" button to Gravity Forms editor that exports form structure
 * as formatted HTML table to clipboard. Includes field labels, types, conditional logic,
 * and Gravity Populate Anything (GPPA) settings for documentation purposes.
 *
 * CONFIGURATION
 * - Plugin: Gravity Forms must be installed and activated
 * - Plugin: Gravity Populate Anything (GPPA) plugin (optional but recommended)
 * - Permissions: User must have 'gravityforms_edit_forms' capability
 *
 * USAGE
 * 1. Navigate to Forms > Edit Form in WordPress admin
 * 2. Click "📋 Export Layout" button in toolbar
 * 3. Script copies formatted table to clipboard
 * 4. Opens new Google Docs tab automatically
 * 5. Paste (Ctrl+V) into Google Doc
 *
 * NOTES
 * - Detects form field widths (quarter, half, full, etc.)
 * - Resolves GPPA field mappings to human-readable labels
 * - Shows conditional logic rules with field references
 * - Exports hidden/admin-only field indicators
 * - Table format optimized for Google Docs
 */

add_action(
	'wp_ajax_get_gppa_form_fields',
	function () {
		if ( ! current_user_can( 'gravityforms_edit_forms' ) ) {
			wp_send_json_error( 'Permission denied', 403 );
		}

		$nonce = isset( $_POST['_ajax_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_ajax_nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'get_gppa_form_fields' ) ) {
			wp_send_json_error( 'Invalid nonce', 400 );
		}

		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		if ( ! $form_id ) {
			wp_send_json_error( 'Missing form ID', 400 );
		}

		if ( ! class_exists( 'GFAPI' ) ) {
			wp_send_json_error( 'Gravity Forms not available', 500 );
		}

		try {
			$form = GFAPI::get_form( $form_id );
		} catch ( Exception $e ) {
			wp_send_json_error( 'Error loading form', 500 );
		}
		if ( ! $form || empty( $form['fields'] ) ) {
			wp_send_json_error( 'Form not found', 404 );
		}

		$field_map = [];
		foreach ( $form['fields'] as $field ) {
			if ( isset( $field->id ) && isset( $field->label ) ) {
				$field_map[ $field->id ] = $field->label;
			}
		}

		wp_send_json_success(
			[
				'title'  => rgar( $form, 'title' ),
				'fields' => $field_map,
			]
		);
	}
);

add_action(
	'gform_editor_js',
	function () {
		$nonce = wp_create_nonce( 'get_gppa_form_fields' );
		?>
		<script>
            jQuery(document).ready(function($) {

                const showToast = (msg, type = 'success') => {
                    const toast = $(`<div style="position:fixed;top:20px;right:20px;padding:15px 20px;background:${type === 'success' ? '#28a745' : '#dc3545'};color:white;border-radius:4px;z-index:99999;box-shadow:0 2px 8px rgba(0,0,0,0.2);">${msg}</div>`);
                    $('body').append(toast);
                    setTimeout(() => toast.fadeOut(300, () => toast.remove()), 3000);
                };

                const btn = $('<button class="gform-button gform-button--white gform-button--icon-leading" id="export-form-visual">📋 Export Layout</button>');
                $('#gf_toolbar_buttons_container').append(btn);

                $('#export-form-visual').on('click', async function(e) {
                    e.preventDefault();

                    const $btn = $(this);
                    const originalText = $btn.text();
                    $btn.prop('disabled', true).text('⏳ Exporting...');

                    const $fields = $('#gform_fields .gfield');
                    const rows = [], widthMap = {
                        quarter: 1, third: 1.33, half: 2,
                        'two-thirds': 2.66, 'three-quarters': 3, full: 4
                    };
                    let currentRow = [], currentWidth = 0;

                    $fields.each(function(i, el) {
                        const $f = $(el);
                        const widthClass = $f.attr('class').match(/gfield--width-(\w+)/);
                        const colspan = widthClass ? widthMap[widthClass[1]] || 1 : 1;

                        if (currentWidth + colspan > 4) {
                            rows.push(currentRow);
                            currentRow = [];
                            currentWidth = 0;
                        }

                        currentRow.push({ $f, colspan });
                        currentWidth += colspan;
                    });
                    if (currentRow.length > 0) rows.push(currentRow);

                    const htmlRows = await Promise.all(rows.map(async row => {
                        const cells = await Promise.all(row.map(async ({ $f, colspan }) => {
                            const idAttr = $f.attr('id');
                            const idMatch = idAttr ? idAttr.match(/_(\d+)$/) : null;
                            const id = idMatch ? parseInt(idMatch[1]) : null;
                            let label = $f.find('.gfield_label').text().trim() || '(No Label)';
                            const type = ($f.attr('class').match(/gfield--type-([^\s]+)/) || [])[1] || 'unknown';
                            const desc = $f.find('.gfield_description').text().trim();
                            const vis = $f.hasClass('gfield_visibility_hidden') ? '🔒 Hidden' :
                                $f.hasClass('gfield_visibility_administrative') ? '🛠 Admin Only' : '';

                            let gppaHTML = '', choicesText = '', logicHTML = '';

                            if (window.form && Array.isArray(window.form.fields)) {
                                const fieldObj = window.form.fields.find(f => f.id === id);
                                if (fieldObj) {
                                    const gppa = fieldObj['gppa-choices-enabled'];
                                    if (gppa) {
                                        const source = fieldObj['gppa-choices-object-type'] || '(Unknown)';
                                        const primary = fieldObj['gppa-choices-primary-property'] || '';
                                        const ordering = fieldObj['gppa-choices-ordering-property'] || '';
                                        const method = fieldObj['gppa-choices-ordering-method'] || '';
                                        const templates = fieldObj['gppa-choices-templates'] || {};
                                        const filters = fieldObj['gppa-choices-filter-groups'] || [];

                                        gppaHTML += '<div style="margin-top:6px;font-size:0.85em;color:#555;">🔄 <strong>Populate Anything:</strong><br>';
                                        gppaHTML += 'Enabled: ✅<br>';
                                        gppaHTML += `Source: ${source || '(Unknown Source)'}<br>`;
                                        gppaHTML += `Primary Property: ${primary}<br>`;

                                        if (source === 'gf_entry' && primary) {
                                            try {
                                                const result = await $.post(ajaxurl, {
                                                    action: 'get_gppa_form_fields',
                                                    _ajax_nonce: '<?php echo esc_js( $nonce ); ?>',
                                                    form_id: parseInt(primary)
                                                });
                                                if (result.success) {
                                                    const fieldLabels = result.data.fields;
                                                    const resolve = key => {
                                                        const match = key.match(/gf_field_(\d+)/);
                                                        if (!match) return key;
                                                        const fid = match[1];
                                                        return fieldLabels[fid] ? `${fieldLabels[fid]} (${key})` : key;
                                                    };
                                                    gppaHTML += `🔍 Source Form: ${result.data.title}<br>`;
                                                    if (ordering) gppaHTML += `Order By: ${resolve(ordering)} (${method})<br>`;
                                                    if (templates?.value || templates?.label) {
                                                        gppaHTML += `<u>Templates:</u><br>`;
                                                        if (templates?.value) gppaHTML += `- Value: ${resolve(templates.value)}<br>`;
                                                        if (templates?.label) gppaHTML += `- Label: ${resolve(templates.label)}<br>`;
                                                    }
                                                }
                                            } catch (err) {
                                                console.warn("❌ GPPA AJAX error: ", err);
                                            }
                                        }

                                        if (Array.isArray(filters) && filters.length) {
                                            gppaHTML += '<u>Filters:</u><br>';
                                            filters.forEach(group => group.forEach(filter => {
                                                gppaHTML += `- ${filter.key} ${filter.operator} "${filter.value}"<br>`;
                                            }));
                                        }
                                        gppaHTML += '</div>';
                                    } else if (Array.isArray(fieldObj.choices)) {
                                        const shownChoices = fieldObj.choices.map(c => c.text).filter(Boolean).slice(0, 10);
                                        if (shownChoices.length) choicesText = `<div style="margin-top:6px;font-size:0.85em;color:#555;">🔘 <strong>Choices:</strong> ${shownChoices.join(', ')}</div>`;
                                    }

                                    if (fieldObj.conditionalLogic) {
                                        const logic = fieldObj.conditionalLogic;
                                        const action = logic.actionType === 'show' ? 'Show' : 'Hide';
                                        const logicType = logic.logicType === 'all' ? 'all conditions' : 'any condition';
                                        const rules = logic.rules.map(r => {
                                            const target = window.form.fields.find(f => String(f.id) === String(r.fieldId));
                                            const targetLabel = target ? target.label : `Field ${r.fieldId}`;
                                            return `${targetLabel} ${r.operator} "${r.value}"`;
                                        }).join('<br>');
                                        logicHTML = `<div style="margin-top:6px;font-size:0.85em;color:#555;">⚙️ <strong>Conditional Logic:</strong><br>${action} if ${logicType}:<br>${rules}</div>`;
                                    }
                                }
                            }

                            let html = `<td colspan="${Math.round(colspan)}" style="border:1px solid #ccc;padding:10px;vertical-align:top;">
                    <strong>${label}</strong><br>`;
                            if (desc) html += `<span style="font-size:smaller;color:#666;">${desc}</span><br>`;
                            html += `<em>${type}</em><br><small>Field ID: ${id}</small><br>`;
                            if (vis) html += `${vis}<br>`;
                            if (gppaHTML) html += gppaHTML;
                            else if (choicesText) html += choicesText;
                            if (logicHTML) html += logicHTML;
                            html += `</td>`;
                            return html;
                        }));
                        return `<tr>${cells.join('')}</tr>`;
                    }));

                    const fullHTML = `<table style="border-collapse:collapse;width:100%;font-family:Arial,sans-serif;">${htmlRows.join('')}</table>`;
                    try {
                        if (navigator.clipboard && window.ClipboardItem) {
                            await navigator.clipboard.write([new ClipboardItem({"text/html": new Blob([fullHTML], {type:"text/html"})})]);
                        } else {
                            const ta = document.createElement('textarea');
                            ta.value = fullHTML.replace(/<[^>]+>/g, '');
                            document.body.appendChild(ta);
                            ta.select();
                            document.execCommand('copy');
                            document.body.removeChild(ta);
                        }
                        window.open("https://docs.google.com/document/create", "_blank");
                        showToast('✅ Copied! Opening Google Docs...');
                    } catch (err) {
                        showToast('❌ Copy to clipboard failed', 'error');
                    } finally {
                        $btn.prop('disabled', false).text(originalText);
                    }
                });
            });
		</script>
		<?php
	}
);
