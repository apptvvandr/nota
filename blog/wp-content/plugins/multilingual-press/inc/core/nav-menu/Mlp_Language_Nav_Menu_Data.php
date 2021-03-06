<?php
/**
 * Data read and write for backend nav menu management.
 *
 * @version 2014.10.10
 * @author  Inpsyde GmbH, toscho
 * @license GPL
 */
class Mlp_Language_Nav_Menu_Data
	implements Mlp_Nav_Menu_Selector_Data_Interface {

	/**
	 * @type string
	 */
	private $meta_key;

	/**
	 * Button id.
	 *
	 * @type string
	 */
	private $button_id = 'mlp_language';

	/**
	 * @type string
	 */
	private $handle;

	/**
	 * @type Inpsyde_Nonce_Validator_Interface
	 */
	private $nonce;

	/**
	 * @type Mlp_Assets_Interface
	 */
	private $assets;

	/**
	 * Constructor
	 *
	 * @param string                            $handle
	 * @param string                            $meta_key
	 * @param Inpsyde_Nonce_Validator_Interface $nonce
	 * @param Mlp_Assets_Interface              $assets
	 */
	function __construct(                 $handle,
		                                  $meta_key,
		Inpsyde_Nonce_Validator_Interface $nonce,
		Mlp_Assets_Interface              $assets
	) {
		$this->handle   = $handle;
		$this->meta_key = $meta_key;
		$this->nonce    = $nonce;
		$this->assets   = $assets;
	}

	/**
	 * @return array
	 */
	public function get_list() {

		return mlp_get_available_languages_titles( TRUE );
	}

	/**
	 * @return string
	 */
	public function get_list_id() {

		return $this->handle . '_checklist';
	}

	/**
	 * @return string
	 */
	public function get_button_id() {

		return $this->button_id;
	}

	/**
	 * @return bool
	 */
	public function has_menu() {

		return ! empty ( $GLOBALS['nav_menu_selected_id'] );
	}

	/**
	 * @return void
	 */
	public function register_script() {

		$this->assets->provide( array ( 'mlp_backend_js', 'mlp_backend_css' ) );
	}

	/**
	 * @param string $hook
	 * @return void
	 */
	public function load_script( $hook ) {

		if ( 'nav-menus.php' !== $hook )
			return;

		$data = array (
			'ajaxurl'         => admin_url( 'admin-ajax.php' ),
			'metabox_id'      => $this->handle,
			'metabox_list_id' => $this->get_list_id(),
			'action'          => $this->handle,
			'nonce'           => wp_create_nonce( $this->nonce->get_action() )
		);
		$data[ $this->nonce->get_name() ] = wp_create_nonce( $this->nonce->get_action() );
		$data[ 'nonce_name' ] = $this->nonce->get_name();

		wp_localize_script( 'mlp_backend_js', $this->handle, $data );
	}

	/**
	 * AJAX handler.
	 *
	 * Called by the view. The 'exit' is handled there.
	 *
	 * @return array
	 */
	public function get_ajax_menu_items() {

		if ( ! $this->is_allowed() )
			return array ();

		$titles = mlp_get_available_languages_titles( TRUE );

		return $this->prepare_menu_items( $titles );
	}

	/**
	 * Is the AJAX request allowed and should be processed?
	 * @return bool
	 */
	public function is_allowed() {

		if ( ! current_user_can( 'edit_theme_options' ) )
			return FALSE;

		if ( ! $this->nonce->is_valid() )
			return FALSE;

		return ! empty ( $_POST[ 'mlp_sites' ] );
	}

	/**
	 * @param  array $titles
	 * @return array
	 */
	private function prepare_menu_items( Array $titles ) {

		$menu_items = array ();

		foreach ( array_values( $_POST[ 'mlp_sites' ] ) as $blog_id ) {

			if ( ! $this->is_valid_blog_id( $titles, $blog_id ) )
				continue;

			$menu_item = $this->create_menu_item( $titles, $blog_id );

			if ( empty ( $menu_item->ID ) )
				continue;

			$menu_items[] = $this->set_menu_item_meta( $menu_item, $blog_id );
		}

		return $menu_items;
	}

	/**
	 * Check if a blog id is for a linked, existing blog.
	 *
	 * @param array $titles
	 * @param int   $blog_id
	 * @return bool
	 */
	private function is_valid_blog_id( Array $titles, $blog_id ) {

		return isset ( $titles[ $blog_id ] ) && blog_exists( $blog_id );
	}

	/**
	 * Insert item into database.
	 *
	 * @param $titles
	 * @param $blog_id
	 * @return null|WP_Post
	 */
	private function create_menu_item( $titles, $blog_id ) {

		$menu_item_data = array (
			'menu-item-title'      => esc_attr( $titles[ $blog_id ] ),
			'menu-item-type'       => 'language',
			'menu-item-object'     => 'custom',
			'menu-item-url'        => get_home_url( $blog_id, '/' ),
			'menu_item-type-label' => esc_html__( 'Language', 'multilingualpress' ),
		);

		$item_id   = wp_update_nav_menu_item(
			$_POST[ 'menu' ],
			0,
			$menu_item_data
		);
		$menu_item = get_post( $item_id );

		return $menu_item;
	}

	/**
	 * Set item meta data.
	 *
	 * @param  WP_Post $menu_item
	 * @param  int     $blog_id
	 * @return WP_Post
	 */
	private function set_menu_item_meta( $menu_item, $blog_id ) {

		// don't show "(pending)" in ajax-added items
		$menu_item->post_type  = 'nav_menu_item';
		$menu_item->url        = get_home_url( $blog_id, '/' );
		$menu_item->object     = 'mlp_language';
		$menu_item->xfn        = 'alternate';
		$menu_item             = wp_setup_nav_menu_item( $menu_item );
		$menu_item->label      = $menu_item->title;
		// Replace the "Custom" in the management screen
		$menu_item->type_label = esc_html__( 'Language', 'multilingualpress' );
		$menu_item->classes[ ] = "blog-id-$blog_id";
		$menu_item->classes[ ] = "mlp-language-nav-item";
		$menu_item->url        = get_home_url( $blog_id, '/' );

		update_post_meta( $menu_item->ID, $this->meta_key, $blog_id );
		$url = esc_url_raw( get_home_url( $blog_id, '/' ) );
		update_post_meta( $menu_item->ID, '_menu_item_url', $url );

		return $menu_item;
	}
}