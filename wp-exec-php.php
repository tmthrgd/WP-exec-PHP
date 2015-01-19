<?php
/**
 * Author: Tom Thorogood
 * Author URI: http://xenthrax.com
 * Description: Execute PHP inside posts, pages, text widgets and text widget titles. Add filters and actions using custom feilds.
 * Plugin Name: WP exec PHP
 * Plugin URI: http://xenthrax.com/wordpress/wp-exec-php/
 * Version: 2.0.1
 * 
 * Donate: http://xenthrax.com/donate/
 * 
 * Plugin Shortlink: http://xenthrax.com/wp-exec-php/
 * Other Plugins: http://xenthrax.com/wordpress/
 * 
 * WordPress Plugin: http://wordpress.org/extend/plugins/wp-exec-php/
 */

/**
 * If you notice any issues or bugs in the plugin please contact me [@link http://xenthrax.com/about/]
 * If you make any revisions to and/or re-release this plugin please contact me [@link http://xenthrax.com/about/]
 */

/**
 * Copyright (c) 2010-2013 Tom Thorogood
 * 
 * This file is part of "WP exec PHP" WordPress Plugin.
 * 
 * "WP exec PHP" is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation version 3.
 * 
 * You may NOT assume that you can use any other version of the GPL.
 * 
 * "WP exec PHP" is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with "WP exec PHP". If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * TODO:
 *  Add user-by-user configuration.
 *  Get roles & capabilities working.
 *  Add banned users UI.
 */

/**
 * @package WP exec PHP
 * @since 2.0
 */
class WP_exec_PHP {
	/**
	 * @access private
	 * @since 2.0
	 * @var array
	 */
	private $notices = array();
	
	/**
	 * @access private
	 * @since 2.0.1
	 * @var string
	 */
	private $file = __FILE__;
	
	/**
	 * @access private
	 * @since 2.0
	 * @param bool $esc
	 * @param bool $echo
	 * @return string Data
	 */
	function _plugin_data($key, $esc = false, $echo = false) {
		static $plugin_data;
		$key = strtolower($key);
		
		if (is_null($plugin_data)) {
			$plugin_data = get_file_data($this->file, array(
				'author'       => 'Author',
				'authoruri'    => 'Author URI',
				'donate'       => 'Donate',
				'name'         => 'Plugin Name',
				'uri'          => 'Plugin URI',
				'version'      => 'Version',
				'shortlink'    => 'Plugin Shortlink',
				'otherplugins' => 'Other Plugins'
			));
		}
		
		if (!array_key_exists($key, $plugin_data) || empty($plugin_data[$key]))
			return false;
		
		$data = $plugin_data[$key];
		
		if ($esc) {
			switch ($key) {
				case 'author':
				case 'name':
				case 'version':
					$data = esc_html($data);
					break;
				case 'authoruri':
				case 'donate':
				case 'uri':
				case 'shortlink':
				case 'otherplugins':
					$data = esc_url($data);
					break;
				/*default:
					return false;*/
			}
		}
		
		$data = apply_filters("{$this->slug('hook')}-plugin-data", $data, $key, $esc, $echo, $plugin_data);
		
		if ($data === NULL)
			return false;
		
		if (!$echo)
			return $data;
		
		echo $data;
		return true;
	}
	
	/**
	 * @access public
	 * @since 2.0
	 * @param bool $esc
	 * @param bool $echo
	 * @return string Plugin version
	 */
	function version($esc = false, $echo = false) {
		return $this->_plugin_data('version', $esc, $echo);
	}
	
	/**
	 * @since 2.0
	 */
	function WP_exec_PHP() {
		$args = func_get_args();
		call_user_func_array(array(&$this, '__construct'), $args);
	}
	
	/**
	 * @since 2.0
	 */
	function __construct() {
		load_plugin_textdomain($this->slug(), false, basename(dirname($this->file)) . '/lang');
		
		if (!$this->get_option('version') || version_compare($this->get_option('version'), $this->version(), '<')) {
			$old_options = get_option('wp-exec-php');
			
			if (!empty($old_options)) {
				if (function_exists('maybe_unserialize'))
					$old_options = maybe_unserialize($old_options);
				else
					$old_options = @unserialize($old_options);
				
				if (isset($old_options['level']))
					$this->set_option('role', sprintf('level_%d', $old_options['level']));
				
				if (isset($old_options['widget']))
					$this->set_option('widgets', (bool)$old_options['widget']);
				
				delete_option('wp-exec-php');
			}
			
			$this->set_option('version', $this->version());
		}
		
		$this->add_option('version', $this->version());
		
		foreach ($this->default_options() as $name => $value)
			$this->add_option($name, $value);
		
		foreach (array('the_content', 'the_excerpt', 'the_excerpt_rss') as $filter)
			add_filter($filter, array(&$this, '_exec_post'), 1);
		
		add_filter('widget_text', array(&$this, '_exec_widget'), 1);		
		add_filter('widget_title', array(&$this, '_widget_title'), 1, 3);
		add_filter('widget_update_callback', array(&$this, '_widget_update_callback'), 10, 4);
		add_filter('wp_insert_post_data', array(&$this, '_wp_insert_post_data'));
		//add_filter("plugin_action_links_{$this->slug('plugin')}", array(&$this, '_plugin_action_links'));
		add_filter('plugin_row_meta', array(&$this, '_plugin_row_meta'), 10, 2);
		add_action("admin_head-{$this->slug('settings')}", array(&$this, '_admin_head_options'));
		add_action('admin_notices', array(&$this, '_admin_notices'), 9);
		add_action('admin_menu', array(&$this, '_admin_menu'));
		add_action("load-{$this->slug('settings')}", array(&$this, '_options_init'));
		add_action('widgets_init', array(&$this, '_widgets_init'));
		add_action('widget_form_callback', array(&$this, '_widget_form_callback'), 10, 2);
		add_action('admin_print_footer_scripts', array(&$this, '_admin_print_footer_scripts'), 21);
		add_action('deleted_user', array(&$this, 'optimize'));
		add_action('user_register', array(&$this, '_user_register'));
		add_action('wp', array(&$this, '_wp'), 1, pow(2, 31) - 1);
	}
	
	/**
	 * @access private
	 * @since 2.0
	 * @param string $name
	 * @param string $value Optional.
	 * @return void
	 */
	private function add_option($name, $value = '') {
		$value = apply_filters("{$this->slug('hook')}-add-option", $value, $name);
		
		if ($value === NULL)
			return;
		
		add_option("{$this->slug()}-{$name}", $value);
	}
	
	/**
	 * @access private
	 * @since 2.0
	 * @param string $name
	 * @return string Option value
	 */
	private function get_option($name) {
		$value = get_option("{$this->slug()}-{$name}");
		return apply_filters("{$this->slug('hook')}-get-option", $value, $name);
	}
	
	/**
	 * @access private
	 * @since 2.0
	 * @param string $name
	 * @param string $value Optional.
	 * @return void
	 */
	private function set_option($name, $value = '') {
		$value = apply_filters("{$this->slug('hook')}-set-option", $value, $name);
		
		if ($value === NULL)
			return;
		
		update_option("{$this->slug()}-{$name}", $value);
	}
	
	/**
	 * @access private
	 * @since 2.0
	 * @return array Default options
	 */
	private function default_options() {
		$options = array(
			'allowed' => '',
			'banned'  => '',
			'hooks'   => false,
			//'role'    => 'administrator',
			'role'    => 'level_10',
			'widgets' => false
			);
		return apply_filters("{$this->slug('hook')}-default-options", $options);
	}
	
	/**
	 * @access private
	 * @since 2.0
	 * @param bool $opening
	 * @param bool $echo Optional.
	 * @return string Comment tag
	 */
	function _comment_tag($opening, $echo = true) {
		if ($opening)
			$tag = "<!--{$this->_plugin_data('name', true)} - {$this->version(true)}: {$this->_plugin_data('uri', true)}-->\n";
		else
			$tag = "<!--/{$this->_plugin_data('name', true)}-->\n";
		
		$tag = apply_filters("{$this->slug('hook')}-comment-tag", $tag, $opening, $echo);
		
		if ($tag === false)
			return false;
		
		if (!$echo)
			return $tag;
		
		echo $tag;
		return true;
	}
	
	/**
	 * @note WordPress < 2.9.0 will always return true
	 * @access public
	 * @since 2.0
	 * @return bool Is the latest version of plugin
	 */
	function latest_version() {
		$latest = true;
		
		if (function_exists('get_site_transient')) {
			$plugins = get_site_transient('update_plugins');
			$latest = (!isset($plugins->response) || !is_array($plugins->response) || !isset($plugins->response[$this->slug('plugin')]));
		}
		
		return apply_filters("{$this->slug('hook')}-latest-version", $latest);
	}
	
	/**
	 * @access private
	 * @since 2.0
	 * @param string $msg
	 * @param string $type Optional.
	 * @param int $priority Optional.
	 * @return void
	 */
	private function add_notice($msg, $type = 'updated', $priority = false) {
		$type = strtolower($type);
		$priority = ($priority === false) ? (($type === 'error') ? 5 : 10) : (int)$priority;
		$msg = apply_filters("{$this->slug('hook')}-add-notice", $msg, $type, $priority);
		
		if (empty($msg))
			return false;
		
		$this->notices[$priority][] = (object)array(
			'msg' => (string)$msg,
			'type' => (string)$type
			);
		return true;
	}
	
	/**
	 * @access private
	 * @since 2.0
	 * @return void
	 */
	function _admin_notices() {
		if (is_super_admin()) {
			$notices = $this->get_option('admin_notices');
			
			if (!empty($notices))
				foreach ($notices as $notice)
					$this->add_notice($notice->msg, $notice->type, $notice->priority);
			
			$this->set_option('admin_notices', '');
		}
		
		$this->notices = apply_filters("{$this->slug('hook')}-print-notices", $this->notices);
		ksort($this->notices);
		
		if (!empty($this->notices)) {
			$this->_comment_tag(true);
			
			foreach ($this->notices as $priority => $notices)
				foreach ($notices as $notice)
					echo "<div class=\"{$notice->type}\"><p>{$notice->msg}</p></div>\n";
			
			$this->_comment_tag(false);
		}
	}
		
	/**
	 * @access private
	 * @since 2.0
	 * @return void
	 */
	function _wp() {		
		if (is_singular() && $this->get_option('hooks')) {
			global $wp_query;
			$post =& $wp_query->get_queried_object();
			
			if ($this->can_execute($post->post_author)) {
				$custom = get_post_custom($post->ID);
				
				foreach ((array)$custom as $name => $values) {
					if (stripos($name, 'action_') === 0 || stripos($name, 'filter_') === 0) {
						$tag = substr($name, 7);
						
						if (($pos = strpos($tag, '_')) !== false && is_numeric($priority = substr($tag, 0, $pos))) {
							$tag = substr($tag, $pos + 1);
							$priority = ($priority > 0) ? intval($priority) : 10;
						} else
							$priority = 10;
						
						if (!empty($tag)) {
							foreach ((array)$values as $value) {
								$value = apply_filters("{$this->slug('hook')}-add-hook", $value, $tag, $priority, $post, $custom);
								
								if (!empty($value)) {
									$func = create_function('', '?' . ">{$value}<" . '?');
									
									if ($tag === 'wp' && $priority === 1) {
										$args = func_get_args();
										call_user_func_array($func, $args);
									}
									
									add_filter($tag, $func, $priority, pow(2, 31) - 1);
								}
							}
						}
					}
				}
			}
		}
	}
	
	/**
	 * @access private
	 * @since 2.0
	 * @return void
	 */
	function _widgets_init() {
		global $wp_widget_factory;
		
		if (array_key_exists('WP_Widget_Text', $wp_widget_factory->widgets))
			$wp_widget_factory->widgets['WP_Widget_Text']->widget_options['description'] = __('Arbitrary text, HTML or PHP', $this->slug());
	}
	
	/**
	 * @access private
	 * @since 2.0
	 * @param string $content
	 * @param object|array $instance Optional.
	 * @param string $id_base Optional.
	 * @return string Title
	 */
	function _widget_title($content, $instance = '', $id_base = '') {
		if ($id_base === 'text') {
			if (apply_filters("{$this->slug('hook')}-do-exec-widget-title", true, $content, $instance, $id_base))
				$content = $this->_exec_widget($content/*, false*/);
			
			$content = apply_filters("{$this->slug('hook')}-exec-widget-title", $content, $instance, $id_base);
		}
		
		return $content;
	}
	
	/**
	 * @access private
	 * @since 2.0
	 * @param object|array $instance
	 * @param WP_Widget $widget WP_Widget
	 * @return mixed
	 */
	function _widget_form_callback($raw_instance, $widget) {
		if (is_a($widget, 'WP_Widget_Text')) {
			$instance = wp_parse_args((array)$raw_instance, array('title' => '', 'text' => ''));
			
			$this->_comment_tag(true);
?>
		<p><label for="<?php echo $widget->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
		<input class="widefat" id="<?php echo $widget->get_field_id('title'); ?>" name="<?php echo $widget->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($instance['title']); ?>" /></p>
		<textarea class="widefat" rows="16" cols="20" id="<?php echo $widget->get_field_id('text'); ?>" name="<?php echo $widget->get_field_name('text'); ?>"><?php echo format_to_edit($instance['text']); ?></textarea>
		<p><input id="<?php echo $widget->get_field_id('filter'); ?>" name="<?php echo $widget->get_field_name('filter'); ?>" type="checkbox" <?php checked(isset($instance['filter']) ? $instance['filter'] : 0); ?> />&nbsp;<label for="<?php echo $widget->get_field_id('filter'); ?>"><?php _e('Automatically add paragraphs'); ?></label></p>
<?php
			$this->_comment_tag(false);
			
			$result = null;
			do_action_ref_array('in_widget_form', array(&$widget, &$result, $raw_instance));
			return false;
		}
		
		return $raw_instance;
	}
	
	/**
	 * @access private
	 * @since 2.0
	 * @param array $instance
	 * @param array $new_instance
	 * @param array $old_instance
	 * @param WP_Widget $widget
	 * @return array
	 */
	function _widget_update_callback($instance, $new_instance, $old_instance, $widget) {
		if (is_a($widget, 'WP_Widget_Text')) {
			if (current_user_can('unfiltered_html'))
				$instance['title'] = $new_instance['title'];
			else
				$instance['title'] = stripslashes(wp_filter_post_kses(addslashes($new_instance['title'])));
		}
		
		return $instance;
	}
	
	/**
	 * @access private
	 * @since 2.0
	 * @param array $matches
	 * @return string Properly formated php
	 */
	function _post_content_php_commented($matches) {
		return '<?php' . stripslashes($matches[1]) . '?' . '>';
	}
	
	/**
	 * @access private
	 * @since 2.0
	 * @param array $matches
	 * @return string Properly formated php
	 */
	function _post_content_php_encoded($matches) {
		return '<?php' . html_entity_decode(stripslashes($matches[1]), ENT_QUOTES, get_bloginfo('charset')) . '?' . '>';
	}
	
	/**
	 * @access private
	 * @since 2.0
	 * @param array $data
	 * @return array Post data with php fixed
	 */
	function _wp_insert_post_data($data) {
		if (stripos($data['post_content'], '<!--?') < strpos($data['post_content'], '?-->'))
			$data['post_content'] = preg_replace_callback('/<!--\?(?:php)?(.*?)\?-->/s', array(&$this, '_post_content_php_commented'), $data['post_content']);
		
		if (stripos($data['post_content'], '&lt;?') < strpos($data['post_content'], '?&gt;'))
			$data['post_content'] = preg_replace_callback('/&lt;\?(?:php)?(.*?)\?(?:&gt;|>)/s', array(&$this, '_post_content_php_encoded'), $data['post_content']);
		
		$data['post_content'] = $this->normalize_php($data['post_content']);
		$data['post_content'] = apply_filters("{$this->slug('hook')}-insert-post-data", $data['post_content'], $data);
		return $data;
	}
	
	/**
	 * @access private
	 * @since 2.0
	 * @deprected 2.0
	 * @deprected Use $WP_exec_PHP->_plugin_row_meta()
	 * @return array Links to display
	 */
	function _plugin_action_links($links) {
		_deprecated_function(__CLASS__ . '::' . __FUNCTION__, '2.0', '$WP_exec_PHP->_plugin_row_meta()');
		$links[] = '<a href="' . admin_url('options-general.php?page=' . urlencode($this->slug('plugin'))) . '">' . __('Settings', $this->slug()) . '</a>';
		$links[] = "<a href=\"{$this->_plugin_data('donate')}\">" . __('Donate', $this->slug()) . '</a>';
		return $links;
	}
	
	/**
	 * @access private
	 * @since 2.0
	 * @return array Links to display
	 */
	function _plugin_row_meta($links, $file) {
		if ($file == $this->slug('plugin')) {
			$links[] = '<a href="' . admin_url('options-general.php?page=' . urlencode($this->slug('plugin'))) . '">' . __('Settings', $this->slug()) . '</a>';
			$links[] = "<a href=\"{$this->_plugin_data('donate')}\">" . __('Donate', $this->slug()) . '</a>';
		}
		
		return $links;
	}
	
	/**
	 * @access private
	 * @since 2.0
	 * @return string
	 */
	private function slug($context = 'name', $esc = false, $echo = false) {
		if (!is_string($context)) {
			$echo    = $esc;
			$esc     = $context;
			$context = 'name';
		}
		
		switch ($context) {
			case 'plugin':
				$slug = plugin_basename($this->file);
				break;
			case 'settings':
				$slug = 'settings_page_' . basename(dirname($this->file)) . '/' . basename($this->file, '.php');
				break;
			case 'name':
			case 'slug':
				$slug = preg_replace('/[^a-z0-9_\-]/i', '-', $this->_plugin_data('name'));
				break;
			case 'hook':
				$slug = 'wp-exec-php';
				break;
			default:
				$slug = false;
				break;
		}
		
		if ($context !== 'hook')
			$slug = apply_filters("{$this->slug('hook')}-slug", $slug, $context, $esc, $echo);
		
		if ($slug === false)
			return false;
		
		if ($esc)
			$slug = esc_html($slug);
		
		if (!$echo)
			return $slug;
		
		echo $slug;
		return true;
	}
	
	/**
	 * @acess private
	 * @since 2.0
	 * @param string $content
	 * @return string Content with fixed PHP
	 */
	function normalize_php($content) {
		if (stripos($content, '<phpcode>') !== false) {
			_deprecated_argument(__CLASS__ . '::' . __FUNCTION__, '2.0', '<phpcode> is deprected use \'<?php ?' . '>\' instead.');
			$content = str_ireplace(array('<phpcode>', '</phpcode>'), '', $content); //be compatible with Exec PHP
		}
		
		if (stripos($content, '[exec]') !== false) {
			_deprecated_argument(__CLASS__ . '::' . __FUNCTION__, '2.0', '[exec] is deprected use \'<?php ?' . '>\' instead.');
			$content = str_ireplace('[exec]', '<?php ', $content);
			$content = str_ireplace('[/exec]', ' ?' . '>', $content);
		}
		
		if (strpos($content, '<%=') !== false) {
			_deprecated_argument(__CLASS__ . '::' . __FUNCTION__, '2.0', '<%= is deprected use \'<?=\' instead.');
			$content = str_replace('<%=', '<?=', $content);
		}
		
		if (strpos($content, '<%') !== false) {
			_deprecated_argument(__CLASS__ . '::' . __FUNCTION__, '2.0', '<% is deprected use \'<?php ?' . '>\' instead.');
			$content = str_replace('<%', '<?php', $content);
			$content = str_replace('%>', '?' . '>', $content);
		}
		
		if (strpos($content, '<?') !== false) {
			$count = 0;
			//$content = preg_replace('/<\?(?!php|xml|=|[a-z0-9])/i', '<?php', $content, -1, $count);
			$content = preg_replace('/<\?(\s)/', '<?php\1', $content, -1, $count);
			
			if ($count)
				_deprecated_argument(__CLASS__ . '::' . __FUNCTION__, '2.0', '<? is deprected use \'<?php ?' . '>\' instead.');
		}
		
		return apply_filters("{$this->slug('hook')}-normalize-php", $content);
	}
	
	/**
	 * @access public
	 * @since 2.0
	 * @param string $content
	 * @return string Content with PHP executed
	 */
	function exec($content = ''/*, $comments = true*/) {	
		$content = $this->normalize_php($content);
		
		// "Starting with PHP 5.4, short echo tag <?= is always recognized and valid, regardless of the short_open_tag setting." - php.net
		if (version_compare(phpversion(), '5.4.0', '<') && strpos($content, '<?=') !== false)
			$content = str_replace('<?=', '<?php echo ', $content);
		
		if (apply_filters("{$this->slug('hook')}-do-exec", true, $content)
			&& (stripos($content, '<?php') !== false
				|| stripos($content, '<?=') !== false)) {
			ob_start();
			
			eval('?' . ">{$content}");
			
			$content = ob_get_clean();
			
			/* WordPress adds <br/> tags before/after comments. */
			/*$content = $comments ? $this->_comment_tag(true, false) : '';
			$content .= ob_get_clean();
			$content .= $comments ? "\n{$this->_comment_tag(false, false)}" : '';*/
		}
		
		return apply_filters("{$this->slug('hook')}-exec", $content);
	}
	
	/**
	 * @access public
	 * @since 2.0
	 * @param string $content
	 * @return string Content with PHP removed
	 */
	function clean($content = '') {
		$content = $this->normalize_php($content);
		
		if (apply_filters("{$this->slug('hook')}-do-clean", true, $content)
			&& (stripos($content, '<?php') !== false
				|| stripos($content, '<?=') !== false))
			$content = preg_replace('/\<\?(?:php|=).*?\?\>/si', '', $content);
		
		return apply_filters("{$this->slug('hook')}-clean", $content);
	}
	
	/**
	 * @access private
	 * @since 2.0
	 * @param string $content
	 * @return string Content with PHP executed/removed
	 */
	function _exec_post($content) {
		global $post;
		
		if (apply_filters("{$this->slug('hook')}-do-exec-post", true, $content, $post))
			$content = $this->can_execute($post->post_author) ? $this->exec($content) : $this->clean($content);
		
		return apply_filters("{$this->slug('hook')}-exec-post", $content, $post);
	}
	
	/**
	 * @access private
	 * @since 2.0
	 * @param string $content
	 * @return string Content with PHP executed/removed 
	 */
	function _exec_widget($content/*, $comments = true*/) {
		if (apply_filters("{$this->slug('hook')}-do-exec-widget", true, $content))
			$content = $this->get_option('widgets') ? $this->exec($content/*, $comments*/) : $this->clean($content);
		
		return apply_filters("{$this->slug('hook')}-exec-widget", $content);
	}
	
	/**
	 * @access private
	 * @since 2.0
	 * @param int $id
	 * @return void
	 */
	function _user_register($id) {
		if (!is_super_admin() || apply_filters("{$this->slug('hook')}-ignore-is-super-admin", false, $id)) {
			$user = new WP_User($id);
			$post = $this->can_execute($user->id);
			$widget = ($this->get_option('widgets') && $user->has_cap('edit_theme_options'));
			
			if ($post || $widget) {
				$notice = 'A new user (login:%1$s id:%2$d) has registered and has the ability to execute php';
				
				if ($post && $widget)
					$notice .= ' in posts and widgets';
				else if ($post)
					$notice .= ' in posts';
				else if ($widget)
					$notice .= ' in widgets';
				
				$notice = sprintf(__("{$notice}.", $this->slug()), $user->user_login, $user->id);
				$notice = apply_filters("{$this->slug('hook')}-new-user-can-execute", $notice, $id, $user, $post, $widget);
				
				if ($notice !== false) {
					$notices = $this->get_option('notices');
					
					if (empty($notices) || !is_array($notices))
						$notices = array();
					
					$notices[] = (object)array('msg' => $notice, 'type' => 'error', 'priority' => 1);
					$this->set_option('notices', $notices);
				}
			}
		}
	}
	
	/**
	 * @access private
	 * @since 2.0
	 * @param int $id
	 * @return bool Wheather user is allowed to execute PHP
	 */
	private function can_execute($id) {
		if ($this->banned_execute($id))
			return false;
		
		static $users;
		
		if (is_null($users)) {
			$users = $this->get_option('allowed');
			$users = empty($users) ? array() : explode(',', $users);
			$users = array_map('intval', $users);
		}
		
		$id = intval($id);
		
		if (in_array($id, $users))
			$exec = true;
		else {
			$user = new WP_User($id);
			$exec = $user->has_cap($this->get_option('role'));
		}
		
		return apply_filters("{$this->slug('hook')}-can-execute", $exec, $id, $users);
	}
	
	/**
	 * @access private
	 * @since 2.0
	 * @param int $id
	 * @return bool Whether user is banned from executing PHP
	 */
	private function banned_execute($id) {
		static $users;
		
		if (is_null($users)) {
			$users = $this->get_option('banned');
			$users = empty($users) ? array() : explode(',', $users);
			$users = array_map('intval', $users);
		}
		
		$exec = in_array(intval($id), $users);
		return apply_filters("{$this->slug('hook')}-banned-execute", $exec, $id, $users);
	}

	/**
	 * @access public
	 * @since 2.0
	 * @return void
	 */
	function optimize() {
		$allowed = $_allowed = $this->get_option('allowed');
		
		if (!empty($_allowed)) {
			$_allowed = explode(',', $_allowed);
			$_allowed = array_map('intval', $_allowed);
			$i = 0;
			
			while ($i < count($_allowed)) {
				if (empty($_allowed[$i]) || !get_userdata($_allowed[$i]))
					unset($_allowed[$i]);
				
				$i++;
			}
			
			$this->set_option('allowed', implode(',', $_allowed));
		}
		
		$banned = $_banned = $this->get_option('banned');
		
		if (!empty($_banned)) {
			$_banned = explode(',', $_banned);
			$_banned = array_map('intval', $_banned);
			$i = 0;
			
			while ($i < count($_banned)) {
				if (empty($_banned[$i]) || !get_userdata($_banned[$i]))
					unset($_banned[$i]);
				
				$i++;
			}
			
			$this->set_option('banned', implode(',', $_banned));
		}
		
		do_action("{$this->slug('hook')}-optimize", $allowed, $banned, $_allowed, $_banned);
	}

	/**
	 * @access private
	 * @since 2.0
	 * @return void
	 */
	function _admin_menu() {
		add_options_page($this->_plugin_data('name'), $this->_plugin_data('name'), 'manage_options', $this->file, array(&$this, '_options_page'));
	}
	
	/**
	 * @access private
	 * @since 2.0
	 * @return void
	 */
	function _admin_print_footer_scripts() {
		if (wp_script_is('editor', 'done') && apply_filters("{$this->slug('hook')}-print-editor-fix", true)) {
			$this->_comment_tag(true);
?>
<script type="text/javascript">
/* <![CDATA[ */
jQuery(function($) {
	var $div = $('<div/>');	
	$('body').bind('beforeWpautop', function(e, o) {
		o.data = o.data.replace(/<\?php.*?\?>/gim, function(str) {
			return $div.text(str).html();
		});
	}).bind('beforePreWpautop', function(e, o) {
		o.data = o.data.replace(/<!--\?php/gi, '&lt;?php').replace(/\?-->/g, '?&gt;').replace(/&lt;\?php.*?\?&gt;/gim, function(str) {
			return $div.html(str).text();
		});
	});
<?php
if (user_can_richedit() && wp_default_editor() != 'html') { //are we using the visual editor?
	//the following fixes an issue were the visual editor doesnt display php
?>
	
	if (switchEditors.mode == '') {
		switchEditors.go('content');
		switchEditors.go('content');
	}
<?php } ?>
});
/* ]]> */
</script>
<?php
			$this->_comment_tag(false);
		}
		
		if (wp_script_is('admin-widgets', 'done') && apply_filters("{$this->slug('hook')}-print-widget-fix", true)) {
			$this->_comment_tag(true);
?>
<script type="text/javascript">
/* <![CDATA[ */
(function($) {
	if (wpWidgets) {
		var appendTitle = wpWidgets.appendTitle;
		wpWidgets.appendTitle = function(widget) {
			if ($('input.id_base[value="text"]', widget).length > 0) {
				var title = $('input[id*="-title"]', widget).val();
				
				if (title) {
					title = title.replace(/</g, '&lt;').replace(/>/g, '&gt;');
					$('.widget-top .widget-title .in-widget-title', widget).html(': ' + title);
				}
			} else
				appendTitle(widget);
		};
	}
})(jQuery);
/* ]]> */
</script>
<?php
			$this->_comment_tag(false);
		}
	}
	
	/**
	 * @access private
	 * @since 2.0
	 * @return void
	 */
	function _admin_head_options() {
		$this->_comment_tag(true);
?>
<style type="text/css">
#<?php $this->slug(true, true); ?> .red{color:red;}
#<?php $this->slug(true, true); ?> .green{color:green;}
#<?php $this->slug(true, true); ?> table{width:100%;}
#<?php $this->slug(true, true); ?> th{width:15%;font-weight:normal;font-size:1.1em;vertical-align:top;}
#<?php $this->slug(true, true); ?> td{font-weight:normal;font-size:0.9em;vertical-align:top;}
#<?php $this->slug(true, true); ?> abbr,#<?php $this->slug(true, true); ?> .dashed{border-bottom:1px dashed;}
</style>
<?php
		$this->_comment_tag(false);
	}
	
	/**
	 * @access private
	 * @since 2.0
	 * @return void
	 */
	function _options_init() {
		global $wp_roles;
		
		if (isset($_POST["{$this->slug()}-submit"])) {
			if (check_admin_referer("{$this->file}_{$this->slug()}_{$this->version()}")) {
				if (isset($_POST["{$this->slug()}-role"])) {
					$role = stripslashes(trim($_POST["{$this->slug()}-role"]));
					
					//if (in_array($role, array_keys($wp_roles->get_names()))
					if (strpos($role, 'level_') === 0)
						$this->set_option('role', $role);
				}
				
				foreach (array('hooks', 'widgets') as $option)
					$this->set_option($option, isset($_POST["{$this->slug()}-{$option}"]));
				
				do_action("{$this->slug('hook')}-set-options");
				$this->add_notice(__('Options saved successfully.', $this->slug()));
			}
		} else if (isset($_POST["{$this->slug()}-reset"])) {
			if (check_admin_referer("{$this->file}_{$this->slug()}_{$this->version()}")) {
				foreach ($this->default_options() as $name => $value)
					$this->set_option($name, $value);
				
				do_action("{$this->slug('hook')}-reset-options");
				$this->add_notice(__('Options reset.', $this->slug()));
			}
		}
	}
	
	/**
	 * @access private
	 * @since 2.0
	 * @return void
	 */
	function _options_page() {
		global $wpdb, $wp_roles, $wp_version;		
		$the_role = $this->get_option('role');
		$widgets = $this->get_option('widgets');
		$allowed = $banned = array();
		
		foreach (get_users_of_blog() as $blog_user) {
			if ($this->banned_execute($blog_user->ID))
				$banned[] = $blog_user->user_login;
			else if ($this->can_execute($blog_user->ID)
				|| ($widgets
					&& $blog_user =& new WP_User($blog_user->ID)
					&& $blog_user->has_cap('edit_theme_options')))
				$allowed[] = $blog_user->user_login;
		}
		
		sort($allowed);
		sort($banned);
		
		$this->_comment_tag(true);
?>
	<div id="<?php $this->slug(true, true); ?>" class="wrap">
		<h2><?php $this->_plugin_data('Name', true, true); ?></h2>
		<form method="post" action="">
			<fieldset class="options">
				<table class="editform">
					<tr>
						<th scope="row"><?php _e('Author:', $this->slug()); ?></th>
						<td><a href="<?php $this->_plugin_data('authoruri', true, true); ?>" target="_blank"><?php $this->_plugin_data('author', true, true); ?></a> | <a href="<?php $this->_plugin_data('otherplugins', true, true); ?>" target="_blank"><?php printf(__('Other plugins by %1$s', $this->slug()), $this->_plugin_data('author', true)); ?></a> | <a href="<?php $this->_plugin_data('uri', true, true); ?>" target="_blank"><?php _e('Documentation', $this->slug()); ?></a> | <a href="<?php $this->_plugin_data('donate', true, true); ?>"><?php _e('Donate', $this->slug()); ?></a></td>
					</tr>
					<tr>
						<th scope="row"><?php _e('Credit:', $this->slug()); ?></th>
						<td><?php printf(__('Inspired by %1$s by %2$s.', $this->slug()), '<a href="http://priyadi.net/archives/2005/03/02/wordpress-php-exec-plugin/" target="_blank">PHP Exec</a>', '<a href="http://priyadi.net/" target="_blank">Priyadi Iman Nurcahyo</a>'); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php _e('Version:', $this->slug()); ?></th>
<?php if (version_compare($wp_version, '2.9.0', '>=')) { ?>
						<td class="<?php if ($this->latest_version()) { echo 'green'; } else { echo 'red'; } ?>"><span class="dashed" title="<?php if ($this->latest_version()) { _e('Latest version', $this->slug()); } else { _e('Newer version avalible', $this->slug()); } ?>"><?php $this->version(true, true); ?></span></td>
<?php } else { ?>
						<td><span class="dashed"><?php $this->version(true, true); ?></span></td>
<?php } ?>
					</tr>
					<tr><td>&nbsp;</td></tr>
					<tr>
						<th scope="row"><?php _e('Role:', $this->slug()); ?></th>
						<td>
							<label>
								<select name="<?php $this->slug(true, true); ?>-role">
<?php
foreach ($wp_roles->get_names() as $role => $name)
	echo "\t\t\t\t\t\t\t\t\t<!--<option value=\"{$role}\"" . selected($the_role, $role, false) . ">{$name}</option>-->\n";

for ($i = 10; $i > 0; $i--)
	echo "\t\t\t\t\t\t\t\t\t<option value=\"level_{$i}\"" . selected($the_role, "level_{$i}", false) . '>' . sprintf(__('Level %1$d', $this->slug()), $i) ."</option>\n";
?>
								</select>
								<?php _e('The required <a href="http://codex.wordpress.org/Roles_and_Capabilities#User_Levels" target="_blank">User Level</a> to execute PHP in posts &amp; pages and hook onto actions and filters.', $this->slug()); ?> 
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php _e('Actions &amp; Filters:', $this->slug()); ?></th>
						<td>
							<p>
								<label>
									<input name="<?php $this->slug(true, true); ?>-hooks" type="checkbox"<?php checked($this->get_option('hooks')); ?> />
									<?php _e('Allow hooking onto <a href="http://codex.wordpress.org/Plugin_API" target="_blank">actions and filters</a> using &quot;Custom Fields&quot;. Only works on posts and pages.', $this->slug()); ?> 
								</label>
							</p>
							<blockquote cite="http://codex.wordpress.org/Plugin_API">
								<p><?php _e('Actions: Actions are the hooks that the WordPress core launches at specific points during execution, or when specific events occur. Your plugin can specify that one or more of its PHP functions are executed at these points, using the Action API.', $this->slug()); ?></p>
							</blockquote>
							<p>action_<em>action_name</em> and action_<em>priority</em>_<em>action_name</em></p>
							<blockquote cite="http://codex.wordpress.org/Plugin_API">
								<p><?php _e('Filters: Filters are the hooks that WordPress launches to modify text of various types before adding it to the database or sending it to the browser screen. Your plugin can specify that one or more of its PHP functions is executed to modify specific types of text at these times, using the Filter API.', $this->slug()); ?></p>
							</blockquote>
							<p>filter_<em>filter_name</em> and filter_<em>priority</em>_<em>filter_name</em></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php _e('Widgets:', $this->slug()); ?></th>
						<td>
							<label>
								<input name="<?php $this->slug(true, true); ?>-widgets" type="checkbox"<?php checked($widgets); ?> />
								<?php _e('Execute PHP in text widgets. Anyone who can add widgets can execute PHP if this option is selected.', $this->slug()); ?> 
							</label>
						</td>
					</tr>
					<tr><td>&nbsp;</td></tr>
					<tr>
						<th><?php _e('Allowed Users:', $this->slug()); ?></th>
						<td class="green"><?php echo implode(', ', $allowed); ?></td>
					</tr>
					<tr>
						<th><?php _e('Banned Users:', $this->slug()); ?></th>
						<td class="red">
							<p style="margin:0;"><?php echo implode(', ', $banned); ?></p>
							<p style="margin:0;"><em class="dashed red" title="<?php _e('Will be added to a future release.', $this->slug()); ?>"><?php _e('Not yet supported.', $this->slug()); ?></em></p>
						</td>
					</tr>
<?php do_action("{$this->slug('hook')}-options-page"); ?>
					<tr><td>&nbsp;</td></tr>
					<tr>
						<th><input type="submit" class="button-primary" name="<?php $this->slug(true, true); ?>-submit" value="<?php _e('Save', $this->slug()); ?>" /></th>
						<td><input type="submit" class="button-primary" name="<?php $this->slug(true, true); ?>-reset" value="<?php _e('Reset', $this->slug()); ?>" onclick="return confirm('<?php _e('WARNING: This will reset ALL options, are you sure want to continue?', $this->slug()); ?>');" /></td>
					</tr>
					<tr><td>&nbsp;</td></tr>
					<tr>
						<th></th>
						<td><?php printf(__('Please support us by <a href="http://twitter.com/?status=I+just+installed+%2$s+WordPress+plugin+%3$s+%%23wordpress" target="_blank">tweeting about this plugin</a>, <a href="%1$s" target="_blank">writing a post about this plugin</a> or <a href="%4$s">donating</a>.', $this->slug()), admin_url('post-new.php'), urlencode($this->_plugin_data('name')), urlencode($this->_plugin_data('shortlink')), $this->_plugin_data('donate', true)); ?></td>
					</tr>
				</table>
			</fieldset>
			<?php wp_nonce_field("{$this->file}_{$this->slug()}_{$this->version()}"); ?>
		</form>
	</div>
<?php
		$this->_comment_tag(false);
	}
}

/**
 * @global object $WP_exec_PHP Creates a new WP_exec_PHP object
 * @since 2.0
 */
$WP_exec_PHP = new WP_exec_PHP();

/**
 * @access public
 * @since 2.0
 *
 * @param string $content
 * @return string Content with PHP executed
 */
if (!function_exists('execute_php')) {
	function execute_php($content = '') {
		global $WP_exec_PHP;
		return $WP_exec_PHP->exec($content);
	}
}

/**
 * @access public
 * @since 2.0
 * 
 * @param string $content
 * @return string Content with PHP removed
 */
if (!function_exists('remove_php')) {
	function remove_php($content = '') {
		global $WP_exec_PHP;
		return $WP_exec_PHP->clean($content);
	}
}

/*************************
 ** DEPRECTED FUNCTIONS **
 *************************/

/**
 * @access private
 * @since 1.0
 * @deprected 2.0
 * @deprected No alternative function available
 * 
 * @param array $matches
 * @return null
 */
function _wp_exec_php_execute($matches) {
	_deprecated_function(__FUNCTION__, '2.0');
	return NULL;
}

/**
 * @access public
 * @since 1.0
 * @deprected 2.0
 * @deprected Use execute_php()
 * 
 * @param string $text
 * @return string
 */
function wp_exec_php_do($text = '') {
	global $WP_exec_PHP;
	_deprecated_function(__FUNCTION__, '2.0', 'execute_php()');
	return $WP_exec_PHP->exec($text);
}

/**
 * @access public
 * @since 1.0
 * @deprected 2.0
 * @deprected Use $WP_exec_PHP->_exec_post()
 * 
 * @param string $text
 * @return string
 */
function wp_exec_php_content($text = '') {
	global $WP_exec_PHP;
	_deprecated_function(__FUNCTION__, '2.0', '$WP_exec_PHP->_exec_post()');
	return $WP_exec_PHP->_exec_post($text);
}

/**
 * @access private
 * @since 1.0
 * @deprected 2.0
 * @deprected No alternative function available
 * 
 * @param array $options
 * @param string $prefix Optional.
 * @return array Returns $options
 */
function wp_exec_php_parse_options($options, $prefix = '') {
	_deprecated_function(__FUNCTION__, '2.0');
	return $options;
}

/**
 * @access private
 * @since 1.0
 * @deprected 2.0
 * @deprected Use $WP_exec_PHP->_options_page()
 * @return void
 */
function wp_exec_php_options_page() {
	global $WP_exec_PHP;
	_deprecated_function(__FUNCTION__, '2.0', '$WP_exec_PHP->_options_page()');
	$WP_exec_PHP->_options_page();
}

/**
 * @access private
 * @since 1.0
 * @deprected 2.0
 * @deprected Use $WP_exec_PHP->_admin_menu()
 * @return void
 */
function wp_exec_php_add_options_page() {
	global $WP_exec_PHP;
	_deprecated_function(__FUNCTION__, '2.0', '$WP_exec_PHP->_admin_menu()');
	$WP_exec_PHP->_admin_menu();
}

/**
 * @access private
 * @since 1.0
 * @deprected 2.0
 * @deprected No alternative function available.
 * @return void
 */
function wp_exec_php_footer() {
	_deprecated_function(__FUNCTION__, '2.0');
}

/**
 * @access private
 * @since 1.0
 * @deprected 2.0
 * @deprected Use $WP_exec_PHP->__construct()
 * @return void
 */
function wp_exec_php_init() {
	global $WP_exec_PHP;
	_deprecated_function(__FUNCTION__, '2.0', '$WP_exec_PHP->__construct()');
	$WP_exec_PHP->__construct();
}
?>