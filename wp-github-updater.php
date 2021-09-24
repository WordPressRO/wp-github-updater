<?php
/**
 * Plugin name: WP GitHub Updater
 * Version: 1.0.0
 * Author: Vlăduț Ilie
 * Author URI: https://vladilie.ro
 */

namespace WP_GitHub_Updater;

use GitHub_Updater;

defined( 'ABSPATH' ) || exit;

require_once dirname( __FILE__ ) . '/class-github-updater.php';

define( 'GITHUB_USERNAME', 'WordPressRO' );
define( 'GITHUB_REPOSITORY', 'wp-github-updater' );
define( 'GITHUB_TOKEN', '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' );

new GitHub_Updater( __FILE__ );