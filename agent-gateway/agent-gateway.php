<?php
/**
 * Plugin Name: Agent Gateway
 * Description: Expose an agent-readable business profile and receive human-approved agent requests.
 * Version: 2.0.0
 * Author: Agent Gateway Registry
 */

if (!defined('ABSPATH')) {
    exit;
}

const AGW_VERSION = '2.0.0';
const AGW_OPTION = 'agw_settings';
const AGW_REQUESTS = 'agw_requests';
const AGW_RATES = 'agw_rates';
const AGW_ACTION_STATES = 'agw_action_states';
const AGW_MAX_ACTION_BODY_BYTES = 65536;

function agw_actions() {
    return array(
        'contact_request' => 'Contact request',
        'booking_request' => 'Booking request',
        'quote_request' => 'Quote request',
        'availability_request' => 'Availability request',
        'product_enquiry' => 'Product enquiry',
        'media_request' => 'Media request',
        'licensing_request' => 'Licensing request',
        'general_enquiry' => 'General enquiry',
    );
}

function agw_sensitive_actions() {
    return array();
}

function agw_default_settings() {
    return array(
        'registry_url' => 'http://srv1536342.hstgr.cloud:8081',
        'registration_key' => '',
        'registry_id' => '',
        'site_api_key' => '',
        'site_name' => get_bloginfo('name'),
        'description' => get_bloginfo('description'),
        'business_type' => 'other',
        'city' => '',
        'region' => '',
        'country' => '',
        'public_email' => get_option('admin_email'),
        'phone' => '',
        'preferred_contact_method' => 'agent_request',
        'offerings' => '',
        'enabled_actions' => array('contact_request', 'general_enquiry'),
        'require_action_api_key' => false,
        'action_api_key' => '',
        'anonymous_requests_per_hour' => 30,
        'verification_method' => 'hosted_file',
        'verification_challenge' => '',
        'verification_instruction' => '',
        'report_events' => true,
    );
}

function agw_settings() {
    $saved = get_option(AGW_OPTION, array());
    if (!is_array($saved)) {
        $saved = array();
    }
    return array_merge(agw_default_settings(), $saved);
}

function agw_activate() {
    add_option(AGW_OPTION, agw_default_settings());
    add_option(AGW_REQUESTS, array());
    add_option(AGW_RATES, array());
    add_option(AGW_ACTION_STATES, array());
    agw_add_rewrite_rule();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'agw_activate');

function agw_detected_systems() {
    $systems = array();
    if (class_exists('WC') || class_exists('WooCommerce')) {
        $systems['woocommerce'] = array(
            'name' => 'WooCommerce',
            'installed' => true,
            'actions' => array('product_enquiry'),
            'detection_result' => 'supported_by_agent_gateway',
        );
    }
    if (class_exists('Bookly\\Backend\\Modules') || class_exists('Bookly\\Lib\\Plugin') || class_exists('BooklyLib\\Plugin')) {
        $systems['bookly'] = array(
            'name' => 'Bookly',
            'installed' => true,
            'actions' => array('booking_request'),
            'detection_result' => 'custom_endpoint_required',
        );
    }
    if (class_exists('AmeliaBooking') || class_exists('Amelia\\Infrastructure\\WP\\AmeliaPlugin') || class_exists('AmeliaBooking\\Plugin')) {
        $systems['amelia'] = array(
            'name' => 'Amelia',
            'installed' => true,
            'actions' => array('booking_request'),
            'detection_result' => 'custom_endpoint_required',
        );
    }
    if (class_exists('Tribe__Events__Main')) {
        $systems['the_events_calendar'] = array(
            'name' => 'The Events Calendar',
            'installed' => true,
            'actions' => array('booking_request'),
            'detection_result' => 'custom_endpoint_required',
        );
    }
    if (class_exists('WPCF7') || function_exists('wpcf7')) {
        $systems['contact_form_7'] = array(
            'name' => 'Contact Form 7',
            'installed' => true,
            'actions' => array('contact_request', 'general_enquiry'),
            'detection_result' => 'supported_by_agent_gateway',
        );
    }
    if (class_exists('WPForms') || function_exists('wpforms')) {
        $systems['wpforms'] = array(
            'name' => 'WPForms',
            'installed' => true,
            'actions' => array('contact_request', 'quote_request', 'general_enquiry'),
            'detection_result' => 'supported_by_agent_gateway',
        );
    }
    if (class_exists('GFForms')) {
        $systems['gravity_forms'] = array(
            'name' => 'Gravity Forms',
            'installed' => true,
            'actions' => array('contact_request', 'quote_request', 'general_enquiry'),
            'detection_result' => 'supported_by_agent_gateway',
        );
    }
    return apply_filters('agw_detected_systems', $systems);
}

function agw_detected_actions() {
    $settings = agw_settings();
    $detected = array();
    foreach (agw_detected_systems() as $system_key => $system) {
        foreach ($system['actions'] as $action) {
            $detected[$system_key . ':' . $action] = array(
                'action_name' => $action,
                'detected_source' => $system_key,
                'system_name' => $system['name'],
                'detection_result' => $system['detection_result'],
                'endpoint_url' => rest_url('agent-gateway/v1/' . str_replace('_', '-', $action)),
                'requires_api_key' => $settings['require_action_api_key'] || in_array($action, agw_sensitive_actions(), true),
            );
        }
    }
    return array_values($detected);
}

function agw_action_states() {
    $states = get_option(AGW_ACTION_STATES, array());
    return is_array($states) ? $states : array();
}

function agw_state_for_action($action) {
    $states = agw_action_states();
    return isset($states[$action]) && is_array($states[$action]) ? $states[$action] : array(
        'action_name' => $action,
        'state' => 'detected',
        'detection_result' => 'not_yet_supported',
    );
}

function agw_store_registry_action_states($actions) {
    if (!is_array($actions)) {
        return;
    }
    $states = agw_action_states();
    foreach ($actions as $item) {
        if (!is_array($item) || empty($item['action_name'])) {
            continue;
        }
        $states[sanitize_key($item['action_name'])] = $item;
    }
    update_option(AGW_ACTION_STATES, $states, false);
}

function agw_support_label($detection_result) {
    switch ($detection_result) {
        case 'supported_by_agent_gateway':
            return 'Supported by Agent Gateway';
        case 'custom_endpoint_required':
            return 'Custom endpoint required';
        case 'not_yet_supported':
        default:
            return 'Not yet supported';
    }
}

function agw_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'agw_deactivate');

function agw_add_rewrite_rule() {
    add_rewrite_rule('^\.well-known/agent/?$', 'index.php?agw_agent_profile=1', 'top');
    add_rewrite_rule('^agent\.json$', 'index.php?agw_agent_profile=1', 'top');
    add_rewrite_rule('^\.well-known/agent-gateway-verification\.txt$', 'index.php?agw_agent_verify=1', 'top');
    add_rewrite_rule('^\.well-known/agent-gateway-verify/?$', 'index.php?agw_agent_verify=1', 'top');
}
add_action('init', 'agw_add_rewrite_rule');

function agw_query_vars($vars) {
    $vars[] = 'agw_agent_profile';
    $vars[] = 'agw_agent_verify';
    return $vars;
}
add_filter('query_vars', 'agw_query_vars');

function agw_template_redirect() {
    if (get_query_var('agw_agent_profile')) {
        wp_send_json(agw_profile());
    }
    if (get_query_var('agw_agent_verify')) {
        $settings = agw_settings();
        header('Content-Type: text/plain; charset=utf-8');
        echo esc_html($settings['verification_challenge']);
        exit;
    }
}
add_action('template_redirect', 'agw_template_redirect');

function agw_admin_menu() {
    add_menu_page('Agent Gateway', 'Agent Gateway', 'manage_options', 'agent-gateway', 'agw_settings_page', 'dashicons-networking');
    add_submenu_page('agent-gateway', 'Requests', 'Requests', 'manage_options', 'agent-gateway-requests', 'agw_requests_page');
}
add_action('admin_menu', 'agw_admin_menu');

function agw_sanitize_settings($input) {
    $current = agw_settings();
    $actions = array_keys(agw_actions());
    $enabled = isset($input['enabled_actions']) && is_array($input['enabled_actions']) ? array_values(array_intersect($actions, $input['enabled_actions'])) : array();
    return array(
        'registry_url' => esc_url_raw($input['registry_url'] ?? $current['registry_url']),
        'registration_key' => sanitize_text_field($input['registration_key'] ?? ''),
        'registry_id' => sanitize_text_field($input['registry_id'] ?? $current['registry_id']),
        'site_api_key' => sanitize_text_field($input['site_api_key'] ?? $current['site_api_key']),
        'site_name' => sanitize_text_field($input['site_name'] ?? ''),
        'description' => sanitize_textarea_field($input['description'] ?? ''),
        'business_type' => sanitize_key($input['business_type'] ?? 'other'),
        'city' => sanitize_text_field($input['city'] ?? ''),
        'region' => sanitize_text_field($input['region'] ?? ''),
        'country' => sanitize_text_field($input['country'] ?? ''),
        'public_email' => sanitize_email($input['public_email'] ?? ''),
        'phone' => sanitize_text_field($input['phone'] ?? ''),
        'preferred_contact_method' => sanitize_text_field($input['preferred_contact_method'] ?? 'agent_request'),
        'offerings' => sanitize_textarea_field($input['offerings'] ?? ''),
        'enabled_actions' => $enabled,
        'require_action_api_key' => !empty($input['require_action_api_key']),
        'action_api_key' => sanitize_text_field($input['action_api_key'] ?? ''),
        'anonymous_requests_per_hour' => max(1, intval($input['anonymous_requests_per_hour'] ?? 30)),
        'verification_method' => sanitize_key($input['verification_method'] ?? $current['verification_method']),
        'verification_challenge' => sanitize_text_field($input['verification_challenge'] ?? $current['verification_challenge']),
        'verification_instruction' => sanitize_text_field($input['verification_instruction'] ?? $current['verification_instruction']),
        'report_events' => !empty($input['report_events']),
    );
}

function agw_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    if (isset($_POST['agw_save_settings'])) {
        check_admin_referer('agw_save_settings');
        update_option(AGW_OPTION, agw_sanitize_settings($_POST));
        echo '<div class="updated"><p>Settings saved.</p></div>';
    }
    if (isset($_POST['agw_sync_registry'])) {
        check_admin_referer('agw_sync_registry');
        $result = agw_sync_registry();
        $message = $result['ok'] ? 'Registry sync complete.' : 'Registry sync failed: ' . $result['message'];
        echo '<div class="' . ($result['ok'] ? 'updated' : 'error') . '"><p>' . esc_html($message) . '</p></div>';
    }
    if (isset($_POST['agw_verify_registry'])) {
        check_admin_referer('agw_verify_registry');
        $result = agw_verify_registry();
        $message = $result['ok'] ? 'Domain verification passed.' : 'Domain verification failed: ' . $result['message'];
        echo '<div class="' . ($result['ok'] ? 'updated' : 'error') . '"><p>' . esc_html($message) . '</p></div>';
    }
    $settings = agw_settings();
    $categories = array('accommodation', 'restaurant', 'venue', 'shop', 'service', 'event', 'creative_business', 'other');
    ?>
    <div class="wrap">
        <h1>Agent Gateway Settings</h1>
        <form method="post">
            <?php wp_nonce_field('agw_save_settings'); ?>
            <h2>Registry</h2>
            <table class="form-table">
                <tr><th>Registry URL</th><td><input class="regular-text" name="registry_url" value="<?php echo esc_attr($settings['registry_url']); ?>"></td></tr>
                <tr><th>Registration key</th><td><input class="regular-text" name="registration_key" value="<?php echo esc_attr($settings['registration_key']); ?>"></td></tr>
                <tr><th>Registry ID</th><td><input class="regular-text" name="registry_id" value="<?php echo esc_attr($settings['registry_id']); ?>"></td></tr>
                <tr><th>Site API key</th><td><input class="regular-text" name="site_api_key" value="<?php echo esc_attr($settings['site_api_key']); ?>"></td></tr>
                <tr><th>Verification method</th><td><select name="verification_method">
                    <?php foreach (array('hosted_file', 'verification_endpoint', 'dns_txt') as $method) { echo '<option value="' . esc_attr($method) . '"' . selected($settings['verification_method'], $method, false) . '>' . esc_html($method) . '</option>'; } ?>
                </select></td></tr>
                <tr><th>Verification challenge</th><td><input class="regular-text" readonly value="<?php echo esc_attr($settings['verification_challenge']); ?>"><p class="description"><?php echo esc_html($settings['verification_instruction']); ?></p></td></tr>
            </table>
            <h2>Business Profile</h2>
            <table class="form-table">
                <tr><th>Site name</th><td><input class="regular-text" name="site_name" value="<?php echo esc_attr($settings['site_name']); ?>"></td></tr>
                <tr><th>Description</th><td><textarea class="large-text" rows="4" name="description"><?php echo esc_textarea($settings['description']); ?></textarea></td></tr>
                <tr><th>Business type</th><td><select name="business_type"><?php foreach ($categories as $category) { echo '<option value="' . esc_attr($category) . '"' . selected($settings['business_type'], $category, false) . '>' . esc_html($category) . '</option>'; } ?></select></td></tr>
                <tr><th>City</th><td><input class="regular-text" name="city" value="<?php echo esc_attr($settings['city']); ?>"></td></tr>
                <tr><th>Region</th><td><input class="regular-text" name="region" value="<?php echo esc_attr($settings['region']); ?>"></td></tr>
                <tr><th>Country</th><td><input class="regular-text" name="country" value="<?php echo esc_attr($settings['country']); ?>"></td></tr>
                <tr><th>Public email</th><td><input class="regular-text" name="public_email" value="<?php echo esc_attr($settings['public_email']); ?>"></td></tr>
                <tr><th>Phone</th><td><input class="regular-text" name="phone" value="<?php echo esc_attr($settings['phone']); ?>"></td></tr>
                <tr><th>Offerings</th><td><textarea class="large-text" rows="5" name="offerings"><?php echo esc_textarea($settings['offerings']); ?></textarea><p class="description">One offering per line.</p></td></tr>
            </table>
            <h2>Detected Systems</h2>
            <table class="widefat striped">
                <thead><tr><th>System</th><th>Detected</th><th>Available actions</th><th>Support level</th></tr></thead>
                <tbody>
                <?php foreach (agw_detected_systems() as $system) : ?>
                    <tr>
                        <td><?php echo esc_html($system['name']); ?></td>
                        <td>Yes</td>
                        <td><?php echo esc_html(implode(', ', $system['actions'])); ?></td>
                        <td><?php echo esc_html(agw_support_label($system['detection_result'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty(agw_detected_systems())) : ?>
                    <tr><td colspan="4">No supported systems detected yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <p class="description">Detected actions must be approved and verified in the Registry before this plugin will route them.</p>
            <h2>Supported Actions</h2>
            <?php foreach (agw_actions() as $key => $label) : ?>
                <label><input type="checkbox" name="enabled_actions[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $settings['enabled_actions'], true)); ?>> <?php echo esc_html($label); ?></label><br>
            <?php endforeach; ?>
            <h2>Action Security</h2>
            <table class="form-table">
                <tr><th>Require action API key</th><td><label><input type="checkbox" name="require_action_api_key" value="1" <?php checked($settings['require_action_api_key']); ?>> Require X-Agent-Gateway-Key on action endpoints</label></td></tr>
                <tr><th>Action API key</th><td><input class="regular-text" name="action_api_key" value="<?php echo esc_attr($settings['action_api_key']); ?>"></td></tr>
                <tr><th>Anonymous requests per hour</th><td><input type="number" min="1" name="anonymous_requests_per_hour" value="<?php echo esc_attr($settings['anonymous_requests_per_hour']); ?>"></td></tr>
                <tr><th>Report request events</th><td><label><input type="checkbox" name="report_events" value="1" <?php checked($settings['report_events']); ?>> Send request status events to the Registry</label></td></tr>
            </table>
            <p><button class="button button-primary" name="agw_save_settings" value="1">Save Settings</button></p>
        </form>
        <form method="post">
            <?php wp_nonce_field('agw_sync_registry'); ?>
            <p><button class="button" name="agw_sync_registry" value="1">Sync with Registry</button></p>
        </form>
        <form method="post">
            <?php wp_nonce_field('agw_verify_registry'); ?>
            <p><button class="button" name="agw_verify_registry" value="1">Verify Domain Now</button></p>
        </form>
    </div>
    <?php
}

function agw_profile() {
    $settings = agw_settings();
    $site_url = home_url();
    $offerings = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $settings['offerings']))));
    $capabilities = array('read_profile' => true);
    foreach (array_keys(agw_actions()) as $action) {
        $capabilities[$action] = in_array($action, $settings['enabled_actions'], true);
    }
    $actions = array();
    foreach ($settings['enabled_actions'] as $action) {
        $state = agw_state_for_action($action);
        $route = str_replace('_', '-', $action);
        $actions[] = array(
            'name' => $action,
            'method' => 'POST',
            'endpoint' => rest_url('agent-gateway/v1/' . $route),
            'requires_api_key' => (bool) ($settings['require_action_api_key'] || in_array($action, agw_sensitive_actions(), true)),
            'human_approval_required' => true,
            'state' => $state['state'] ?? 'detected',
            'detection_result' => $state['detection_result'] ?? 'not_yet_supported',
            'confirmation_policy' => 'No commercial action is confirmed until manually approved by the website owner.',
        );
    }
    return array(
        'agent_gateway_version' => AGW_VERSION,
        'business_name' => $settings['site_name'],
        'site_name' => $settings['site_name'],
        'website_url' => $site_url,
        'site_url' => $site_url,
        'registry_id' => $settings['registry_id'],
        'business_type' => $settings['business_type'],
        'description' => $settings['description'],
        'location' => array('city' => $settings['city'], 'region' => $settings['region'], 'country' => $settings['country']),
        'contact' => array('public_email' => $settings['public_email'], 'phone' => $settings['phone'], 'preferred_contact_method' => $settings['preferred_contact_method']),
        'offerings' => $offerings,
        'capabilities' => $capabilities,
        'supported_actions' => array_values($settings['enabled_actions']),
        'detected_actions' => agw_detected_actions(),
        'action_states' => agw_action_states(),
        'actions' => $actions,
        'rate_limits' => array('anonymous_requests_per_hour' => intval($settings['anonymous_requests_per_hour']), 'authenticated_requests_per_hour' => 300),
        'human_approval_policy' => 'All commercial requests are stored as pending owner review. No booking, payment, reservation, purchase, or legal commitment is automatically confirmed.',
    );
}

function agw_register_rest_routes() {
    register_rest_route('agent-gateway/v1', '/profile', array('methods' => 'GET', 'callback' => function () { return rest_ensure_response(agw_profile()); }, 'permission_callback' => '__return_true'));
    register_rest_route('agent-gateway/v1', '/request/(?P<id>[a-zA-Z0-9_-]+)', array(
        'methods' => 'GET',
        'callback' => 'agw_get_request_status',
        'permission_callback' => '__return_true',
    ));
    foreach (array_keys(agw_actions()) as $action) {
        register_rest_route('agent-gateway/v1', '/' . str_replace('_', '-', $action), array(
            'methods' => 'POST',
            'callback' => function ($request) use ($action) { return agw_handle_action($request, $action); },
            'permission_callback' => '__return_true',
        ));
    }
}
add_action('rest_api_init', 'agw_register_rest_routes');

function agw_get_request_status($request) {
    $request_id = sanitize_text_field($request['id'] ?? '');
    $requests = get_option(AGW_REQUESTS, array());
    if (!is_array($requests)) {
        $requests = array();
    }

    foreach ($requests as $item) {
        if (($item['id'] ?? '') !== $request_id) {
            continue;
        }

        $raw_status = sanitize_key($item['status'] ?? 'pending_owner_approval');
        $payload = json_decode($item['payload'] ?? '{}', true);
        if (!is_array($payload)) {
            $payload = array();
        }
        $owner_response = sanitize_textarea_field($item['internal_notes'] ?? '');

        return rest_ensure_response(array(
            'request_id' => $item['id'],
            'status' => agw_public_request_status($raw_status),
            'action_type' => sanitize_key($item['type'] ?? ''),
            'created_at' => sanitize_text_field($item['created_at'] ?? ''),
            'updated_at' => sanitize_text_field($item['updated_at'] ?? ''),
            'owner_response' => $owner_response,
            'payload' => $payload,
            'metadata' => array(
                'raw_status' => $raw_status,
                'is_confirmation' => false,
                'confirmation_type' => 'none',
                'human_approval_required' => true,
                'owner_response' => $owner_response,
            ),
        ));
    }

    return new WP_Error('agw_request_not_found', 'Request not found.', array('status' => 404));
}

function agw_public_request_status($status) {
    switch ($status) {
        case 'approved':
            return 'approved';
        case 'completed':
            return 'completed';
        case 'rejected':
        case 'spam':
            return 'rejected';
        case 'pending_owner_approval':
        default:
            return 'pending';
    }
}

function agw_rate_limited($ip) {
    $settings = agw_settings();
    $limit = intval($settings['anonymous_requests_per_hour']);
    $rates = get_option(AGW_RATES, array());
    $now = time();
    $bucket = isset($rates[$ip]) && is_array($rates[$ip]) ? $rates[$ip] : array();
    $bucket = array_values(array_filter($bucket, function ($time) use ($now) { return ($now - intval($time)) < HOUR_IN_SECONDS; }));
    $bucket[] = $now;
    $rates[$ip] = $bucket;
    update_option(AGW_RATES, $rates, false);
    return count($bucket) > $limit;
}

function agw_handle_action($request, $action) {
    $settings = agw_settings();
    $content_length = intval($request->get_header('content-length'));
    if ($content_length > AGW_MAX_ACTION_BODY_BYTES) {
        return new WP_Error('agw_payload_too_large', 'Payload too large.', array('status' => 413));
    }
    if (!in_array($action, $settings['enabled_actions'], true)) {
        return new WP_Error('agw_action_disabled', 'This action is not enabled for this website.', array('status' => 403));
    }
    $state = agw_state_for_action($action);
    if (($state['state'] ?? 'detected') !== 'verified') {
        return new WP_Error('agw_action_not_verified', 'This action has not been verified by Agent Gateway.', array('status' => 403));
    }
    $requires_key = $settings['require_action_api_key'] || in_array($action, agw_sensitive_actions(), true);
    if ($requires_key) {
        if (empty($settings['action_api_key'])) {
            return new WP_Error('agw_action_key_required', 'Sensitive actions require an action API key.', array('status' => 403));
        }
        $provided = $request->get_header('X-Agent-Gateway-Key');
        if (!$provided || !hash_equals($settings['action_api_key'], $provided)) {
            return new WP_Error('agw_invalid_api_key', 'Invalid action API key.', array('status' => 401));
        }
    }
    $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (agw_rate_limited($ip)) {
        return new WP_Error('agw_rate_limited', 'Rate limit exceeded.', array('status' => 429));
    }
    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = array();
    }
    $payload_json = wp_json_encode($params);
    if (strlen($payload_json) > AGW_MAX_ACTION_BODY_BYTES) {
        return new WP_Error('agw_payload_too_large', 'Payload too large.', array('status' => 413));
    }
    $item = array(
        'id' => wp_generate_uuid4(),
        'type' => $action,
        'status' => 'pending_owner_approval',
        'name' => sanitize_text_field($params['name'] ?? ''),
        'email' => sanitize_email($params['email'] ?? ''),
        'message' => sanitize_textarea_field($params['message'] ?? ''),
        'internal_notes' => '',
        'payload' => $payload_json,
        'ip' => $ip,
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    );
    $requests = get_option(AGW_REQUESTS, array());
    if (!is_array($requests)) {
        $requests = array();
    }
    array_unshift($requests, $item);
    update_option(AGW_REQUESTS, array_slice($requests, 0, 500), false);
    agw_report_request_event($item['id'], $action, 'request_received', array('source' => 'wordpress_plugin'));
    return rest_ensure_response(array(
        'request_id' => $item['id'],
        'status' => 'pending_owner_approval',
        'is_confirmation' => false,
        'confirmation_type' => 'none',
        'message' => 'Request received. No booking or transaction has been confirmed.',
    ));
}

function agw_update_request_status($request_id, $status, $notes = '', $source = 'wordpress_admin') {
    $request_id = sanitize_text_field($request_id);
    $status = sanitize_key($status);
    $notes = sanitize_textarea_field($notes);
    if (!in_array($status, array('pending_owner_approval', 'approved', 'rejected', 'completed', 'spam'), true)) {
        return new WP_Error('agw_invalid_request_status', 'Invalid request status.', array('status' => 400));
    }

    $requests = get_option(AGW_REQUESTS, array());
    if (!is_array($requests)) {
        $requests = array();
    }

    $updated_item = null;
    foreach ($requests as &$item) {
        if ((string) ($item['id'] ?? '') !== $request_id) {
            continue;
        }
        $item['status'] = $status;
        $item['internal_notes'] = $notes;
        $item['updated_at'] = current_time('mysql');
        $updated_item = $item;
        break;
    }
    unset($item);

    if (!$updated_item) {
        return new WP_Error('agw_request_not_found', 'Request not found.', array('status' => 404));
    }

    update_option(AGW_REQUESTS, $requests, false);
    wp_cache_delete(AGW_REQUESTS, 'options');
    agw_report_request_event($request_id, $updated_item['type'] ?? '', agw_event_for_status($status), array('source' => $source));

    return $updated_item;
}

function agw_requests_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    $requests = get_option(AGW_REQUESTS, array());
    if (!is_array($requests)) {
        $requests = array();
    }
    if (isset($_POST['agw_request_id'], $_POST['agw_request_status'])) {
        check_admin_referer('agw_update_request');
        $result = agw_update_request_status($_POST['agw_request_id'], $_POST['agw_request_status'], $_POST['agw_internal_notes'] ?? '');
        if (is_wp_error($result)) {
            echo '<div class="error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
        } else {
            $requests = get_option(AGW_REQUESTS, array());
            if (!is_array($requests)) {
                $requests = array();
            }
            echo '<div class="updated"><p>Request updated.</p></div>';
        }
    }
    ?>
    <div class="wrap">
        <h1>Agent Requests</h1>
        <p>Approving a request is a manual business decision. The plugin does not confirm payments, bookings, contracts, or legal commitments automatically.</p>
        <table class="widefat striped">
            <thead><tr><th>Created</th><th>Request ID</th><th>Type</th><th>Status</th><th>Name</th><th>Email</th><th>Message</th><th>Notes</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($requests as $item) : ?>
                <tr>
                    <td><?php echo esc_html($item['created_at']); ?></td>
                    <td><code><?php echo esc_html($item['id']); ?></code></td>
                    <td><?php echo esc_html($item['type']); ?></td>
                    <td><?php echo esc_html($item['status']); ?></td>
                    <td><?php echo esc_html($item['name']); ?></td>
                    <td><?php echo esc_html($item['email']); ?></td>
                    <td><?php echo esc_html($item['message']); ?></td>
                    <td><?php echo esc_html($item['internal_notes'] ?? ''); ?></td>
                    <td>
                        <form method="post">
                            <?php wp_nonce_field('agw_update_request'); ?>
                            <input type="hidden" name="agw_request_id" value="<?php echo esc_attr($item['id']); ?>">
                            <select name="agw_request_status">
                                <option value="pending_owner_approval" <?php selected($item['status'], 'pending_owner_approval'); ?>>pending_owner_approval</option>
                                <option value="approved" <?php selected($item['status'], 'approved'); ?>>approved</option>
                                <option value="rejected" <?php selected($item['status'], 'rejected'); ?>>rejected</option>
                                <option value="completed" <?php selected($item['status'], 'completed'); ?>>completed</option>
                                <option value="spam" <?php selected($item['status'], 'spam'); ?>>spam</option>
                            </select>
                            <textarea name="agw_internal_notes" rows="2" class="large-text"><?php echo esc_textarea($item['internal_notes'] ?? ''); ?></textarea>
                            <button class="button">Update</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function agw_sync_registry() {
    $settings = agw_settings();
    $registry_url = rtrim($settings['registry_url'], '/');
    $profile = agw_profile();
    $is_update = !empty($settings['registry_id']) && !empty($settings['site_api_key']);
    $endpoint = $registry_url . ($is_update ? '/api/v1/update' : '/api/v1/register');
    $body = $is_update
        ? array('registry_id' => $settings['registry_id'], 'profile' => $profile)
        : array('profile' => $profile, 'verification_method' => $settings['verification_method']);
    $headers = array('Content-Type' => 'application/json');
    if ($is_update) {
        $headers['X-Site-Api-Key'] = $settings['site_api_key'];
    } else {
        $headers['X-Registry-Key'] = $settings['registration_key'];
    }
    $response = wp_remote_post($endpoint, array('timeout' => 20, 'headers' => $headers, 'body' => wp_json_encode($body)));
    if (is_wp_error($response)) {
        return array('ok' => false, 'message' => $response->get_error_message());
    }
    $code = wp_remote_retrieve_response_code($response);
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if ($code < 200 || $code >= 300 || !is_array($data)) {
        return array('ok' => false, 'message' => 'Unexpected registry response.');
    }
    if (!empty($data['registry_id'])) {
        $settings['registry_id'] = sanitize_text_field($data['registry_id']);
    }
    if (!empty($data['site_api_key'])) {
        $settings['site_api_key'] = sanitize_text_field($data['site_api_key']);
    }
    if (!empty($data['verification_challenge'])) {
        $settings['verification_challenge'] = sanitize_text_field($data['verification_challenge']);
    }
    if (!empty($data['verification_instruction'])) {
        $settings['verification_instruction'] = sanitize_text_field($data['verification_instruction']);
    }
    update_option(AGW_OPTION, $settings);
    agw_sync_detected_actions_to_registry();
    return array('ok' => true, 'message' => 'Synced');
}

function agw_sync_detected_actions_to_registry() {
    $settings = agw_settings();
    if (empty($settings['registry_id']) || empty($settings['site_api_key'])) {
        return array('ok' => false, 'message' => 'Missing registry ID or site API key.');
    }
    $registry_url = rtrim($settings['registry_url'], '/');
    $response = wp_remote_post($registry_url . '/api/v1/actions/detect', array(
        'timeout' => 20,
        'headers' => array('Content-Type' => 'application/json', 'X-Site-Api-Key' => $settings['site_api_key']),
        'body' => wp_json_encode(array(
            'registry_id' => $settings['registry_id'],
            'detected_actions' => agw_detected_actions(),
            'plugin_version' => AGW_VERSION,
        )),
    ));
    if (is_wp_error($response)) {
        return array('ok' => false, 'message' => $response->get_error_message());
    }
    $code = wp_remote_retrieve_response_code($response);
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if ($code >= 200 && $code < 300 && is_array($data) && !empty($data['actions'])) {
        agw_store_registry_action_states($data['actions']);
        return array('ok' => true, 'message' => 'Detected actions synced.');
    }
    return array('ok' => false, 'message' => 'Unexpected action detection response.');
}

function agw_verify_registry() {
    $settings = agw_settings();
    if (empty($settings['registry_id']) || empty($settings['site_api_key'])) {
        return array('ok' => false, 'message' => 'Sync with the Registry first.');
    }
    $registry_url = rtrim($settings['registry_url'], '/');
    $body = array('registry_id' => $settings['registry_id'], 'method' => $settings['verification_method']);
    $response = wp_remote_post($registry_url . '/api/v1/verify', array(
        'timeout' => 20,
        'headers' => array('Content-Type' => 'application/json', 'X-Site-Api-Key' => $settings['site_api_key']),
        'body' => wp_json_encode($body),
    ));
    if (is_wp_error($response)) {
        return array('ok' => false, 'message' => $response->get_error_message());
    }
    $code = wp_remote_retrieve_response_code($response);
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if ($code < 200 || $code >= 300 || !is_array($data)) {
        return array('ok' => false, 'message' => 'Unexpected registry response.');
    }
    return array('ok' => !empty($data['ok']), 'message' => sanitize_text_field($data['verification_status'] ?? 'unknown'));
}

function agw_event_for_status($status) {
    switch ($status) {
        case 'approved':
            return 'request_approved';
        case 'rejected':
            return 'request_rejected';
        case 'completed':
            return 'request_completed';
        case 'spam':
            return 'request_marked_spam';
        case 'pending_owner_approval':
        default:
            return 'request_viewed';
    }
}

function agw_report_request_event($request_id, $action_type, $event_type, $metadata = array()) {
    $settings = agw_settings();
    if (empty($settings['report_events']) || empty($settings['registry_id']) || empty($settings['site_api_key'])) {
        return;
    }
    $registry_url = rtrim($settings['registry_url'], '/');
    wp_remote_post($registry_url . '/api/v1/request-events', array(
        'timeout' => 5,
        'blocking' => false,
        'headers' => array('Content-Type' => 'application/json', 'X-Site-Api-Key' => $settings['site_api_key']),
        'body' => wp_json_encode(array(
            'registry_id' => $settings['registry_id'],
            'request_id' => $request_id,
            'event_type' => $event_type,
            'action_type' => $action_type,
            'metadata' => $metadata,
        )),
    ));
}
