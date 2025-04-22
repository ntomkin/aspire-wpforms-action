<?php
/*
 * Plugin Name: Aspire Software: WPForms Actions for Pardot
 * Version: 1.3.2
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
        echo '<script type="text/javascript" data-cfasync="false">gaconnector2.track("' . esc_js($ga_connector['key']) . '");</script>';
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
    $sections['pardot_integration'] = __( 'Pardot Integration', 'wpforms' );
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
    echo '	<div class="wpforms-panel-content-section-field">';
    echo '		<div id="mappings-list" class="wpforms-field-mappings"></div>';
    echo '		<button type="button" id="add-mapping" class="wpforms-btn wpforms-btn-md wpforms-btn-light-grey">' . __('Add Field Mapping', 'wpforms') . '</button>';
    echo '		<input type="hidden" name="mappings" id="mappings-data" value="">';
    echo '	</div>';

    // Add JavaScript for field mappings
    echo '<script type="text/javascript">
    jQuery(document).ready(function($) {
        var mappings = [];
        var formFields = {};

        // Load existing mappings
        var formId = ' . $instance->form_data["id"] . ';
        $.get(ajaxurl, {
            action: "aspire_wpforms_get_mappings",
            form_id: formId,
            nonce: "' . wp_create_nonce("aspire_wpforms_get_mappings") . '"
        }, function(response) {
            if (response.success) {
                formFields = response.data.fields || {};
                // Convert mappings from raw format to UI format
                mappings = Object.entries(response.data.mappings || {}).map(([relation, data]) => ({
                    relation: relation,
                    data: data[0],
                    value: data[1].toString()
                }));
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
            updateMappingsData();
            updateCopyPasteFormat();
        }

        // Handle data type change
        $(document).on("change", ".data", function() {
            var row = $(this).closest(".wpforms-field-mapping-row");
            var index = row.data("index");
            var type = $(this).val();
            mappings[index].data = type;
            mappings[index].value = ""; // Reset value when changing type
            renderMappings();
        });

        // Remove mapping
        $(document).on("click", ".remove-mapping", function() {
            var index = $(this).closest(".wpforms-field-mapping-row").data("index");
            mappings.splice(index, 1);
            renderMappings();
        });

        // Update mappings data
        function updateMappingsData() {
            var data = {};
            $(".wpforms-field-mapping-row").each(function() {
                var relation = $(this).find(".relation").val();
                var type = $(this).find(".data").val();
                var value = $(this).find(".value").val();
                if (relation && type && value) {
                    data[relation] = [type, value];
                }
            });
            $("#mappings-data").val(JSON.stringify(data));
        }

        // Update copy/paste format
        function updateCopyPasteFormat() {
            var formatted = "Action URL: " + $("#wpforms-panel-field-settings-action_url").val() + "\n\n";
            
            mappings.forEach(function(mapping) {
                if (mapping.relation && mapping.data && mapping.value) {
                    var rightSide = mapping.value;
                    if (mapping.data === "field" && formFields[mapping.value]) {
                        rightSide = formFields[mapping.value];
                    }
                    
                    var mappedFrom = "";
                    if (mapping.data === "post") {
                        mappedFrom = "POST Data";
                    } else if (mapping.data === "field") {
                        mappedFrom = "WPForms Field";
                    } else {
                        mappedFrom = "Replace";
                    }
                    
                    formatted += mapping.relation + " [" + mappedFrom + "] " + rightSide + "\n";
                }
            });
            $("#copy-paste-mappings").val(formatted);
        }

        // Update mappings data when fields change
        $(document).on("change", ".wpforms-field-mapping-row input, .wpforms-field-mapping-row select", function() {
            updateMappingsData();
            updateCopyPasteFormat();
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

    // Add JavaScript for import/export (include in the same script tag as above)
    echo '<script type="text/javascript">
    jQuery(document).ready(function($) {
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
                    alert("Configuration imported successfully!");
                } catch (error) {
                    alert("Error importing configuration: " + error.message);
                }
            };
            reader.readAsText(file);
        });
    });
    </script>';

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

    // Modify parameter names as needed
    $form_fields = json_decode(file_get_contents(__DIR__ . '/forms/' . $form_id . '.json'));
    $updated_form_data = [];


	if($form_fields) {

	    foreach($form_fields as $field => $field_data) {
			$type = $field_data[0];
			$data = $field_data[1];


			if($type === 'field') {
				$updated_form_data[$field] = $fields[$data]['value'];
			} else if($type === 'post') {
				$updated_form_data[$field] = filter($_POST[$data]);
			} else if($type === 'replace') {


				$refUrl = $_SERVER['HTTP_REFERER'];

				switch($data) {

					case "Page":
						//	Page URL
						$updated_form_data[$field] = filter($refUrl);
						break;

					case "CTA":
						//	Page title
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
			}
		}

	}


    // Get the URL from the custom field
    $url = $form_data['settings']['action_url'] ?? '';

	if( empty( $url ) ) return;

	$result = wp_remote_post($url, [
		'method'    => 'POST',
		'body'      => $updated_form_data,
	]);


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

// Handle saving mappings
add_action('wpforms_save_form_args', 'aspire_wpforms_save_mappings', 10, 2);
function aspire_wpforms_save_mappings($form_data, $form) {
    if (!isset($_POST['mappings'])) {
        return $form_data;
    }

    $mappings = json_decode(stripslashes($_POST['mappings']), true);
    if (!$mappings) {
        return $form_data;
    }

    $forms_dir = plugin_dir_path(__FILE__) . 'forms';
    if (!file_exists($forms_dir)) {
        wp_mkdir_p($forms_dir);
    }

    $file_path = $forms_dir . '/' . $form_data['id'] . '.json';
    file_put_contents($file_path, json_encode($mappings, JSON_PRETTY_PRINT));

    return $form_data;
}

// Add hidden fields to WPForms forms
add_action('wpforms_frontend_output_before', 'aspire_wpforms_ga_connector_hidden_fields', 10, 2);
function aspire_wpforms_ga_connector_hidden_fields($form_data, $form) {
    $form_id = $form_data['id'];
    $hidden_fields_html = '';
    
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
                    // If the field name starts with 'gaconnector', add it as a hidden field
                    if (strpos($field_name, 'gaconnector') === 0) {
                        $cookie_name = isset($field_data[1]) ? $field_data[1] : $field_name . '__c';
                        $hidden_fields_html .= "<input type='hidden' name='{$field_name}' value='' class='ga-cookie-pair' data-cookie-name='{$cookie_name}' data-gaconnector-tracked='true'>";
                    }
                }
            }
        }
    }
    
    // Output the hidden fields
    echo $hidden_fields_html;
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
    $response = wp_remote_get('https://your-update-server.com/plugins/aspire-wpforms-action/info.json');
    if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
        return $checked_data;
    }
    
    $update_data = json_decode(wp_remote_retrieve_body($response));
    if (version_compare($checked_data->checked[$plugin_slug], $update_data->version, '<')) {
        $checked_data->response[$plugin_slug] = $update_data;
    }
    
    return $checked_data;
}
add_filter('pre_set_site_transient_update_plugins', 'aspire_check_for_plugin_update');

?>