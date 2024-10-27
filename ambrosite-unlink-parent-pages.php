<?php
/*
Plugin Name: Ambrosite Unlink Parent Pages
Plugin URI: http://www.ambrosite.com/plugins
Description: Unlinks parent pages in page menus and lists. Affects the output of wp_list_pages and wp_page_menu.
Version: 1.4
Author: J. Michael Ambrosio
Author URI: http://www.ambrosite.com
License: GPL2
*/

function ambrosite_unlink_parent_pages_activate() {
	if ( function_exists('mystique_list_pages') ) {
		deactivate_plugins( basename(__FILE__) );
		wp_die( "Unlink Parent Pages is not compatible with the Mystique theme, sorry!");
	}
}
register_activation_hook( __FILE__, 'ambrosite_unlink_parent_pages_activate');

/**
 * This filter is priority zero (highest priority), so other plugins that filter the output
 * of wp_list_pages, such as Page Menu Editor and All In One SEO Pack, can still function.
 */
add_filter('wp_list_pages', 'wp_list_pages_unlink_parents', 0, 2);

/**
 * Based on wp_list_pages from wp-includes/post-template.php
 * The only reason for replacing wp_list_pages is to make it call the revised Walker_Page class.
 * Otherwise, it operates identically to the core function.
 */
function wp_list_pages_unlink_parents($output, $r) {
	$output = '';
	$current_page = 0;

	$pages = get_pages($r);

	if ( !empty($pages) ) {
		if ( $r['title_li'] )
			$output .= '<li class="pagenav">' . $r['title_li'] . '<ul>';

		$r['walker'] = new Walker_Page_Unlink_Parents;
			
		global $wp_query;
		if ( is_page() || is_attachment() || $wp_query->is_posts_page )
			$current_page = $wp_query->get_queried_object_id();
		$output .= walk_page_tree($pages, $r['depth'], $current_page, $r);

		if ( $r['title_li'] )
			$output .= '</ul></li>';
	}

	return $output;
}

/**
 * Create HTML list of pages, with unlinked parent pages.
 * Based on Walker_Page class from wp-includes/post-template.php
 *
 * @uses Walker
 */
class Walker_Page_Unlink_Parents extends Walker {

	var $tree_type = 'page';

	var $db_fields = array ('parent' => 'post_parent', 'id' => 'ID');
	
	// Define six new member variables to hold the option settings, and the list of all page objects
	var $option_dummy = 1;
	
	var $option_unlink_current = 0;

	var $option_remove_titles = 0;

	var $option_maxdepth = 9999;
	
	var $option_unlink_array = array();
	
	var $all_pages = array();

	// Define a constructor to load the option settings from the db, and initialize the page object list
	function Walker_Page_Unlink_Parents() {
		$unlink_options = get_option('ambrosite_unlink_parents');

		$this->option_dummy = is_null($unlink_options['dummy']) ? 1 : $unlink_options['dummy'];
		$this->option_unlink_current = $unlink_options['unlink_current'];
		$this->option_remove_titles = $unlink_options['remove_titles'];
		$this->option_maxdepth = ( $unlink_options['maxdepth'] ) ? $unlink_options['maxdepth'] : 9999;
		if ( $unlink_options['expages'] )
			$this->option_unlink_array = array_map( 'intval', explode(',', $unlink_options['expages']) );

		$all_pages_wp_query = new WP_Query();
		$this->all_pages = $all_pages_wp_query->query( array('post_type' => 'page', 'posts_per_page' => -1) );
	}

	function start_lvl(&$output, $depth) {
		$indent = str_repeat("\t", $depth);
		$output .= "\n$indent<ul>\n";
	}

	function end_lvl(&$output, $depth) {
		$indent = str_repeat("\t", $depth);
		$output .= "$indent</ul>\n";
	}

	// This is the only method that has been modified from the core Walker_Page class.
	function start_el(&$output, $page, $depth, $args, $current_page) {
		if ( $depth )
			$indent = str_repeat("\t", $depth);
		else
			$indent = '';

		extract($args, EXTR_SKIP);

		$unlink_current = false;
		$css_class = array('page_item', 'page-item-'.$page->ID);
		if ( !empty($current_page) ) {
			$_current_page = get_page( $current_page );
			if ( isset($_current_page->ancestors) && in_array($page->ID, (array) $_current_page->ancestors) )
				$css_class[] = 'current_page_ancestor';
			if ( $page->ID == $current_page ) {
				$css_class[] = 'current_page_item';
				if ( $this->option_unlink_current )
					$unlink_current = true;
			}
			elseif ( $_current_page && $page->ID == $_current_page->post_parent )
				$css_class[] = 'current_page_parent';
		} elseif ( $page->ID == get_option('page_for_posts') ) {
			$css_class[] = 'current_page_parent';
		}

		$css_class = implode(' ', apply_filters('page_css_class', $css_class, $page));
		
//		Begin modified code. Find any child pages, and if they exist, unlink the parent page (if at a depth less than maxdepth).
		$page_children = get_page_children($page->ID, $this->all_pages);

		$link_open = '<a href="' . get_page_link($page->ID) . '"';
		$link_close = '</a>';
		if ( !empty($page_children) && $depth < $this->option_maxdepth || in_array($page->ID, $this->option_unlink_array) || $unlink_current ) {
			if ( $this->option_dummy ) {
				$link_open = '<a href="#" style="cursor: default;"';
			} else {
				$link_open = '<span';
				$link_close = '</span>';
			}
		}

		$page_title = ( $this->option_remove_titles ) ? '' : ' title="' . esc_attr(apply_filters('the_title', $page->post_title)) . '"';

		$output .= $indent . '<li class="' . $css_class . '">' . $link_open . $page_title . '>' . $link_before . apply_filters('the_title', $page->post_title) . $link_after . $link_close;
//		End of modified code.

		if ( !empty($show_date) ) {
			if ( 'modified' == $show_date )
				$time = $page->post_modified;
			else
				$time = $page->post_date;

			$output .= " " . mysql2date($date_format, $time);
		}
	}

	function end_el(&$output, $page, $depth) {
		$output .= "</li>\n";
	}

}

function ambrosite_unlink_parents_adminpage() {
	?>
	<div class="wrap">
		<h2 style="margin-bottom: .5em;">Ambrosite Unlink Parent Pages - Options</h2>
		<?php foreach ( wp_get_nav_menus() as $menu ) {
			if ( wp_get_nav_menu_items($menu->term_id) ) {
				echo '<p style="border: solid 1px red;"><strong style="display: inline-block; padding: 10px;"><span style="color: red">IMPORTANT: Unlink Parent Pages has detected custom menus.</span> This plugin is not compatible with the WordPress 3.x custom menu system (that is, menus created using the drag-and-drop menu builder under Appearance->Menus). If you want to create an unlinked parent menu item with the menu builder, use a \'Custom Link\' with a \'#\' (hash/pound) mark in the URL field.</strong></p>';
				break;
			}
		} ?>
		<form method="post" action="options.php">
			<?php
			settings_fields('ambrosite_unlink_parents_options');
			$options = get_option('ambrosite_unlink_parents');
			if ( is_null($options['dummy']) )
				$options['dummy'] = 1; 
			?>
			<fieldset>
			<label><strong>Use Dummy Links:</strong> </label><input name="ambrosite_unlink_parents[dummy]" type="checkbox" value="1" <?php checked('1', $options['dummy']); ?> />
			<p style="margin: .2em 0 1.5em 0;">In some themes, unlinking the parent pages may cause problems with CSS styling. If that happens, try using dummy links instead. A dummy link is just like a regular link, except that it leads back to the current page when clicked. (Turned on by default as of Unlink Parent Pages version 1.3)</p>
			</fieldset>
			<fieldset>
			<label><strong>Unlink Current Page:</strong> </label><input name="ambrosite_unlink_parents[unlink_current]" type="checkbox" value="1" <?php checked('1', $options['unlink_current']); ?> />
			<p style="margin: .2em 0 1.5em 0;">Unlink the current page, in addition to the parent pages.</p>
			</fieldset>
			<fieldset>
			<label><strong>Remove Link Titles:</strong> </label><input name="ambrosite_unlink_parents[remove_titles]" type="checkbox" value="1" <?php checked('1', $options['remove_titles']); ?> />
			<p style="margin: .2em 0 1.5em 0;">Remove the title attribute from the links. Stops the tooltip from popping up when the mouse hovers over the menu items.</p>
			</fieldset>
			<fieldset>
			<label><strong>Maximum Depth:</strong> </label><input type="text" name="ambrosite_unlink_parents[maxdepth]" maxlength="4" size="4" value="<?php echo $options['maxdepth']; ?>" />
			<p style="margin: .2em 0 1.5em 0;">Controls how many levels in the page hierarchy are to be unlinked. Works exactly like the 'depth' option for wp_list_pages.</p>
			<ul style="margin: 0 0 1.5em 0;">
			<li style="margin-bottom: 2px;">0 (default) Unlinks all parent pages, anywhere in the page hierarchy.</li>
			<li style="margin-bottom: 2px;">1 Unlinks top-level parent pages only.</li>
			<li style="margin-bottom: 2px;">2, 3, ... Unlinks parent pages to the given depth.</li>
			<li style="margin-bottom: 2px;">-1 Do not unlink any pages (specify individual pages below).</li>
			</ul>
			</fieldset>
			<fieldset>
			<label><strong>Unlink Specific Pages:</strong> </label><input type="text" name="ambrosite_unlink_parents[expages]" size="40" value="<?php echo $options['expages']; ?>" />
			<p style="margin: .2em 0 1.5em 0;">You can specify which pages you want unlinked, using a comma-separated list of page IDs (example: 3,7,31). Works exactly like the 'exclude' option for wp_list_pages. If you want <em>only</em> these pages to be unlinked, then set max depth to -1.</p>
			</fieldset>
			<p class="submit">
			<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
			</p>
		</form>
	</div>
	<?php	
}

function ambrosite_unlink_parents_addpage() {
	add_options_page('Ambrosite Unlink Parent Pages Options', 'Unlink Parent Pages', 'manage_options', 'ambrosite_unlink_parents', 'ambrosite_unlink_parents_adminpage');
}
add_action('admin_menu', 'ambrosite_unlink_parents_addpage');

// Sanitize and validate input. Accepts an array, returns a sanitized array.
function ambrosite_unlink_parents_validate($input) {
	foreach ( array('dummy', 'unlink_current', 'remove_titles') as $key ) {
		if ( array_key_exists($key, $input) )
			$input[$key] = ( $input[$key] == 1 ) ? 1 : 0;
		else
			$input[$key] = 0;
	}

	$input['maxdepth'] = intval($input['maxdepth']);
	if ( $input['maxdepth'] < -1 || $input['maxdepth'] > 9999 )
		$input['maxdepth'] = 0;
	
	$input['expages'] = preg_replace('/[^0-9,]/', '', $input['expages']);

	return $input;
}

// Init plugin options to white list the options
function ambrosite_unlink_parents_init(){
	register_setting( 'ambrosite_unlink_parents_options', 'ambrosite_unlink_parents', 'ambrosite_unlink_parents_validate' );
}
add_action('admin_init', 'ambrosite_unlink_parents_init' );
?>