<?php
/*
 * Plugin Name: Aspire Software: WPForms Actions for Pardot
 * Version: 1.3.6
 * Description: Posts leads to Pardot endpoints via a URL field displayed on form configuration and includes GA Connector integration for tracking
 * Author: Nick Tomkin (@ntomkin)
 * Author URI: https://www.linkedin.com/in/nicktomkin/
 * License: GPL2
 * GitHub Plugin URI: https://github.com/ntomkin/aspire-wpforms-action
 * GitHub Branch: master
*/

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

// Include the GitHub Updater
if (!class_exists('AspirePluginUpdater')) {
    require_once plugin_dir_path(__FILE__) . 'includes/updater.php';
}

// Setup the updater
add_action('init', function() {
    // Only initialize updater for admin
    if (is_admin()) {
        new AspirePluginUpdater(__FILE__, 'ntomkin', 'aspire-wpforms-action');
    }
});

// GA Connector Integration
function aspire_wpforms_ga_connector_scripts() {
    $ga_connector = get_option('ga_connector_option_name');
    if (!empty($ga_connector['key'])) {
        $tracking_type = isset($ga_connector['tracking_type']) ? $ga_connector['tracking_type'] : 'cookie';
        $script_url = $tracking_type === 'cookie' 
            ? 'https://ta.gaconnector.com/gaconnector.js' 
            : 'https://track.gaconnector.com/gaconnector.js';
            
        wp_register_script('gaconnector-tracker', $script_url, array('jquery'), null, true);
        wp_register_script('gaconnector', plugins_url('js/connector.js', __FILE__), array('gaconnector-tracker'), '1.0', true);

        wp_enqueue_script('gaconnector-tracker');
        wp_enqueue_script('gaconnector');
    }
}
add_action('wp_enqueue_scripts', 'aspire_wpforms_ga_connector_scripts');

function aspire_wpforms_ga_connector_prevent_cloudflare_caching($tag, $handle, $src) {
    if (strpos($handle, 'gaconnector') !== false) {
        return str_replace('<script', '<script data-cfasync="false"', $tag);
    }
    return $tag;
}
add_filter('script_loader_tag', 'aspire_wpforms_ga_connector_prevent_cloudflare_caching', 10, 3);

// Add GA Connector Admin Menu
function aspire_wpforms_ga_connector_add_admin_menu() {
    add_submenu_page(
        'options-general.php', 
        __('GA Connector Settings', 'aspire-wpforms-action'), 
        __('GA Connector Settings', 'aspire-wpforms-action'), 
        'manage_options', 
        'aspire_wpforms_ga_connector', 
        'aspire_wpforms_ga_connector_settings_page'
    );
}
add_action('admin_menu', 'aspire_wpforms_ga_connector_add_admin_menu');

// GA Connector Settings Page
function aspire_wpforms_ga_connector_settings_page() {
    ?>
    <div class="wrap">
        <style>
            /* Remove WPForms branding */
            .wpforms-logo,
            img[src*="wpforms"],
            .wpforms-header,
            .wpforms-page-title img {
                display: none !important;
            }
            /* Simple padding and spacing */
            .ga-connector-settings {
                padding: 20px;
                margin-top: 15px;
            }
            .form-table {
                margin-top: 15px;
            }
        </style>
        <h1><?php echo __('GA Connector Settings', 'aspire-wpforms-action'); ?></h1>
        <div class="ga-connector-settings">
            <form action="options.php" method="POST">
                <p><?php echo __('Please note: the hidden fields that this plugin produces for forms on this website will only be populated when loaded on production.', 'aspire-wpforms-action'); ?></p>
                <?php
                settings_fields('aspire_wpforms_ga_connector_settings');
                do_settings_sections('aspire_wpforms_ga_connector');
                submit_button();
                ?>
            </form>
        </div>
    </div>
    <?php
}

// Register GA Connector Settings
function aspire_wpforms_ga_connector_settings_init() {
    register_setting(
        'aspire_wpforms_ga_connector_settings',
        'ga_connector_option_name',
        'aspire_wpforms_ga_connector_sanitize'
    );

    add_settings_section(
        'aspire_wpforms_ga_connector_section',
        __('Account Settings', 'aspire-wpforms-action'),
        'aspire_wpforms_ga_connector_section_callback',
        'aspire_wpforms_ga_connector'
    );

    add_settings_field(
        'aspire_wpforms_ga_connector_account_id',
        __('Account ID', 'aspire-wpforms-action'),
        'aspire_wpforms_ga_connector_account_id_callback',
        'aspire_wpforms_ga_connector',
        'aspire_wpforms_ga_connector_section'
    );

    add_settings_field(
        'aspire_wpforms_ga_connector_tracking_type',
        __('Tracking Type', 'aspire-wpforms-action'),
        'aspire_wpforms_ga_connector_tracking_type_callback',
        'aspire_wpforms_ga_connector',
        'aspire_wpforms_ga_connector_section'
    );
}
add_action('admin_init', 'aspire_wpforms_ga_connector_settings_init');

// Section Description
function aspire_wpforms_ga_connector_section_callback() {
    echo __('Enter your GA Connector account ID and configure tracking settings.', 'aspire-wpforms-action');
}

// Account ID Field
function aspire_wpforms_ga_connector_account_id_callback() {
    $options = get_option('ga_connector_option_name');
    $value = isset($options['key']) ? $options['key'] : '';
    ?>
    <input type="text" name="ga_connector_option_name[key]" value="<?php echo esc_attr($value); ?>" class="regular-text" />
    <?php
}

// Tracking Type Field
function aspire_wpforms_ga_connector_tracking_type_callback() {
    $options = get_option('ga_connector_option_name');
    $tracking_type = isset($options['tracking_type']) ? $options['tracking_type'] : 'cookie';
    ?>
    <select name="ga_connector_option_name[tracking_type]">
        <option value="cookie" <?php selected($tracking_type, 'cookie'); ?>><?php _e('Cookie Based', 'aspire-wpforms-action'); ?></option>
        <option value="field" <?php selected($tracking_type, 'field'); ?>><?php _e('Field Based', 'aspire-wpforms-action'); ?></option>
    </select>
    <?php
}

// Sanitize settings
function aspire_wpforms_ga_connector_sanitize($options) {
    foreach ($options as $name => &$val) {
        if ($name == 'key') {
            $val = sanitize_text_field($val);
        } else if ($name == 'tracking_type') {
            $val = in_array($val, ['cookie', 'field']) ? $val : 'cookie';
        }
    }
    return $options;
}

// Add tracking script to header
function aspire_wpforms_ga_connector_header_script() {
    $ga_connector = get_option('ga_connector_option_name');
    if (!empty($ga_connector['key'])) {
        $tracking_type = isset($ga_connector['tracking_type']) ? $ga_connector['tracking_type'] : 'cookie';
        $script_url = $tracking_type === 'cookie' 
            ? 'https://ta.gaconnector.com/gaconnector.js' 
            : 'https://track.gaconnector.com/gaconnector.js';
            
        echo '<script src="' . esc_url($script_url) . '" type="text/javascript" data-cfasync="false"></script>';
        echo '<script type="text/javascript" data-cfasync="false">
            window.addEventListener("load", function() {
                if (typeof gaconnector2 !== "undefined") {
                    gaconnector2.track("' . esc_js($ga_connector['key']) . '");
                }
            });
        </script>';
    }
}
add_action('wp_head', 'aspire_wpforms_ga_connector_header_script');

// Add settings link to plugin page
function aspire_wpforms_ga_connector_plugin_action_links($links) {
    $settings_link = '<a href="options-general.php?page=aspire_wpforms_ga_connector">' . __('GA Connector Settings', 'aspire-wpforms-action') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'aspire_wpforms_ga_connector_plugin_action_links');

add_filter( 'wpforms_builder_settings_sections', 'wpforms_action_url_section', 20, 2 );
function wpforms_action_url_section( $sections, $form_data ) {
    $sections['pardot_integration'] = __( 'Salesforce Integration', 'wpforms' );
    return $sections;
}

add_filter('wpforms_form_settings_panel_content', 'wpforms_action_url_setting_to_wpforms_content', 10, 2);
function wpforms_action_url_setting_to_wpforms_content($instance) {
    echo '<div class="wpforms-panel-content-section wpforms-panel-content-section-pardot_integration">';
    echo '	<div class="wpforms-panel-content-section-title">' . __('Action URL', 'wpforms') . '</div>';
	echo '	<div class="wpforms-panel-content-section-field">';
    echo wpforms_panel_field(
        'text',
        'settings',
        'action_url',
        $instance->form_data,
        __( 'Enter the URL to send form data.', 'wpforms' ),
    );
    echo '	</div>';

    // Add field mappings UI
    echo '	<div class="wpforms-panel-content-section-title">' . __('Field Mappings', 'wpforms') . '</div>';
    echo '	<div class="wpforms-panel-content-section-subtitle" style="margin-bottom: 15px;">' . __('Please note: these mappings will only be saved when you press "Save Mappings Now"', 'wpforms') . '</div>';
    echo '	<div class="wpforms-panel-content-section-field">';
    echo '		<div id="mappings-loading" style="display:none; color:#666; margin-bottom:10px;">Loading mappings…</div>';
    echo '		<div id="mappings-list" class="wpforms-field-mappings"></div>';
    echo '		<button type="button" id="add-mapping" class="wpforms-btn wpforms-btn-md wpforms-btn-light-grey">' . __('Add Field Mapping', 'wpforms') . '</button>';
    echo '		<input type="hidden" name="mappings" id="mappings-data" value="">';
    echo '      <div style="margin-top: 15px;">';
    echo '          <button type="button" id="save-mappings-direct" class="wpforms-btn wpforms-btn-md wpforms-btn-orange" style="margin-right: 10px;">Save Mappings Now</button>';
    echo '          <span id="mappings-save-status"></span>';
    echo '      </div>';
    echo '	</div>';

    // Add JavaScript for field mappings
    echo '<script type="text/javascript">
    jQuery(document).ready(function($) {
        var mappings = [];
        var formFields = {};

        // Load existing mappings
        var formId = ' . $instance->form_data["id"] . ';
        $("#mappings-loading").show();
        $.get(ajaxurl, {
            action: "aspire_wpforms_get_mappings",
            form_id: formId,
            nonce: "' . wp_create_nonce("aspire_wpforms_get_mappings") . '"
        }, function(response) {
            $("#mappings-loading").hide();
            if (response.success) {
                formFields = response.data.fields || {};
                // Convert mappings from raw format to UI format
                mappings = [];
                Object.entries(response.data.mappings || {}).forEach(function(entry) {
                    var relation = entry[0];
                    var data = entry[1];
                    
                    if (data[0] === "conditional") {
                        // Handle multiple conditional rules for the same field
                        var conditionalRules = JSON.parse(data[1]);
                        if (Array.isArray(conditionalRules)) {
                            // Multiple rules for the same field
                            conditionalRules.forEach(function(rule) {
                                mappings.push({
                                    relation: relation,
                                    data: "conditional",
                                    value: JSON.stringify(rule)
                                });
                            });
                        } else {
                            // Single rule (backward compatibility)
                            mappings.push({
                                relation: relation,
                                data: "conditional",
                                value: data[1].toString()
                            });
                        }
                    } else {
                        // Regular field mapping
                        mappings.push({
                            relation: relation,
                            data: data[0],
                            value: data[1].toString()
                        });
                    }
                });
                renderMappings();
            }
        });

        // Add new mapping
        $("#add-mapping").on("click", function() {
            mappings.push({
                relation: "",
                data: "post",
                value: ""
            });
            renderMappings();
        });

        // Handle input changes for relation fields
        $(document).on("input change", ".wpforms-field-mapping-row .relation", function() {
            var row = $(this).closest(".wpforms-field-mapping-row");
            var index = row.data("index");
            mappings[index].relation = $(this).val();
            updateMappingsData();
            updateCopyPasteFormat();
        });

        // Handle input changes for value fields
        $(document).on("input change", ".wpforms-field-mapping-row .value", function() {
            var row = $(this).closest(".wpforms-field-mapping-row");
            var index = row.data("index");
            mappings[index].value = $(this).val();
            updateMappingsData();
            updateCopyPasteFormat();
        });

        // Handle data type change
        $(document).on("change", ".data", function() {
            var row = $(this).closest(".wpforms-field-mapping-row");
            var index = row.data("index");
            var type = $(this).val();
            var currentValue = mappings[index].value;
            mappings[index].data = type;
            // Only reset value if switching to/from field type or conditional type
            if ((type === "field" && mappings[index].value && !formFields[mappings[index].value]) || 
                (mappings[index].data === "field" && type !== "field") ||
                (type === "conditional" && mappings[index].data !== "conditional")) {
                mappings[index].value = "";
            }
            renderMappings();
        });

        // Handle conditional mapping field changes
        $(document).on("change input", ".conditional-if-field, .conditional-if-value, .conditional-then-value", function() {
            var row = $(this).closest(".wpforms-field-mapping-row");
            var index = row.data("index");
            
            
            if (mappings[index] && mappings[index].data === "conditional") {
                var conditionalData = {
                    ifField: row.find(".conditional-if-field").val(),
                    ifValue: row.find(".conditional-if-value").val(),
                    thenValue: row.find(".conditional-then-value").val()
                };
                mappings[index].value = JSON.stringify(conditionalData);
                window.updateMappingsData();
                updateCopyPasteFormat();
            }
        });

        // Render mappings list
        function renderMappings() {
            var html = "";
            mappings.forEach(function(mapping, index) {
                var valueField = "";
                
                // Create value input based on data type
                if (mapping.data === "field") {
                    valueField = `
                        <select class="value wpforms-field-mapping-field">
                            <option value="">Select WPForms field...</option>
                            ${Object.entries(formFields).map(([id, label]) => 
                                `<option value="${id}" ${mapping.value === id.toString() ? "selected" : ""}>${label}</option>`
                            ).join("")}
                        </select>`;
                } else if (mapping.data === "conditional") {
                    // Parse conditional value if it exists
                    var conditionalData = {ifField: "", ifValue: "", thenValue: ""};
                    if (mapping.value) {
                        try {
                            conditionalData = JSON.parse(mapping.value);
                        } catch (e) {
                            console.error("Error parsing conditional data:", e);
                            conditionalData = {ifField: "", ifValue: "", thenValue: ""};
                        }
                    }
                    valueField = `
                        <div class="conditional-mapping-container">
                            <div class="conditional-row">
                                <label>IF</label>
                                <select class="conditional-if-field wpforms-field-mapping-field" style="width: 150px;">
                                    <option value="">Select existing field...</option>
                                    ${Object.entries(formFields).map(([id, label]) => 
                                        `<option value="${id}" ${conditionalData.ifField === id.toString() ? "selected" : ""}>${label}</option>`
                                    ).join("")}
                                </select>
                                <label>EQUALS</label>
                                <input type="text" class="conditional-if-value wpforms-field-mapping-field" 
                                    placeholder="Value to match" value="${conditionalData.ifValue || ""}" style="width: 120px;">
                            </div>
                            <div class="conditional-row">
                                <label>THEN</label>
                                <span class="conditional-field-name">${mapping.relation || "Field Name"}</span>
                                <label>EQUALS</label>
                                <input type="text" class="conditional-then-value wpforms-field-mapping-field" 
                                    placeholder="Set to this value" value="${conditionalData.thenValue || ""}" style="width: 120px;">
                            </div>
                        </div>`;
                } else {
                    valueField = `
                        <input type="text" 
                            class="value wpforms-field-mapping-field" 
                            placeholder="Value" 
                            value="${mapping.value || ""}">`;
                }

                html += `
                    <div class="wpforms-field-mapping-row" data-index="${index}">
                        <div class="wpforms-field-mapping-input">
                            <input type="text" 
                                class="relation wpforms-field-mapping-field" 
                                placeholder="Field Name" 
                                value="${mapping.relation || ""}">
                        </div>
                        <div class="wpforms-field-mapping-type">
                            <select class="data wpforms-field-mapping-select">
                                <option value="post" ${mapping.data === "post" ? "selected" : ""}>POST Data</option>
                                <option value="field" ${mapping.data === "field" ? "selected" : ""}>WPForms Field</option>
                                <option value="replace" ${mapping.data === "replace" ? "selected" : ""}>Replace</option>
                                <option value="conditional" ${mapping.data === "conditional" ? "selected" : ""}>Conditional</option>
                            </select>
                        </div>
                        <div class="wpforms-field-mapping-input value-container">
                            ${valueField}
                        </div>
                        <div class="wpforms-field-mapping-remove">
                            <button type="button" class="wpforms-btn wpforms-btn-sm wpforms-btn-red remove-mapping">Remove</button>
                        </div>
                    </div>
                `;
            });
            $("#mappings-list").html(html);
            window.updateMappingsData();
            updateCopyPasteFormat();
        }

        // Remove mapping
        $(document).on("click", ".remove-mapping", function() {
            var index = $(this).closest(".wpforms-field-mapping-row").data("index");
            mappings.splice(index, 1);
            renderMappings();
        });

        // Update mappings data (global function)
        window.updateMappingsData = function() {
            var data = {};
            var conditionalRules = {}; // Store multiple conditional rules per field
            
            $(".wpforms-field-mapping-row").each(function() {
                var relation = $(this).find(".relation").val();
                var type = $(this).find(".data").val();
                var value = "";
                
                
                if (type === "conditional") {
                    // For conditional mappings, collect the data from the UI fields
                    var ifField = $(this).find(".conditional-if-field").val();
                    var ifValue = $(this).find(".conditional-if-value").val();
                    var thenValue = $(this).find(".conditional-then-value").val();
                    
                    
                    if (ifField && ifValue && thenValue) {
                        // Store multiple conditional rules for the same field
                        if (!conditionalRules[relation]) {
                            conditionalRules[relation] = [];
                        }
                        
                        conditionalRules[relation].push({
                            ifField: ifField,
                            ifValue: ifValue,
                            thenValue: thenValue
                        });
                        
                    } else {
                    }
                } else {
                    value = $(this).find(".value").val();
                    if (relation && type && value) {
                        data[relation] = [type, value];
                    }
                }
            });
            
            // Add all conditional rules to the data
            Object.keys(conditionalRules).forEach(function(fieldName) {
                data[fieldName] = ["conditional", JSON.stringify(conditionalRules[fieldName])];
            });
            
            $("#mappings-data").val(JSON.stringify(data));
        };

        // Update copy/paste format
        function updateCopyPasteFormat() {
            var formatted = "Action URL: " + $("#wpforms-panel-field-settings-action_url").val() + "\n\n";
            
            mappings.forEach(function(mapping) {
                if (mapping.relation && mapping.data && mapping.value) {
                    var rightSide = mapping.value;
                    var mappedFrom = "";
                    
                    if (mapping.data === "field" && formFields[mapping.value]) {
                        rightSide = formFields[mapping.value];
                        mappedFrom = "WPForms Field";
                    } else if (mapping.data === "conditional") {
                        try {
                            var conditionalData = JSON.parse(mapping.value);
                            var ifFieldLabel = formFields[conditionalData.ifField] || conditionalData.ifField;
                            rightSide = "IF \'" + ifFieldLabel + "\' EQUALS \'" + conditionalData.ifValue + "\' THEN \'" + mapping.relation + "\' EQUALS \'" + conditionalData.thenValue + "\'";
                            mappedFrom = "Conditional";
                        } catch (e) {
                            rightSide = mapping.value;
                            mappedFrom = "Conditional";
                        }
                    } else if (mapping.data === "post") {
                        mappedFrom = "POST Data";
                    } else if (mapping.data === "replace") {
                        mappedFrom = "Replace";
                    }
                    
                    formatted += mapping.relation + " [" + mappedFrom + "] " + rightSide + "\n";
                }
            });
            $("#copy-paste-mappings").val(formatted);
        }

        // Update mappings data when fields change
        $(document).on("change", ".wpforms-field-mapping-row input, .wpforms-field-mapping-row select", function() {
            window.updateMappingsData();
            updateCopyPasteFormat();
        });
        
        // Listen for WPForms form save button clicks
        $(document).on("click", "#wpforms-save, #wpforms-save-button, .wpforms-save", function() {
            
            // Make sure mappings data is up to date before save
            window.updateMappingsData();
            
            // Get the current mappings data
            var mappingsData = $("#mappings-data").val();
            
            if (!mappingsData) {
                console.error("No mappings data to save");
                return;
            }
            
            // Save via AJAX immediately to ensure its saved regardless of form submission
            $.post(ajaxurl, {
                action: "aspire_wpforms_save_imported_mappings",
                form_id: formId,
                mappings: mappingsData,
                nonce: "' . wp_create_nonce("aspire_wpforms_save_mappings") . '"
            })
            .done(function(response) {
                if (response.success) {
                } else {
                    console.error("Error saving mappings:", response.data ? response.data.message : "Unknown error");
                }
            })
            .fail(function(xhr, status, error) {
                console.error("AJAX error when saving mappings:", status, error);
            });
        });
        
        // Also hook into form submitted event to save mappings
        $(document).on("wpformsFormSave", function() {
            window.updateMappingsData();
            
            var mappingsData = $("#mappings-data").val();
            if (!mappingsData) return;
            
            $.post(ajaxurl, {
                action: "aspire_wpforms_save_imported_mappings",
                form_id: formId,
                mappings: mappingsData,
                nonce: "' . wp_create_nonce("aspire_wpforms_save_mappings") . '"
            });
        });
        
        // Save mappings data when form panel is initialized
        $(document).on("wpformsPanelSectionVisible", function(e, section) {
            if (section === "pardot_integration") {
                // When our section becomes visible, update any field changes made
                window.updateMappingsData();
            }
        });
        
        // Also save mappings when navigating away from our panel
        $(document).on("wpformsPanelSwitch", function() {
            if ($("#wpforms-panel-content-section-pardot_integration").is(":visible")) {
                window.updateMappingsData();
                
                var mappingsData = $("#mappings-data").val();
                if (!mappingsData) return;
                
                $.post(ajaxurl, {
                    action: "aspire_wpforms_save_imported_mappings",
                    form_id: formId,
                    mappings: mappingsData,
                    nonce: "' . wp_create_nonce("aspire_wpforms_save_mappings") . '"
                });
            }
        });
        
        // Export functionality
        $("#export-mappings").on("click", function() {
            var mappingsData = $("#mappings-data").val();
            if (!mappingsData) {
                alert("No configuration to export.");
                return;
            }
            
            // Create a downloadable JSON file
            var dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(mappingsData);
            var downloadAnchorNode = document.createElement("a");
            downloadAnchorNode.setAttribute("href", dataStr);
            downloadAnchorNode.setAttribute("download", "form-' . $instance->form_data["id"] . '-mappings.json");
            document.body.appendChild(downloadAnchorNode);
            downloadAnchorNode.click();
            downloadAnchorNode.remove();
        });
        
        // Trigger file input when import button is clicked
        $("#import-mappings").on("click", function() {
            $("#import-file").click();
        });
        
        // Handle file import
        $("#import-file").on("change", function(e) {
            var file = e.target.files[0];
            if (!file) return;
            
            var reader = new FileReader();
            reader.onload = function(event) {
                try {
                    var jsonData = JSON.parse(event.target.result);
                    
                    // Validate the imported data
                    if (typeof jsonData !== "object") {
                        throw new Error("Invalid JSON format");
                    }
                    
                    // Update the mappings data
                    $("#mappings-data").val(JSON.stringify(jsonData));
                    
                    // Convert the imported data to UI format
                    mappings = Object.entries(jsonData).map(([relation, data]) => ({
                        relation: relation,
                        data: data[0],
                        value: data[1].toString()
                    }));
                    
                    // Render the mappings in the UI
                    renderMappings();
                    
                    // Save the imported data to server immediately 
                    $.post(ajaxurl, {
                        action: "aspire_wpforms_save_imported_mappings",
                        form_id: formId,
                        mappings: JSON.stringify(jsonData),
                        nonce: "' . wp_create_nonce("aspire_wpforms_save_mappings") . '"
                    }, function(response) {
                        if (response.success) {
                            alert("Configuration imported and saved successfully!");
                        } else {
                            alert("Configuration imported, but there was an error saving it. Please save the form manually.");
                        }
                    }).fail(function() {
                        alert("Configuration imported, but there was an error saving it. Please save the form manually.");
                    });
                    
                } catch (error) {
                    alert("Error importing configuration: " + error.message);
                }
            };
            reader.readAsText(file);
        });
    });
    </script>';

    // Add copy/paste format area
    echo '<div class="wpforms-panel-content-section-field" style="margin-top: 20px;">
             <label for="copy-paste-mappings" class="wpforms-field-label">' . __('Copy/Paste Format', 'wpforms') . '</label>
             <p class="wpforms-field-description">' . __('Copy this formatted version for your reference', 'wpforms') . '</p>
             <textarea id="copy-paste-mappings" class="wpforms-field-mapping-copyable" style="width: 100%; min-height: 150px; font-family: monospace;" readonly></textarea>
           </div>';
           
    // Add import/export functionality
    echo '<div class="wpforms-panel-content-section-field" style="margin-top: 20px;">
            <div class="wpforms-panel-content-section-title">' . __('Import/Export Configuration', 'wpforms') . '</div>
            <p class="wpforms-field-description">' . __('Export your current configuration as a JSON file or import a configuration from another form.', 'wpforms') . '</p>
            <div style="display: flex; gap: 10px; margin-top: 10px;">
                <button type="button" id="export-mappings" class="wpforms-btn wpforms-btn-md wpforms-btn-light-grey">
                    ' . __('Export Configuration', 'wpforms') . '
                </button>
                <div>
                    <input type="file" id="import-file" accept=".json" style="display: none;">
                    <button type="button" id="import-mappings" class="wpforms-btn wpforms-btn-md wpforms-btn-light-grey">
                        ' . __('Import Configuration', 'wpforms') . '
                    </button>
                </div>
            </div>
          </div>';

    // Move Debug Mode section to the bottom with its own header and padding
    echo '<div class="wpforms-panel-content-section-field" style="margin-top: 35px; padding: 20px 0 0 0;">';
    echo '  <div class="wpforms-panel-content-section-title" style="padding-bottom: 8px;">' . __('Debug Options', 'wpforms') . '</div>';
    echo '  <div style="padding: 10px 0 0 0;">';
    echo wpforms_panel_field(
        'checkbox',
        'settings',
        'debug_mode',
        $instance->form_data,
        __( 'Debug Mode - Show form submission results', 'wpforms' ),
    );
    echo '  </div>';
    echo '</div>';

    echo '<style>
    .wpforms-field-mappings {
        margin-bottom: 15px;
    }
    .wpforms-panel-content-section-pardot_integration .wpforms-panel-content-section-title:nth-child(3) {
        margin-top: 35px;
    }
    .wpforms-field-mapping-row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
        padding: 10px;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 3px;
    }
    .wpforms-field-mapping-input {
        flex: 1;
    }
    .wpforms-field-mapping-type {
        width: 150px;
    }
    .wpforms-field-mapping-field,
    .wpforms-field-mapping-select {
        width: 100%;
        padding: 6px 8px;
        border: 1px solid #ddd;
        border-radius: 3px;
        height: 32px;
    }
    .wpforms-field-mapping-remove {
        width: 80px;
        text-align: right;
    }
    #add-mapping {
        margin-top: 10px;
    }
    .wpforms-btn {
        border: 1px solid;
        border-radius: 3px;
        cursor: pointer;
        display: inline-block;
        margin: 0;
        text-decoration: none;
        text-align: center;
        vertical-align: middle;
        white-space: nowrap;
        box-shadow: none;
    }
    .wpforms-btn-md {
        font-size: 13px;
        font-weight: 600;
        padding: 8px 12px;
    }
    .wpforms-btn-sm {
        font-size: 12px;
        font-weight: 400;
        padding: 5px 10px;
    }
    .wpforms-btn-light-grey {
        background-color: #f1f1f1;
        border-color: #ccc;
        color: #666;
    }
    .wpforms-btn-light-grey:hover {
        background-color: #e8e8e8;
    }
    .wpforms-btn-red {
        background-color: #dc3232;
        border-color: #dc3232;
        color: #fff;
    }
    .wpforms-btn-red:hover {
        background-color: #be2c2c;
        border-color: #be2c2c;
    }
    .wpforms-field-mapping-copyable {
        background: #f9f9f9;
        padding: 10px;
        border: 1px solid #ddd;
        resize: vertical;
    }
    .conditional-mapping-container {
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding: 10px;
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 4px;
    }
    .conditional-row {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .conditional-row label {
        font-weight: 600;
        color: #495057;
        white-space: nowrap;
    }
    .conditional-field-name {
        font-weight: 600;
        color: #0073aa;
        background: #e7f3ff;
        padding: 4px 8px;
        border-radius: 3px;
        border: 1px solid #b3d9ff;
        white-space: nowrap;
    }
    </style>';

    echo '</div>';
}

// Save custom URL setting
add_filter('wpforms_form_settings_defaults', 'wpforms_action_url_setting_to_wpforms', 10, 2);
function wpforms_action_url_setting_to_wpforms($defaults, $form_data) {
    $defaults['settings']['action_url'] = $form_data['settings']['action_url'] ?? '';
    return $defaults;
}


// Process the form and send data via POST
add_action( 'wpforms_process_complete', 'wpforms_action_post_to_script', 10, 4 );
function wpforms_action_post_to_script($fields, $entry, $form_data, $entry_id) {
    $form_id = $form_data['id'];

    // Get mappings from JSON file
    $form_fields = json_decode(file_get_contents(__DIR__ . '/forms/' . $form_id . '.json'));
    $updated_form_data = [];

    if($form_fields) {
        foreach($form_fields as $field => $field_data) {
            $type = $field_data[0];
            $value = $field_data[1];

            if($type === 'field') {
                // Check if this is a checkbox option (format: "field_id:checkbox:choice_label")
                if (strpos($value, ':checkbox:') !== false) {
                    list($field_id, $checkbox_part, $choice_label) = explode(':', $value, 3);
                    $field_id = intval($field_id);
                    
                    if (isset($fields[$field_id])) {
                        $is_checked = false;
                        
                        // Get the checkbox field from form data to check choice structure
                        $checkbox_field_config = null;
                        if (isset($form_data['fields'][$field_id]) && ($form_data['fields'][$field_id]['type'] === 'checkbox' || $form_data['fields'][$field_id]['type'] === 'checkboxes')) {
                            $checkbox_field_config = $form_data['fields'][$field_id];
                        }
                        
                        // Get the checkbox field value - check multiple possible locations
                        $checkbox_value = null;
                        // Check all possible locations where WPForms might store checkbox values
                        if (isset($fields[$field_id]['value_raw'])) {
                            $checkbox_value = $fields[$field_id]['value_raw'];
                        } else if (isset($fields[$field_id]['value'])) {
                            $checkbox_value = $fields[$field_id]['value'];
                        } else if (isset($fields[$field_id])) {
                            // If the field itself is an array, use it directly
                            $checkbox_value = $fields[$field_id];
                        }
                        
                        // Fallback: check raw POST data if value is still null
                        if ($checkbox_value === null && isset($_POST['wpforms']['fields'][$field_id])) {
                            $checkbox_value = $_POST['wpforms']['fields'][$field_id];
                        }
                        
                        // Normalize the choice label for comparison (decode HTML entities, trim)
                        $choice_label_normalized = trim(html_entity_decode($choice_label, ENT_QUOTES, 'UTF-8'));
                        
                        // If checkbox_value is null or empty, the checkbox is definitely not checked
                        if ($checkbox_value === null || (is_array($checkbox_value) && empty($checkbox_value)) || (is_string($checkbox_value) && trim($checkbox_value) === '')) {
                            $is_checked = false;
                        } else {
                            // Only proceed with matching if we have a value
                        
                        // Build a list of all possible values to match against (label, value, and choice index)
                        $choice_values_to_match = array($choice_label_normalized);
                        $choice_index_to_match = null;
                        
                        if ($checkbox_field_config && !empty($checkbox_field_config['choices'])) {
                            foreach ($checkbox_field_config['choices'] as $choice_index => $choice) {
                                $choice_label_check = !empty($choice['label']) ? trim(html_entity_decode($choice['label'], ENT_QUOTES, 'UTF-8')) : '';
                                if (strcasecmp($choice_label_check, $choice_label_normalized) === 0) {
                                    // Found matching choice
                                    $choice_index_to_match = $choice_index;
                                    
                                    // Add the choice value if set
                                    if (!empty($choice['value'])) {
                                        $choice_value = trim(html_entity_decode($choice['value'], ENT_QUOTES, 'UTF-8'));
                                        if (!in_array($choice_value, $choice_values_to_match)) {
                                            $choice_values_to_match[] = $choice_value;
                                        }
                                    }
                                    
                                    // Also add the choice ID if it exists (WPForms sometimes uses numeric IDs)
                                    if (isset($choice['id'])) {
                                        $choice_id = is_numeric($choice['id']) ? (string)$choice['id'] : trim(html_entity_decode($choice['id'], ENT_QUOTES, 'UTF-8'));
                                        if (!in_array($choice_id, $choice_values_to_match)) {
                                            $choice_values_to_match[] = $choice_id;
                                        }
                                    }
                                    
                                    break;
                                }
                            }
                        }
                        
                        // Handle different value formats
                        if (is_array($checkbox_value)) {
                            // Array of selected values - could be labels, values, or indices
                            // WPForms might store as indexed array or associative array
                            // First, check if array is empty (no checkboxes selected)
                            if (empty($checkbox_value)) {
                                $is_checked = false;
                            } else {
                                // Try multiple matching strategies
                                foreach ($checkbox_value as $key => $selected) {
                                    // Strategy 1: Handle associative arrays where key might be the choice ID/index
                                    if (is_numeric($key) && $choice_index_to_match !== null && intval($key) === $choice_index_to_match) {
                                        $is_checked = true;
                                        break;
                                    }
                                    
                                    // Strategy 2: Handle both string and numeric values
                                    $selected_normalized = '';
                                    if (is_array($selected)) {
                                        // If selected is itself an array, try to get a string representation
                                        $selected_normalized = implode(' ', array_map(function($v) {
                                            return is_numeric($v) ? (string)$v : trim(html_entity_decode($v, ENT_QUOTES, 'UTF-8'));
                                        }, $selected));
                                    } else {
                                        $selected_normalized = is_numeric($selected) ? (string)$selected : trim(html_entity_decode($selected, ENT_QUOTES, 'UTF-8'));
                                    }
                                    
                                    // Strategy 3: Check if this matches any of our possible values (exact or case-insensitive)
                                    foreach ($choice_values_to_match as $match_value) {
                                        if ($selected_normalized === $match_value || 
                                            strcasecmp($selected_normalized, $match_value) === 0 ||
                                            stripos($selected_normalized, $match_value) !== false ||
                                            stripos($match_value, $selected_normalized) !== false) {
                                            $is_checked = true;
                                            break 2; // Break out of both loops
                                        }
                                    }
                                    
                                    // Strategy 4: Also check if this is the choice index (WPForms might store indices)
                                    if ($choice_index_to_match !== null && is_numeric($selected) && intval($selected) === $choice_index_to_match) {
                                        $is_checked = true;
                                        break;
                                    }
                                    
                                    // Strategy 5: Check if the key itself matches (for associative arrays)
                                    $key_normalized = is_numeric($key) ? (string)$key : trim(html_entity_decode($key, ENT_QUOTES, 'UTF-8'));
                                    foreach ($choice_values_to_match as $match_value) {
                                        if ($key_normalized === $match_value || 
                                            strcasecmp($key_normalized, $match_value) === 0 ||
                                            stripos($key_normalized, $match_value) !== false ||
                                            stripos($match_value, $key_normalized) !== false) {
                                            $is_checked = true;
                                            break 2; // Break out of both loops
                                        }
                                    }
                                    
                                    // Strategy 6: WPForms might store checkbox values where the key is the choice label/value
                                    // and the presence of the key indicates it's checked (value might be empty or the same)
                                    if ($checkbox_field_config && !empty($checkbox_field_config['choices'])) {
                                        foreach ($checkbox_field_config['choices'] as $config_choice_index => $config_choice) {
                                            $config_label = !empty($config_choice['label']) ? trim(html_entity_decode($config_choice['label'], ENT_QUOTES, 'UTF-8')) : '';
                                            $config_value = !empty($config_choice['value']) ? trim(html_entity_decode($config_choice['value'], ENT_QUOTES, 'UTF-8')) : $config_label;
                                            
                                            // Check if key matches choice label or value (with flexible matching)
                                            if (($key_normalized === $config_label || strcasecmp($key_normalized, $config_label) === 0 ||
                                                 stripos($key_normalized, $config_label) !== false || stripos($config_label, $key_normalized) !== false) ||
                                                ($key_normalized === $config_value || strcasecmp($key_normalized, $config_value) === 0 ||
                                                 stripos($key_normalized, $config_value) !== false || stripos($config_value, $key_normalized) !== false)) {
                                                // If this matches our target choice, mark as checked
                                                if (strcasecmp($config_label, $choice_label_normalized) === 0 ||
                                                    stripos($config_label, $choice_label_normalized) !== false ||
                                                    stripos($choice_label_normalized, $config_label) !== false) {
                                                    $is_checked = true;
                                                    break 2; // Break out of both loops
                                                }
                                            }
                                        }
                                    }
                                }
                                
                                // Strategy 7: If still not checked, try using in_array with case-insensitive comparison
                                if (!$is_checked) {
                                    foreach ($choice_values_to_match as $match_value) {
                                        // Check if match_value exists in the array (case-insensitive)
                                        foreach ($checkbox_value as $check_val) {
                                            $check_val_str = is_array($check_val) ? implode(' ', $check_val) : (string)$check_val;
                                            if (stripos($check_val_str, $match_value) !== false || stripos($match_value, $check_val_str) !== false) {
                                                $is_checked = true;
                                                break 2;
                                            }
                                        }
                                    }
                                }
                            }
                        } else if (is_string($checkbox_value) && !empty($checkbox_value)) {
                            // String format - could be comma-separated or single value
                            $selected_values = array_map('trim', explode(',', $checkbox_value));
                            foreach ($selected_values as $selected) {
                                $selected_normalized = trim(html_entity_decode($selected, ENT_QUOTES, 'UTF-8'));
                                
                                // Check if this matches any of our possible values
                                foreach ($choice_values_to_match as $match_value) {
                                    if ($selected_normalized === $match_value || 
                                        strcasecmp($selected_normalized, $match_value) === 0) {
                                        $is_checked = true;
                                        break 2; // Break out of both loops
                                    }
                                }
                            }
                        } else if (is_numeric($checkbox_value) && $choice_index_to_match !== null) {
                            // Handle case where checkbox_value is a numeric index
                            if (intval($checkbox_value) === $choice_index_to_match) {
                                $is_checked = true;
                            }
                        }
                        
                        // Additional check: if checkbox_value exists but is_checked is still false,
                        // and we have a matching choice, check if WPForms stores it differently
                        if (!$is_checked && $checkbox_value !== null && $checkbox_field_config) {
                            // Try checking if the field has a 'choices' structure that matches
                            if (isset($fields[$field_id]['choices']) && is_array($fields[$field_id]['choices'])) {
                                foreach ($fields[$field_id]['choices'] as $choice_key => $choice_val) {
                                    if (is_array($choice_val) && isset($choice_val['label'])) {
                                        $check_label = trim(html_entity_decode($choice_val['label'], ENT_QUOTES, 'UTF-8'));
                                        if (strcasecmp($check_label, $choice_label_normalized) === 0) {
                                            // Check if this choice is selected
                                            if (isset($choice_val['selected']) && $choice_val['selected']) {
                                                $is_checked = true;
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                            
                            // Final fallback: if checkbox_value is not empty and we have a matching choice,
                            // check if the choice label appears anywhere in the checkbox value (very flexible matching)
                            if (!$is_checked && !empty($checkbox_value)) {
                                $checkbox_value_str = is_array($checkbox_value) ? json_encode($checkbox_value) : (string)$checkbox_value;
                                if (stripos($checkbox_value_str, $choice_label_normalized) !== false) {
                                    $is_checked = true;
                                }
                            }
                        }
                        } // Close the else block from earlier
                        
                        $updated_form_data[$field] = $is_checked ? '1' : '0';
                    } else {
                        $updated_form_data[$field] = '0';
                    }
                }
                // Check if this is a Full Name sub-field (format: "field_id:first" or "field_id:last")
                else if (strpos($value, ':') !== false) {
                    list($field_id, $sub_field) = explode(':', $value, 2);
                    if (isset($fields[$field_id]) && in_array($sub_field, ['first', 'last'])) {
                        // Access the sub-field from the Full Name field
                        $updated_form_data[$field] = isset($fields[$field_id][$sub_field]) ? $fields[$field_id][$sub_field] : '';
                    } else {
                        $updated_form_data[$field] = '';
                    }
                } else {
                    // Regular field mapping
                    $updated_form_data[$field] = isset($fields[$value]['value']) ? $fields[$value]['value'] : '';
                }
            } else if($type === 'post') {
                // Get the value from the hidden field that was submitted
                // Check multiple possible locations for POST data
                $post_value = '';
                if (isset($_POST[$field])) {
                    $post_value = $_POST[$field];
                } else if (isset($_REQUEST[$field])) {
                    $post_value = $_REQUEST[$field];
                } else if (isset($entry[$field])) {
                    $post_value = $entry[$field];
                } else if (isset($fields[$field])) {
                    // Sometimes POST data might be in the fields array
                    $post_value = is_array($fields[$field]) ? (isset($fields[$field]['value']) ? $fields[$field]['value'] : '') : $fields[$field];
                }
                
                // If POST value is empty, use the configured value as fallback
                // (since hidden fields are pre-populated with this value)
                if (empty($post_value) && !empty($value)) {
                    $post_value = $value;
                }
                
                $updated_form_data[$field] = !empty($post_value) ? filter($post_value) : '';
            } else if($type === 'replace') {
                $refUrl = $_SERVER['HTTP_REFERER'];

                switch($value) {
                    case "Page":
                        $updated_form_data[$field] = filter($refUrl);
                        break;
                    case "CTA":
                        $id = url_to_postid($refUrl);
                        $title = get_the_title($id);
                        $updated_form_data[$field] = filter($title);
                        break;
                    case "LeadSource":
                        $updated_form_data[$field] = "Website Request";
                        break;
                    case "LeadSourceName":
                        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
                        $updated_form_data[$field] = $current_url;
                        break;
                }
            } else if($type === 'conditional') {
                // Handle conditional mapping (supports multiple rules per field)
                $conditional_data = json_decode($value, true);
                
                if(is_array($conditional_data)) {
                    // Multiple conditional rules for the same field
                    foreach($conditional_data as $rule) {
                        if(isset($rule['ifField']) && isset($rule['ifValue']) && isset($rule['thenValue'])) {
                            $if_field_value = isset($fields[$rule['ifField']]['value']) ? $fields[$rule['ifField']]['value'] : '';
                            
                            if($if_field_value == $rule['ifValue']) {
                                $updated_form_data[$field] = $rule['thenValue'];
                                break; // Stop at first matching rule
                            }
                        }
                    }
                } else if($conditional_data && isset($conditional_data['ifField']) && isset($conditional_data['ifValue']) && isset($conditional_data['thenValue'])) {
                    // Single conditional rule (backward compatibility)
                    $if_field_value = isset($fields[$conditional_data['ifField']]['value']) ? $fields[$conditional_data['ifField']]['value'] : '';
                    
                    if($if_field_value == $conditional_data['ifValue']) {
                        $updated_form_data[$field] = $conditional_data['thenValue'];
                    }
                }
            }
        }
    }

    // Get the URL from the custom field
    $url = $form_data['settings']['action_url'] ?? '';
    if(empty($url)) return;

    $result = wp_remote_post($url, [
        'method' => 'POST',
        'body' => $updated_form_data,
    ]);

    // If debug mode is enabled, store the result for display
    if (!empty($form_data['settings']['debug_mode'])) {
        // Store the result in a transient with a unique key based on form ID and entry ID
        $transient_key = 'wpforms_debug_' . $form_id . '_' . $entry_id;
        $debug_data = array(
            'request' => array(
                'url' => $url,
                'data' => $updated_form_data
            ),
            'response' => array(
                'code' => wp_remote_retrieve_response_code($result),
                'body' => wp_remote_retrieve_body($result),
                'headers' => wp_remote_retrieve_headers($result)
            ),
            'timestamp' => current_time('mysql')
        );
        set_transient($transient_key, $debug_data, HOUR_IN_SECONDS);

        // Add the debug info to the form confirmation message
        add_filter('wpforms_process_smart_tags', function($content, $form_data, $fields = [], $entry_id = 0) use ($debug_data) {
            $debug_html = '<div class="wpforms-debug-info" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;">';
            $debug_html .= '<h4 style="margin-top: 0;">Form Submission Debug Information</h4>';
            
            // Request Details
            $debug_html .= '<div style="margin-bottom: 15px;">';
            $debug_html .= '<strong>Request URL:</strong> ' . esc_html($debug_data['request']['url']) . '<br>';
            $debug_html .= '<strong>Request Data:</strong><br>';
            $debug_html .= '<pre style="background: #fff; padding: 10px; overflow: auto;">' . esc_html(json_encode($debug_data['request']['data'], JSON_PRETTY_PRINT)) . '</pre>';
            $debug_html .= '</div>';
            
            // Response Details
            $debug_html .= '<div>';
            $debug_html .= '<strong>Response Code:</strong> ' . esc_html($debug_data['response']['code']) . '<br>';
            $debug_html .= '<strong>Response Body:</strong><br>';
            $debug_html .= '<pre style="background: #fff; padding: 10px; overflow: auto;">' . esc_html($debug_data['response']['body']) . '</pre>';
            $debug_html .= '</div>';
            
            $debug_html .= '</div>';

            return $content . $debug_html;
        }, 10, 4);
    }
}

function filter($val) {
    return strip_tags($val);
}

// Add AJAX handler for getting form fields and mappings
add_action('wp_ajax_aspire_wpforms_get_mappings', 'aspire_wpforms_get_mappings');
function aspire_wpforms_get_mappings() {
    check_ajax_referer('aspire_wpforms_get_mappings', 'nonce');

    $form_id = intval($_GET['form_id']);
    if (!$form_id) {
        wp_send_json_error();
    }

    // Get form fields
    $form = wpforms()->form->get($form_id);
    $form_data = wpforms_decode($form->post_content);
    $fields = [];
    
    if (!empty($form_data['fields'])) {
        foreach ($form_data['fields'] as $field) {
            // Skip fields with null or empty labels
            if (!empty($field['label']) && $field['label'] !== 'null') {
                $fields[$field['id']] = $field['label'];
                
                // If this is a Full Name field (type === 'name'), expose first_name and last_name sub-fields
                if (isset($field['type']) && $field['type'] === 'name') {
                    $fields[$field['id'] . ':first'] = $field['label'] . ' - First Name';
                    $fields[$field['id'] . ':last'] = $field['label'] . ' - Last Name';
                }
                
                // If this is a Checkbox or Checkboxes field, expose each checkbox option as a separate selectable option
                if (isset($field['type']) && ($field['type'] === 'checkbox' || $field['type'] === 'checkboxes') && !empty($field['choices'])) {
                    foreach ($field['choices'] as $choice_index => $choice) {
                        // Use the choice label, or value if available, or fallback to index
                        $choice_label = !empty($choice['label']) ? $choice['label'] : (!empty($choice['value']) ? $choice['value'] : 'Option ' . ($choice_index + 1));
                        // WPForms typically uses the label as the value when no explicit value is set
                        // Store the label as the identifier since that's what WPForms uses
                        $choice_identifier = $choice_label;
                        
                        // Format: field_id:checkbox:choice_label
                        $fields[$field['id'] . ':checkbox:' . $choice_identifier] = $field['label'] . ': ' . $choice_label;
                    }
                }
            }
        }
    }

    // Get existing mappings
    $mappings = [];
    $file_path = plugin_dir_path(__FILE__) . 'forms/' . $form_id . '.json';
    if (file_exists($file_path)) {
        $json_content = file_get_contents($file_path);
        if ($json_content !== false) {
            $mappings = json_decode($json_content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('Error decoding JSON for form ' . $form_id . ': ' . json_last_error_msg());
                $mappings = [];
            }
        }
    }

    wp_send_json_success(array(
        'fields' => $fields,
        'mappings' => $mappings // Send the raw mappings format
    ));
}

// Add AJAX handler for saving imported mappings
add_action('wp_ajax_aspire_wpforms_save_imported_mappings', 'aspire_wpforms_save_imported_mappings');
function aspire_wpforms_save_imported_mappings() {
    check_ajax_referer('aspire_wpforms_save_mappings', 'nonce');
    
    $form_id = intval($_POST['form_id']);
    if (!$form_id) {
        error_log('Aspire WPForms: Invalid form ID');
        wp_send_json_error(array('message' => 'Invalid form ID'));
        return;
    }
    
    // Get the raw mappings JSON
    $raw_mappings = isset($_POST['mappings']) ? stripslashes($_POST['mappings']) : '';
    if (empty($raw_mappings)) {
        error_log('Aspire WPForms: Empty mapping data');
        wp_send_json_error(array('message' => 'Empty mapping data'));
        return;
    }
    
    // Validate JSON before saving
    $mappings_array = json_decode($raw_mappings, true);
    if (!is_array($mappings_array)) {
        error_log('Aspire WPForms: Invalid JSON in mappings: ' . $raw_mappings);
        wp_send_json_error(array('message' => 'Invalid JSON in mappings'));
        return;
    }
    
    // Create a timestamp file first to test write permissions
    $timestamp_file = plugin_dir_path(__FILE__) . 'timestamp.txt';
    if (file_put_contents($timestamp_file, date('Y-m-d H:i:s')) === false) {
        error_log('Aspire WPForms: Cannot write to plugin directory');
        wp_send_json_error(array('message' => 'Cannot write to plugin directory'));
        return;
    }
    
    // Ensure forms directory exists
    $forms_dir = plugin_dir_path(__FILE__) . 'forms';
    if (!file_exists($forms_dir)) {
        if (!mkdir($forms_dir, 0755, true)) {
            error_log('Aspire WPForms: Failed to create forms directory');
            wp_send_json_error(array('message' => 'Failed to create forms directory'));
            return;
        }
    }
    
    // Define target file path
    $file_path = $forms_dir . '/' . $form_id . '.json';
    
    // Direct file writing with error handling
    $result = file_put_contents($file_path, $raw_mappings);
    
    if ($result !== false) {
        error_log('Aspire WPForms: Successfully saved ' . $result . ' bytes to ' . $file_path);
        wp_send_json_success(array(
            'message' => 'Mappings saved successfully',
            'bytes' => $result,
            'path' => $file_path
        ));
    } else {
        error_log('Aspire WPForms: Failed to save mappings to ' . $file_path);
        wp_send_json_error(array('message' => 'Failed to save mappings'));
    }
}

// Add direct form processing to ensure mappings are saved 
add_action('admin_footer', 'aspire_wpforms_admin_footer_script');
function aspire_wpforms_admin_footer_script() {
    // Only add script on WPForms builder pages
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'wpforms') === false) {
        return;
    }
    
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Get the form ID from the URL
        function getFormIdFromUrl() {
            var urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('form_id');
        }
        
        // Direct form submission interception
        $(document).on('submit', '#wpforms-builder-form', function() {
            var formId = getFormIdFromUrl();
            if (!formId) return;
            
            // Get the mappings data
            var mappingsData = $('#mappings-data').val();
            if (!mappingsData) return;
            
            
            // Perform a synchronous AJAX call to save the mappings
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                async: false, // Make sure this completes before form submission
                data: {
                    action: 'aspire_wpforms_save_imported_mappings',
                    form_id: formId,
                    mappings: mappingsData,
                    nonce: '<?php echo wp_create_nonce("aspire_wpforms_save_mappings"); ?>'
                },
                success: function(response) {
                }
            });
        });
        
        // Also add save functionality to the button click as backup
        $(document).on('click', '#wpforms-save, #wpforms-save-button, .wpforms-save', function() {
            var formId = getFormIdFromUrl();
            if (!formId) return;
            
            updateMappingsData();
            var mappingsData = $('#mappings-data').val();
            if (!mappingsData) return;
            
            
            // Save via AJAX
            $.post(ajaxurl, {
                action: 'aspire_wpforms_save_imported_mappings',
                form_id: formId,
                mappings: mappingsData,
                nonce: '<?php echo wp_create_nonce("aspire_wpforms_save_mappings"); ?>'
            });
        });
        
        // Direct save button for mappings
        $(document).on('click', '#save-mappings-direct', function() {
            $('#mappings-save-status').html('<span style="color:#999;">Saving...</span>');
            
            // Use the global updateMappingsData function
            window.updateMappingsData();
            
            var mappingsData = $('#mappings-data').val();
            if (!mappingsData) {
                $('#mappings-save-status').html('<span style="color:red;">No mapping data to save</span>');
                return;
            }
            
            var formId = getFormIdFromUrl();
            if (!formId) {
                $('#mappings-save-status').html('<span style="color:red;">Cannot determine form ID</span>');
                return;
            }
            
            
            // Save via AJAX with status update
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'aspire_wpforms_save_imported_mappings',
                    form_id: formId,
                    mappings: mappingsData,
                    nonce: '<?php echo wp_create_nonce("aspire_wpforms_save_mappings"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $('#mappings-save-status').html('<span style="color:green;">Saved successfully!</span>');
                        setTimeout(function() {
                            $('#mappings-save-status').html('');
                        }, 3000);
                    } else {
                        $('#mappings-save-status').html('<span style="color:red;">Error: ' + 
                            (response.data && response.data.message ? response.data.message : 'Unknown error') + '</span>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error when saving mappings:', status, error);
                    $('#mappings-save-status').html('<span style="color:red;">Error saving: ' + status + '</span>');
                }
            });
        });
    });
    </script>
    <?php
}

function aspire_check_for_plugin_update($checked_data) {
    if (empty($checked_data->checked)) {
        return $checked_data;
    }
    
    // Get current version
    $plugin_slug = plugin_basename(__FILE__);
    if (empty($checked_data->checked[$plugin_slug])) {
        return $checked_data;
    }
    
    // Check your server for updates
    $response = wp_remote_get('https://github.com/ntomkin/aspire-wpforms-action/blob/master/wpforms-action.php');
    if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
        return $checked_data;
    }
    
    $update_data = wp_remote_retrieve_body($response);
    // Parse the contents of the plugin file to get the version number
    $version = extract_version_from_plugin_file($update_data);
    if (version_compare($checked_data->checked[$plugin_slug], $version, '<')) {
        $checked_data->response[$plugin_slug] = $update_data;
    }
    
    return $checked_data;
}
add_filter('pre_set_site_transient_update_plugins', 'aspire_check_for_plugin_update');

function extract_version_from_plugin_file($plugin_data) {
    // Use a regular expression to find the version number in the plugin data
    if (preg_match('/Version: (.*)/', $plugin_data, $matches)) {
        return trim($matches[1]);
    }
    return '1.0.0';
}

// Add hidden fields to WPForms forms
add_action('wpforms_display_field_before', 'aspire_wpforms_hidden_fields', 10, 2);
function aspire_wpforms_hidden_fields($field, $form_data) {
    static $fields_added = false;
    
    // Only add fields once per form
    if ($fields_added) {
        return;
    }
    
    $form_id = $form_data['id'];
    $hidden_fields_html = '';
    $conditional_rules = [];
    
    // Path to the form JSON configuration file
    $file_path = plugin_dir_path(__FILE__) . 'forms/' . $form_id . '.json';
    
    // Check if form config exists
    if (file_exists($file_path)) {
        $json_content = file_get_contents($file_path);
        if ($json_content !== false) {
            $form_fields = json_decode($json_content, true);
            
            if (is_array($form_fields)) {
                // Go through fields in the form configuration
                foreach ($form_fields as $field_name => $field_data) {
                    // Add POST Data fields as hidden inputs
                    if ($field_data[0] === 'post') {
                        $hidden_fields_html .= "<input type='hidden' name='{$field_name}' data-gaconnector='{$field_data[1]}' value='{$field_data[1]}'>";
                    }
                    // Add conditional fields as hidden inputs
                    else if ($field_data[0] === 'conditional') {
                        $conditional_data = json_decode($field_data[1], true);
                        if ($conditional_data) {
                            $hidden_fields_html .= "<input type='hidden' name='{$field_name}' id='{$field_name}' value='' data-conditional-field='true'>";
                            
                            // Handle multiple conditional rules for the same field
                            if (is_array($conditional_data) && isset($conditional_data[0])) {
                                // Multiple rules
                                foreach($conditional_data as $rule) {
                                    $conditional_rules[] = [
                                        'target_field' => $field_name,
                                        'if_field' => $rule['ifField'],
                                        'if_value' => $rule['ifValue'],
                                        'then_value' => $rule['thenValue']
                                    ];
                                }
                            } else {
                                // Single rule (backward compatibility)
                                $conditional_rules[] = [
                                    'target_field' => $field_name,
                                    'if_field' => $conditional_data['ifField'],
                                    'if_value' => $conditional_data['ifValue'],
                                    'then_value' => $conditional_data['thenValue']
                                ];
                            }
                        }
                    }
                }
            }
        }
    }
    
    
    // Output the hidden fields
    echo $hidden_fields_html;
    
    
    // Add JavaScript for conditional logic if we have conditional rules
    if (!empty($conditional_rules)) {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var conditionalRules = <?php echo json_encode($conditional_rules); ?>;
            
            
            // Function to check and apply conditional rules
            function applyConditionalRules() {
                conditionalRules.forEach(function(rule) {
                    // Try multiple possible field name formats for WPForms
                    var ifField = $('input[name="wpforms[fields][' + rule.if_field + ']"], select[name="wpforms[fields][' + rule.if_field + ']"], input[name="wpforms[fields][' + rule.if_field + '][]"], select[name="wpforms[fields][' + rule.if_field + '][]"], input[name*="[' + rule.if_field + ']"], select[name*="[' + rule.if_field + ']"]');
                    var targetField = $('input[id="' + rule.target_field + '"], select[id="' + rule.target_field + '"]');
                    
                    // Try alternative selectors if not found
                    if (ifField.length === 0) {
                        var altField = $('input[data-field-id="' + rule.if_field + '"], select[data-field-id="' + rule.if_field + '"]');
                        if (altField.length > 0) {
                            ifField = altField;
                        }
                    }
                    
                    if (ifField.length && targetField.length) {
                        // Get the actual selected value for radio buttons and selects
                        var currentValue;
                        if (ifField.is(':radio')) {
                            // For radio buttons, get the checked value
                            currentValue = ifField.filter(':checked').val();
                        } else if (ifField.is('select')) {
                            // For selects, get the selected option value
                            currentValue = ifField.find('option:selected').val();
                        } else {
                            // For other inputs, use regular val()
                            currentValue = ifField.val();
                        }
                        
                        if (currentValue == rule.if_value) {
                            // Set the value using multiple methods to ensure it sticks
                            targetField.val(rule.then_value);
                            targetField.attr('value', rule.then_value);
                            
                            // Also try setting the property directly
                            if (targetField[0]) {
                                targetField[0].value = rule.then_value;
                            }
                            
                            // Force trigger change event to ensure WPForms processes the value
                            targetField.trigger('change');
                        }
                    }
                });
            }
            
            // Apply rules on page load
            applyConditionalRules();
            
            
            // Debounce function to prevent infinite loops
            var applyTimeout;
            function debouncedApplyRules() {
                clearTimeout(applyTimeout);
                applyTimeout = setTimeout(function() {
                    applyConditionalRules();
                }, 100);
            }
            
            // Apply rules when form fields change (exclude hidden fields we create)
            $(document).on("change", 'input[name^="wpforms[fields]["]:not([id^="lead_"]), select[name^="wpforms[fields]["]:not([id^="lead_"])', function() {
                debouncedApplyRules();
            });
            
            // Also listen for any form field changes more broadly
            $(document).on("change input", 'input, select', function() {
                // Skip our own hidden fields to prevent loops
                if ($(this).attr('data-conditional-field') === 'true') {
                    return;
                }
                debouncedApplyRules();
            });
        });
        </script>
        <?php
    }
    
    $fields_added = true;
}

?>