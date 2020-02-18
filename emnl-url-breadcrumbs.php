<?php
/**
 * Plugin Name: URL Breadcrumbs
 * Description: This plugin creates breadcrumbs where its items are based on the current URL structure (and NOT based on permalinks, e.g. like Yoast SEO does). It does two things: 1) Adds schema.org breadcrumbs to the <head> for search engines, and 2) Creates the function emnl_breadcrumbs() which you can use in your theme templates to output visual breadcrumbs to the user.
 * Version: 	2.0
 * Author: 		Erik Molenaar
 * Author URI: 	https://www.erikmolenaar.nl
 */


// Exit if accessed directly
if ( ! defined ( 'ABSPATH' ) ) exit;

/**
 * This class checks conditions for, and returns breadcrumbs at the proper locations.
 */
class EMNL_URL_Breadcrumbs {

	/**
	* Holds class instance.
	*/
	private static $instance;

	/**
	 * Property holding the url array.
	 * @var array
	 */
	private $url_array;

	/**
	 * Class constructor.
	 */
	private function __construct() {
		add_action ( 'wp_head', array ( $this, 'check_conditions_and_return_schema_and_html' ), 99 );
    }

	/**
	 * Checks conditions and returns all breadcrumbs at the proper locations.
	 */
	public function check_conditions_and_return_schema_and_html() {

		// Only work in Categories, Tags, single Posts and the Page containing Cisco Course Categories
		if ( ! ( is_category() || is_tag() || is_single() || is_page ( 'Cisco Courses' ) ) ) { return; }

		// Get the URL items
		$this->url_array = $this->create_breadcrumbs_array_from_url();

		// Check if there are valid URL items. If not, stop here
		if ( ! $this->url_array ) { return; }

		// Return the schema in <head>
		$this->return_breadcrumbs_schema();

		// Return the HTML at the action hook (located in the theme <header> template)
		add_action ( 'emnl_url_breadcrumbs_html', array ( $this,'return_breadcrumbs_html' ) );

	}

	/**
	 * Returns breadcrumbs in schema <script>.
	 */
	private function return_breadcrumbs_schema() {

		// Open JSON-LD snippet 
		$snippet = 
		'<script type="application/ld+json" class="emnl-schema-breadcrumbs">
			{"@context": "https://schema.org",
				"@type": "BreadcrumbList",
				"itemListElement": [';
		
		// Get array and loop thru all items adding them as itemListElements
		$url_array = $this->url_array;
		$last_key = array_key_last ( $url_array );
		foreach ( $url_array as $key => $url_part ) {

			$name = $url_part['name'];
			$url = $url_part['url'];

			$snippet .=
			'{
				"@type": "ListItem",
				"position": ' . $key . ',
				"name": "' . $name . '",
				"item": "' . $url . '"
			}';

			// Add a comma for next entry (if not the last one)
			if ( $key !== $last_key ) {
				$snippet .= ',';
			}

		}

		// Close JSON-LD snippet
		$snippet .=
				']
			}
		</script>';

		// Compress the HTML strip out all the line breaks and spaces
		// Note: comment this during debugging for a human readable snippet!
		$snippet = str_replace ( array ( "\t", "\r\n","\r","\n" ), '', $snippet );
		$snippet .= "\n";

		// Echo the snippet
		echo $snippet;

	}

	/**
	 * Returns breadcrumbs visible to user in <div>.
	 */
	public function return_breadcrumbs_html() {

		// Start of breadcrumbs sentence
		$sentence = 'You are here: ';
		
		// Processing every URL part
		$breadcrumbs_amount = 0;
		$url_array = $this->url_array;

		foreach ( $url_array as $key => $url_part ) {

			$type = $url_part['type'];
			$name = $url_part['name'];
			$url = $url_part['url'];
			$active = $url_part['active'];
			
			// Add all items as breadcrumbs
			// Note: except for 'single' items (to prevent the single title to be shown twice to user; in the breadcrumbs AND in the header)
			if ( $type !== 'single' ) {

				// Add seperator between items
				if ( $key !== array_key_first ( $url_array ) ) {
					$sentence .= ' » ';
				}

				// Only add link if item is currently viewed
				if ( ! $active ) {
					$sentence .= '<a href="' . $url . '">' . $name . '</a>';
				} else {
					$sentence .= $name;
				}

			}

		}
		
		// Wrapping breadcrumbs in a <div>
		$breadcrumbs =  '<div class="breadcrumbs" style="font-size: 12px;">' . $sentence . '</div>';
		
		// Echo the breadcrumbs
		echo $breadcrumbs;

	}

	/**
	 * Returns array of all URL breadcrumbs (together with 'type', 'name', 'url' and 'active').
	 * @return array $breadcrumbs_all
	 */
	private function create_breadcrumbs_array_from_url() {

		// Get URL and place all slugs in an array
		$base_url = get_site_url();
		$full_url = 'http' . ( isset ( $_SERVER['HTTPS'] ) ? 's' : '' ) . '://' . "{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
		$full_url = rtrim ( $full_url, '/' );
		$url_wo_base = str_replace ( $base_url, '', $full_url );
		$url_wo_base = ltrim ( $url_wo_base, '/' );
		$url_wo_base = rtrim ( $url_wo_base, '/' );
		$url_array = explode ( '/', $url_wo_base );

		// Safe guard for improper structured URLs (which contain too many slugs and is probably the result of a server error)
		if ( count ( $url_array ) > 5 ) { return false; }

		// Sanitzing: removing word CATEGORY from array (if /category/ is harcoded in the permalink)
		if ( ( $key = array_search ( 'category', $url_array ) ) !== false ) { unset ( $url_array[$key] ); }

		// Sanitzing: Removing word TAG slug from array (if /tag/ is harcoded in the permalink)
		if ( ( $key = array_search ( 'tag', $url_array ) ) !== false ) { unset ( $url_array[$key] ); }

		// Set 1st item of breadcrumbs (for base URL)
		$breadcrumbs[0]['type'] = 'base';
		$breadcrumbs[0]['name'] = 'Home';
		$breadcrumbs[0]['url'] = $base_url;
		$breadcrumbs[0]['active'] = false;

		// Set last item of breadcrumbs (if 'single' like a post)
		$last_breadcrumb = array();
		if ( is_single() ) {

			$last_breadcrumb[0]['type'] = 'single';
			$last_breadcrumb[0]['name'] = esc_html ( get_the_title() );
			$last_breadcrumb[0]['url'] = $full_url;
			$last_breadcrumb[0]['active'] = true;

			// Remove this last element from the main array (so it won't be processed twice)
			array_pop ( $url_array );

		}

		// Process other slugs in the URL
		foreach ( $url_array as $key => $url_part ) {

			// Unset the term object from previous loop
			if ( isset ( $term_object ) ) { unset ( $term_object ); }

			// Checking if it is a CATEGORY
			// Note: category slugs get priority over tag slugs within core WP
			// So: if not currently viewing a tag, we presume a possible match will be a category
			if ( ! is_tag() && get_term_by ( 'slug', $url_part, 'category' ) ) {
				$term_object = get_term_by ( 'slug', $url_part, 'category' );
			}

			// It's not a category, checking if its is a TAG
			elseif ( get_term_by ( 'slug', $url_part, 'post_tag' ) ) {
				$term_object = get_term_by ( 'slug', $url_part, 'post_tag' );
			}

			// Only continue if a compatible term (CATEGORY or TAG) has been found
			if ( isset ( $term_object ) && is_object ( $term_object ) ) {

				// Getting the name, ID and URL
				$term_name = $term_object->name;
				$term_type = $term_object->taxonomy;
				$term_id = $term_object->term_id;
				$term_url = get_term_link ( $term_object );

				// Check if current term is active
				// Note: we'll do this by comparing term IDs, because categories and tags are both terms with unique term IDs
				$term_active = get_queried_object_id() === $term_id ? true : false;

				// Adds the item to existing array
				array_push ( $breadcrumbs, array ( 'type' => $term_type, 'name' => $term_name, 'url' => $term_url, 'active' => $term_active ) );
				
			}
			
		}

		$breadcrumbs_all = array_merge ( $breadcrumbs, $last_breadcrumb );

		// If breadcrumbs only contains 1 element, it wouldn't be much of a breadcrumb; won't it? If so, return false.
		if ( count ( $breadcrumbs_all ) === 1 ) { return false; }

		// Uncomment below for debugging
		// echo "<script>console.log ('Function create_breadcrumbs_array_from_url completed! Its result is: " . json_encode ( $breadcrumbs_all ) . "');</script>";

		return $breadcrumbs_all;

	}

	/**
	* Get class instance.
	*/
	public static function get_instance(){
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

}

// Create an instance of our class to kick off the whole thing
$emnl_url_breadcrumbs = EMNL_URL_Breadcrumbs::get_instance();
