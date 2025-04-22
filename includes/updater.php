<?php
/**
 * Plugin Updater
 *
 * A simple class to enable updates directly from GitHub repositories.
 */
class AspirePluginUpdater {
    private $file;
    private $plugin;
    private $basename;
    private $active;
    private $username;
    private $repository;
    private $authorize_token;
    private $github_response;

    /**
     * Class constructor
     *
     * @param string $file          Plugin file path
     * @param string $username      GitHub username
     * @param string $repository    GitHub repository name
     * @param string $access_token  Optional GitHub access token for private repos
     */
    public function __construct($file, $username, $repository, $access_token = '') {
        $this->file = $file;
        $this->username = $username;
        $this->repository = $repository;
        $this->authorize_token = $access_token;

        add_action('admin_init', array($this, 'set_plugin_properties'));

        // Define the alternative API for updating checking
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));

        // Define the alternative response for information checking
        add_filter('plugins_api', array($this, 'get_plugin_info'), 10, 3);

        // Plugin Activation hook
        add_action('upgrader_process_complete', array($this, 'after_update'), 10, 2);
    }

    /**
     * Set plugin properties
     *
     * @return void
     */
    public function set_plugin_properties() {
        $this->plugin = get_plugin_data($this->file);
        $this->basename = plugin_basename($this->file);
        $this->active = is_plugin_active($this->basename);
    }

    /**
     * Get repository info
     *
     * @return array Repository info
     */
    private function get_repository_info() {
        if (!empty($this->github_response)) {
            return $this->github_response;
        }

        // Request GitHub API
        $request_uri = sprintf('https://api.github.com/repos/%s/%s/releases/latest', $this->username, $this->repository);
        
        // Include token if provided
        $request_args = array();
        if ($this->authorize_token) {
            $request_args['headers'] = array('Authorization' => 'token ' . $this->authorize_token);
        }

        // Get the response
        $response = wp_remote_get($request_uri, $request_args);

        // Check if response is valid
        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            return false;
        }

        // Parse the response body
        $response = json_decode(wp_remote_retrieve_body($response));
        
        // Check for valid response
        if (empty($response) || !is_object($response)) {
            return false;
        }

        // Check if a release was found
        if (empty($response->tag_name)) {
            return false;
        }

        // Check if there are assets
        if (empty($response->assets) || empty($response->assets[0])) {
            return false;
        }

        // Format the version number
        $response->tag_name = ltrim($response->tag_name, 'v');

        $this->github_response = $response;
        return $response;
    }

    /**
     * Check for updates
     *
     * @param object $transient Update transient
     *
     * @return object
     */
    public function check_update($transient) {
        // If we're not checking for updates, return the transient
        if (empty($transient->checked)) {
            return $transient;
        }

        // Get plugin & GitHub release information
        $version = $this->get_repository_info();
        
        // If there is no new version, return the transient
        if (false === $version || version_compare($this->plugin['Version'], $version->tag_name, '>=')) {
            return $transient;
        }

        // Populate update object
        $update = new stdClass();
        $update->slug = $this->basename;
        $update->plugin = $this->basename;
        $update->new_version = $version->tag_name;
        $update->url = $this->plugin['PluginURI'] ?? '';
        $update->package = $version->assets[0]->browser_download_url;
        $update->tested = get_bloginfo('version');
        
        // Add to transient
        $transient->response[$this->basename] = $update;

        return $transient;
    }

    /**
     * Get plugin information for the "View details" popup
     *
     * @param false|object|array $result The result object or array
     * @param string $action The API action being performed
     * @param object $args Plugin arguments
     *
     * @return object
     */
    public function get_plugin_info($result, $action, $args) {
        // Check if we're getting plugin information
        if ('plugin_information' !== $action) {
            return $result;
        }

        // Check if this is our plugin
        if (empty($args->slug) || $args->slug !== $this->basename) {
            return $result;
        }

        // Get GitHub release info
        $release = $this->get_repository_info();
        if (false === $release) {
            return $result;
        }

        // Populate the response
        $plugin_info = new stdClass();
        $plugin_info->name = $this->plugin['Name'];
        $plugin_info->slug = $this->basename;
        $plugin_info->version = $release->tag_name;
        $plugin_info->author = $this->plugin['AuthorName'] ?? '';
        $plugin_info->homepage = $this->plugin['PluginURI'] ?? '';
        $plugin_info->requires = '5.0';
        $plugin_info->tested = get_bloginfo('version');
        $plugin_info->downloaded = 0;
        $plugin_info->last_updated = $release->published_at;
        $plugin_info->sections = array(
            'description' => $release->body ?: $this->plugin['Description'],
            'changelog' => nl2br($release->body)
        );
        $plugin_info->download_link = $release->assets[0]->browser_download_url;

        return $plugin_info;
    }

    /**
     * Fires after a plugin has been updated
     *
     * @param object $upgrader_object Plugin_Upgrader instance
     * @param array  $options Array of bulk item update data
     *
     * @return void
     */
    public function after_update($upgrader_object, $options) {
        if ('update' === $options['action'] && 'plugin' === $options['type']) {
            // Just clean the cache when a new plugin version is installed
            delete_transient('aspire_plugin_' . $this->basename . '_github_data');
        }
    }
} 