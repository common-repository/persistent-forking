<?php
/*
Plugin Name: Persistent Forking
Author: Julian Gonggrijp, Digital Humanities Lab, Utrecht University
Author URI: http://digitalhumanities.wp.hum.uu.nl
License: MIT
License URI: http://opensource.org/licenses/MIT
*/

/**
* Namespace for all plugin functions.
*/
class Persistent_Forking {

	/**
	 * Register all the hooks that are required in order for the plugin to
	 * work. Called when the plugin is loaded.
	 *
	 * @see Persistent_Forking::add_fork_controls
	 * @see Persistent_Forking::create_family_taxonomies
	 * @see Persistent_Forking::create_forking_form
	 * @see Persistent_Forking::editor_metabox
	 * @see Persistent_Forking::save_family
	 * @see Persistent_Forking::enqueue_resources
	 * @global array $_REQUEST {
	 *     Used to determine whether a conditional hook should be registered.
	 *
	 *     @type string action If this field is set and equal to
	 *                         'persistent_fork', register a hook on init for
	 *                         preparing a fork editing form.
	 * }
	 */
	static function add_hooks( ) {
		add_filter(
			'the_content',
			array( 'Persistent_Forking', 'add_fork_controls' ),
			15
		);
		add_action(
			'init',
			array( 'Persistent_Forking', 'create_family_taxonomies' ),
			0
		);
		if ( isset( $_REQUEST['action'] )
				&& $_REQUEST['action'] === 'persistent_fork' ) {
			add_action(
				'init',
				array( 'Persistent_Forking', 'create_forking_form' )
			);
		}
		add_action(
			'add_meta_boxes',
			array( 'Persistent_Forking', 'editor_metabox' )
		);
		add_action(
			'save_post',
			array( 'Persistent_Forking', 'save_family' ),
			10,
			2
		);
		add_action(
			'wp_enqueue_scripts',
			array( 'Persistent_Forking', 'enqueue_resources' )
		);
	}

	/**
	 * Register the 'family' custom taxonomy with WP core.
	 *
	 * A family consists of a root post and all its descendants (forks and forks
	 * of descendants). The custom taxonomy makes it easy to find all members of
	 * a family.
	 *
	 * @see register_taxonomy
	 */
	static function create_family_taxonomies( ) {
		$labels = array(
			'name'              => _x( 'Families', 'taxonomy general name' ),
			'singular_name'     => _x( 'Family', 'taxonomy singular name' ),
			'search_items'      => __( 'Search Families' ),
			'all_items'         => __( 'All Families' ),
			'parent_item'       => __( 'Parent Family' ),
			'parent_item_colon' => __( 'Parent Family:' ),
			'edit_item'         => __( 'Edit Family' ),
			'update_item'       => __( 'Update Family' ),
			'add_new_item'      => __( 'Add New Family' ),
			'new_item_name'     => __( 'New Family Name' ),
			'menu_name'         => __( 'Family' ),
		);

		$args = array(
			'labels'            => $labels,
			'hierarchical'      => false,
			'show_ui'           => false,
			'show_in_nav_menus' => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'family' ),
			// 'meta_box_cb' => self::some_function,  // custom metabox callback
		);

		register_taxonomy( 'family', array( 'post' ), $args );
	}

	/**
	 * Enqueue scripts and stylesheets with WordPress core.
	 *
	 * @see wp_enqueue_script
	 * @see wp_enqueue_style
	 * @link http://visjs.org
	 */
	static function enqueue_resources( ) {
		wp_enqueue_script(
			'vis-js',
			plugins_url( '/js/vis.min.js', __FILE__ ),
			array(),
			'4.8.2',
			true
		);
		wp_enqueue_script(
			'persistfork-tax-visualisation',
			plugins_url( '/js/visualisation.js', __FILE__ ),
			array( 'jquery', 'vis-js' ),
			false,
			true
		);
		wp_enqueue_style(
			'vis-css',
			plugins_url( '/css/vis.min.css', __FILE__ ),
			array(),
			'4.8.2'
		);
		wp_enqueue_style(
			'persistfork-inset-style',
			plugins_url( '/css/inset.css', __FILE__ )
		);
	}

	/**
	 * Apply a given template, either sending the output directly to STDOUT or
	 * returning a string.
	 *
	 * @param string $template Name of the template to apply, excluding the .php
	 *                         suffix.
	 * @param string $how Mode of operation. If 'as_string', return the result
	 *                    of the applied template as a string. Otherwise, print
	 *                    the result to STDOUT.
	 * @param array $arguments Optional. Global variables for the template.
	 *                         Default empty.
	 * @return void|string If `$how` is 'as_string', a string with the template
	 *                     output. Otherwise, nothing is returned.
	 */
	static function render( $template, $how, $arguments = array() ) {
		$path = dirname( __FILE__ ) . "/templates/{$template}.php";
		extract( $arguments );  // really needed here
		if ( 'as_string' === $how ) {
			ob_start();
		}
		include $path;
		if ( 'as_string' === $how ) {
			$text = ob_get_contents();
			ob_end_clean();
			return $text;
		}
	}

	/**
	 * Prepend HTML with forking controls to the post content.
	 *
	 * @see Persistent_Forking::render
	 * @global WP_Post $post The post for which controls will be rendered.
	 *
	 * @param string $content Input filter state of the post HTML content.
	 * @return string Output filter state, unchanged or with controls prepended.
	 */
	static function add_fork_controls( $content ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return $content;
		}
		$post = $GLOBALS['post'];
		$post_id = $post->ID;
		if ( 'post' != $post->post_type ) {
			return $content;
		}
		$image_url = plugins_url( "/images/fork_icon.png", __FILE__ );
		$fork_box = self::render( 'public_fork_box', 'as_string', array(
			'post_id'   => $post_id,
			'image_url' => $image_url,
		) );
		return $fork_box . $content;
	}

	/**
	 * Create a new post (fork) based on the given parent and store the parent
	 * relationship.
	 *
	 * Prepends a tag to the title of the parent post in order to obtain the
	 * title of the fork. Saves the parent ID in a custom meta field. Otherwise,
	 * the fork is blank new post.
	 *
	 * @see wp_insert_post
	 * @see add_post_meta
	 * @see wp_die
	 * @global WP_Post|int $post Used as the parent if no parent argument is
	 *                           passed.
	 *
	 * @param int|WP_Post $parent_post Optional. ID of the post to be forked
	 *                                 from. Default current post.
	 * @param int|WP_Post $author Optional. Author of the future fork. Default
	 *                            current user.
	 * @return int|bool ID of the newly created post if successful, false
	 *                  otherwise. Will abort (wp_die) in case of insufficient
	 *                  privileges.
	 */
	static function fork( $parent_post = null, $author = null ) {
		global $post;
		if ( null == $parent_post ) {
			$parent_post = $post;
		}
		if ( ! is_object( $parent_post ) ) {
			$parent_post = get_post( $parent_post );
		}
		if ( ! $parent_post ) {
			return false;
		}
		$parent_id = $parent_post->ID;

		if ( null == $author ) $author = wp_get_current_user()->ID;
		if ( ! user_can( $author, 'edit_posts' ) ) {
			wp_die( __(
				'You are not allowed to create forks',
				'persistent-forking'
			) );
		}

		$fork = array(
			'post_author' => $author,
			'post_status' => 'draft',
			'post_title'  => '[fork] ' . $parent_post->post_title,
			'post_type'   => $parent_post->post_type,
		);
		$fork_id = wp_insert_post( $fork );
		if ( ! $fork_id ) {
			return false;
		}
		add_post_meta( $fork_id, '_persistfork-parent', $parent_id, true );

		return $fork_id;
	}

	/**
	 * Associate the given post with a family, if relevant.
	 *
	 * A post is associated with a family under the following conditions:
	 *
	 * - The post has been published.
	 * - The post has a parent.
	 *
	 * If the post is already associated with a family, this function is a
	 * no-op. Otherwise, the family of the parent is assigned. If the parent
	 * does not have a family yet (it is the root of the family), a new family
	 * is created first.
	 *
	 * @see Persistent_Forking::get_post_family
	 *
	 * @param int $post_id ID of the post that needs a family associated.
	 * @param WP_Post $post Complete data object of the same post.
	 */
	static function save_family( $post_id, $post ) {
		if ( 'publish' != $post->post_status ) {
			return;
		}
		$terms = wp_get_object_terms( $post_id, 'family' );
		$term = reset( $terms );
		if ( $term ) {
			return;
		}
		$parent_id = get_post_meta( $post_id, '_persistfork-parent', true );
		if ( ! $parent_id ) {
			return;
		}
		wp_add_object_terms(
			$post_id,
			self::get_post_family( $parent_id ),
			'family'
		);
	}

	/**
	 * Obtain family term ID of a post. Create family on the fly if necessary.
	 *
	 * @param int $post_id The ID of the post of which the family is requested.
	 * @return int The ID of the family term to which the post belongs.
	 */
	static function get_post_family( $post_id ) {
		$terms = wp_get_object_terms( $post_id, 'family' );
		$term = reset( $terms );
		if ( $term ) {
			return $term->term_id;
		} else {
			$post_title = get_post( $post_id )->post_title;
			$term = wp_insert_term( $post_title, 'family' );
			// The following loop exists to handle name collisions.
			$counter = 1;
			while ( is_object( $term ) && is_a( $term, 'WP_Error' ) ) {
				$term = wp_insert_term( $post_title . $counter, 'family' );
				++$counter;
			}
			wp_add_object_terms( $post_id, $term['term_id'], 'family' );
			return $term['term_id'];
		}
	}

	/**
	 * If possible, create a new fork and forward the user to the editing form.
	 *
	 * @see current_user_can
	 * @see wp_verify_nonce
	 * @see Persistent_Forking::fork
	 * @see wp_safe_redirect
	 * @global array $_REQUEST {
	 *     Request data, inspected to determine whether forking is possible.
	 *     
	 *     @type string nonce For security, should match nonce in session.
	 *     @type string post Required. ID of the parent post.
	 * }
	 */
	static function create_forking_form( ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'persistent_forking' ) ) {
			return;
		}
		$post_id = (isset( $_REQUEST['post'] )) ? get_post( (int) $_REQUEST['post'] ) : false;
		if ( empty( $post_id ) ) {
			return;
		}
		$fork_id = self::fork( $post_id );
		// Redirect to form
		$redirect = get_edit_post_link( $fork_id, 'redirect' );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Register the metabox with WordPress.
	 *
	 * @see Persistent_Forking::display_editor_metabox
	 * @see add_meta_box
	 */
	static function editor_metabox( ) {
		add_meta_box(
			'persistfork_info',	 // unique ID
			'Forking',	// box title
			array( 'Persistent_Forking', 'display_editor_metabox' ),  // callback
			'post'	// post type
		);
	}

	/**
	 * Render the metabox in the post editing form.
	 */
	static function display_editor_metabox( ) {
		self::render( 'admin_metabox', 'direct' );
	}
}

Persistent_Forking::add_hooks();

?>
