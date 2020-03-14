<?php

namespace Gonzo\MAB;

use WP_Query;

/**
 * Register action and filter hooks.
 *
 * @return void
 */
function bootstrap() {
	add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );

	add_action( 'init', __NAMESPACE__ . '\\register_announcement_cpt' );
	add_action( 'add_meta_boxes_announcement', __NAMESPACE__ . '\\add_meta_boxes' );
	add_action( 'save_post_announcement', __NAMESPACE__ . '\\save_postdata' );

	add_action( 'wp_body_open', __NAMESPACE__ . '\\display_announcement' );
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_assets' );
}

/**
 * Plugin initialization.
 *
 * @return void
 */
function init() {
	load_plugin_textdomain( 'mab', false, basename( dirname( __DIR__ ) ) . 'languages/' );
	add_filter( 'pll_get_post_types', __NAMESPACE__ . '\\add_announcements_to_pll', 10, 2 );
}

/*
 * Register announcement post type.
 *
 * @return void
 */
function register_announcement_cpt() {

	// Announcements
	register_post_type(
		'announcement',
		[
			'labels' => [
				'name' => __( 'Announcements' ),
				'singular_name' => __( 'Announcement' ),
			],
			'public' => false,
			'has_archive' => false,
			'show_ui' => true,
			'show_in_menu' => true,
			'menu_position' => 50,
			'show_in_rest' => false,
			'supports' => [ 'title', 'editor' ],
			'menu_icon' => 'dashicons-megaphone',
		]
	);
}

/**
 * Register meta boxes for announcement meta data.
 *
 * @param array $meta_boxes Meta boxes array to be filtered.
 * @return array Filtered meta boxes array with new metabox added.
 */
function add_meta_boxes() {
	add_meta_box(
		'announcement-meta',
		esc_html__( 'Schedule', 'map' ),
		__NAMESPACE__ . '\\announcement_meta_box',
		'announcement'
	);
}

function announcement_meta_box( $post ) {
	wp_nonce_field( plugin_basename( __FILE__ ), 'mab_isitme' );

	$end = date( 'Y-m-d', intval( get_post_meta( $post->ID, 'mab-end-date', true ) ) );
	$disable_close = ( get_post_meta( $post->ID, 'mab-disable-close', true ) === 'on' );

	?>
	<p>
		<label for="mab-end-date" style="display:inline-block;width:35%;">
			<?php esc_html_e( 'End', 'mab' ); ?>
		</label>
		<input type="date" id="mab-end-date" name="mab-end-date" value="<?php echo esc_attr( $end ); ?>" style="width:60%;" />
	</p>
	<p>
		<label for="mab-disable-close" style="display:inline-block;width:35%;">
			<?php esc_html_e( 'Disable close button', 'mab' ); ?>
		</label>
		<input type="checkbox" id="mab-disable-close" name="mab-disable-close" <?php checked( $disable_close ); ?> />
	</p>

	<?php

}

/**
 * Save post meta.
 *
 * @param $post_id Post ID.
 * @return void
 */
function save_postdata( $post_id ) {

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// Verify this came from the our screen and with proper authorization,
	// because save_post can be triggered at other times.
	if ( empty( $_POST['mab_isitme'] ) || ! wp_verify_nonce( wp_unslash( $_POST['mab_isitme'] ), plugin_basename( __FILE__ ) ) ) {
		return;
	}

	// Check permissions.
	if ( 'announcement' !== wp_unslash( $_POST['post_type'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// OK, we're authenticated: we need to find and save the data.
	$end = sanitize_text_field( wp_unslash( $_POST['mab-end-date'] ) );
	$end = strtotime( $end );
	if ( empty( $end ) ) {
		delete_post_meta( $post_id, 'mab-end-date' );
	} else {
		update_post_meta( $post_id, 'mab-end-date', $end );
	}

	if ( empty( $_POST['mab-disable-close'] ) ) {
		$disable_close = 'off';
	}
	else {
		$disable_close = ( sanitize_text_field( wp_unslash( $_POST['mab-disable-close'] ) ) === 'on' ) ? 'on' : 'off';
	}
	update_post_meta( $post_id, 'mab-disable-close', $disable_close );
}

/**
 * Render announcement above all page content.
 *
 * @return void
 */
function display_announcement() {

	global $wpdb;

	$today = time();
	$args = [
		'post_type' => 'announcement',
		'posts_per_page' => -1,
		'orderby' => 'date',
		'order' => 'DESC',
		'meta_query' => [
			'relation' => 'OR',
			[
				'key' => 'mab-end-date',
				'compare' => 'NOT EXISTS',
			],
			[
				'key' => 'mab-end-date',
				'value' => $today,
				'compare' => '>=',
				'type' => 'NUMERIC',
			],
		],
	];

	$query = new WP_Query( $args );
	$announcements = $query->posts;

	if ( empty( $announcements ) || is_wp_error( $announcements ) ) {
		return;
	}

	$message = '';
 	foreach ( $announcements as $announcement ) {
		$cookie_name = sprintf( 'announcement-%d', $announcement->ID );
		$disable_close = ( get_post_meta( $announcement->ID, 'mab-disable-close', true ) === 'on' );
		if ( ! empty( $_COOKIE[ $cookie_name ] ) && ! $disable_close ) {
			continue;
		}
		$message = apply_filters( 'the_content', $announcement->post_content );
		break;
	}

	if ( empty( $message ) ) {
		return;
	}

	?>
	<div id="announcements">
		<div class="wrapper">
			<div class="announcement-message">
				<?php
				echo wp_kses_post( $message );
				?>
			</div>
			<?php if ( ! $disable_close ) : ?>
				<button type="button" class="close" data-cookie-name="<?php echo esc_attr( $cookie_name ); ?>">
					<?php esc_html_e( 'Close', 'map' ); ?>
				</button>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * Enqueue assets for the frontend.
 *
 * @return void
 */
function enqueue_assets() {
	wp_enqueue_script(
		'mab-script',
		plugin_dir_url( __DIR__ ) . 'assets/announcements.js',
		[],
		filemtime(
			plugin_dir_path( __DIR__ ) . '/assets/announcements.js',
		),
		true
	);

	wp_enqueue_style(
		'mab-style',
		plugin_dir_url( __DIR__ ) . 'assets/announcements.css',
		[],
		filemtime(
			plugin_dir_path( __DIR__ ) . '/assets/announcements.css',
		)
	);
}

/**
 * Add announcements to translatable post types.
 *
 * @param array $post_types Translatable post types array.
 * @param bool $is_settings Is this the Polylang settings screen.
 * @return array
 */
function add_announcements_to_pll( $post_types, $is_settings ) {
	$post_types['announcement'] = 'announcement';
	return $post_types;
}
