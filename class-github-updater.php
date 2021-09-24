<?php
/**
 * Updater class for plugin.
 *
 * @class   GitHub_Updater
 */

/**
 * Class GitHub_Updater
 *
 * @since 1.0.0
 *
 * @thanks https://github.com/rayman813/smashing-updater-plugin
 */
class GitHub_Updater {

	/**
	 * The plugin slug.
	 *
	 * @since 1.0.0
	 * @var string $plugin_filename Stores the plugin slug.
	 */
	private string $plugin_filename;

	/**
	 * The plugin data from main file header.
	 *
	 * @since 1.0.0
	 * @var array $plugin Stores the plugin data.
	 */
	private array $plugin;

	/**
	 * The plugin basename.
	 *
	 * @since 1.0.0
	 * @var string $basename Stores the plugin basename.
	 */
	private string $basename;

	/**
	 * Whether the plugin is active.
	 *
	 * @since 1.0.0
	 * @var bool $active Whether the plugin is active or not.
	 */
	private bool $active;

	/**
	 * GitHub's authorization token.
	 *
	 * @link https://github.com/settings/tokens
	 * @since 1.0.0
	 * @var string $authorize_token
	 */
	private string $authorize_token;

	/**
	 * Response from GitHub API.
	 *
	 * @since 1.0.0
	 * @var array $github_response Stores the GitHub response.
	 */
	private array $github_response = [];

	/**
	 * Constructor class
	 *
	 * Load hooks and set up properties' class.
	 *
	 * @param string $plugin_filename
	 *
	 * @since 1.0.0
	 *
	 * @see add_action
	 * @link https://developer.wordpress.org/reference/functions/add_action/
	 *
	 * @see add_filter
	 * @link https://developer.wordpress.org/reference/functions/add_filter/
	 */
	public function __construct( string $plugin_filename ) {
		$this->plugin_filename = $plugin_filename;
		$this->authorize_token = GITHUB_TOKEN;

		add_action( 'admin_init', [ $this, 'set_plugin_properties' ] );
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'modify_transient' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_popup' ], 10, 3 );
		add_filter( 'upgrader_post_install', [ $this, 'after_install' ], 10, 3 );

		// Add Authorization Token to download_package
		add_filter( 'upgrader_pre_download',
			function () {
				add_filter( 'http_request_args', [ $this, 'download_package' ], 15 );

				return false;
			}
		);
	}

	/**
	 * Setup data plugin properties.
	 *
	 * @since 1.0.0
	 *
	 * @see get_plugin_data
	 * @link https://developer.wordpress.org/reference/functions/get_plugin_data/
	 *
	 * @see plugin_basename
	 * @link https://developer.wordpress.org/reference/functions/plugin_basename/
	 *
	 * @see is_plugin_active
	 * @link https://developer.wordpress.org/reference/functions/is_plugin_active/
	 */
	public function set_plugin_properties(): void {
		$this->plugin   = get_plugin_data( $this->plugin_filename );
		$this->basename = plugin_basename( $this->plugin_filename );
		$this->active   = is_plugin_active( $this->basename );
	}

	/**
	 * Get GitHub repository info.
	 *
	 * @since 1.0.0
	 *
	 * @see wp_remote_retrieve_body
	 * @link https://developer.wordpress.org/reference/functions/wp_remote_retrieve_body/
	 *
	 * @see wp_remote_get
	 * @link https://developer.wordpress.org/reference/functions/wp_remote_get/
	 */
	private function get_repository_info(): void {
		if ( empty( $this->github_response ) ) {
			$args = [];

			$request_uri = sprintf(
				'https://api.github.com/repos/%s/%s/releases',
				GITHUB_USERNAME,
				GITHUB_REPOSITORY
			);

			if ( $this->authorize_token ) {
				$args['headers']['Authorization'] = "token {$this->authorize_token}";
			}

			$response = json_decode( wp_remote_retrieve_body( wp_remote_get( $request_uri, $args ) ), true );

			if ( is_array( $response ) ) {
				// Get the first item
				$response = current( $response );
			}

			if ( is_array( $response ) ) {
				$this->github_response = $response;
			}
		}
	}

	/**
	 * Put GitHub data in plugin's transient to know where to update it from.
	 *
	 * @param object $transient
	 *
	 * @return object
	 * @since 1.0.0
	 */
	public function modify_transient( object $transient ): object {
		if ( property_exists( $transient, 'checked' ) ) {
			if ( $checked = $transient->checked ) {
				$this->get_repository_info();

				if ( isset( $this->github_response['tag_name'] ) ) {
					$out_of_date = version_compare( $this->github_response['tag_name'], $checked[ $this->basename ], 'gt' );

					if ( $out_of_date ) {
						$new_files = $this->github_response['assets'][0]['url'];
						$slug      = current( explode( '/', $this->basename ) );
						$plugin    = [
							'url'         => $this->plugin['PluginURI'],
							'slug'        => $slug,
							'package'     => $new_files,
							'new_version' => $this->github_response['tag_name']
						];

						$transient->response[ $this->basename ] = (object) $plugin;
					}
				}
			}
		}

		return $transient;
	}

	/**
	 * @param array $result
	 * @param string $action
	 * @param array $args
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function plugin_popup( array $result, string $action, array $args ): array {
		if ( ! empty( $args->slug ) ) {
			if ( $args->slug == current( explode( '/', $this->basename ) ) ) {
				$this->get_repository_info();

				return [
					'name'              => $this->plugin['Name'],
					'slug'              => $this->basename,
					'requires'          => '5.5',
					'tested'            => '5.7',
//					'rating'            => '100.0',
//					'num_ratings'       => '10823',
//					'downloaded'        => '14249',
					'added'             => '2021-09-24',
					'version'           => $this->github_response['tag_name'],
					'author'            => $this->plugin['AuthorName'],
					'author_profile'    => $this->plugin['AuthorURI'],
					'last_updated'      => $this->github_response['published_at'],
					'homepage'          => $this->plugin['PluginURI'],
					'short_description' => $this->plugin['Description'],
					'sections'          => [
						'Description' => $this->plugin['Description'],
						'Updates'     => $this->github_response['body'],
						'Version'     => $this->plugin['Version'],
					],
					'download_link'     => $this->github_response['zipball_url'],
				];
			}
		}

		return $result;
	}

	/**
	 *
	 * @param array $args
	 *
	 * @return mixed
	 * @since 1.0.0
	 *
	 * @see remove_filter
	 * @link https://developer.wordpress.org/reference/functions/remove_filter/
	 *
	 * @see https://docs.github.com/en/rest/reference/repos#get-a-release-asset
	 */
	public function download_package( array $args ): array {
		if ( null !== $args['filename'] && $this->authorize_token ) {
			$args = array_merge( $args, [
				'headers' => [
					'Accept'        => 'application/octet-stream',
					'Authorization' => "token {$this->authorize_token}"
				],
			] );
		}
		remove_filter( 'http_request_args', [ $this, 'download_package' ] );

		return $args;
	}

	/**
	 * After installation, it moves plugin's file and reactivate the plugin.
	 *
	 * @param bool $response
	 * @param array $hook_extra
	 * @param array $result
	 *
	 * @return mixed
	 * @since 1.0.0
	 *
	 * @see plugin_dir_path
	 * @link https://developer.wordpress.org/reference/functions/plugin_dir_path/
	 *
	 * @see activate_plugin
	 * @link https://developer.wordpress.org/reference/functions/activate_plugin/
	 *
	 * @global object $wp_filesystem Used for file management.
	 */
	public function after_install( bool $response, array $hook_extra, array $result ): array {
		global $wp_filesystem;

		$install_directory = plugin_dir_path( $this->plugin_filename );
		$wp_filesystem->move( $result['destination'], $install_directory );
		$result['destination'] = $install_directory;

		if ( $this->active ) {
			activate_plugin( $this->basename );
		}

		return $result;
	}
}
