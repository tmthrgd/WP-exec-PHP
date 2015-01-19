<?php
/*
Plugin Name: WP exec PHP
Plugin URI: http://tom-thorogood.gotdns.com/plugins/wp-exec-php/
Description: Execute PHP inside posts, pages and text widgets. Use [exec][/exec] to execute PHP.
Version: 1.0
Author: Tom Thorogood
Author URI: http://tom-thorogood.gotdns.com/

Inspired by PHP Exec by Priyadi Iman Nurcahyo
http://priyadi.net/archives/2005/03/02/wordpress-php-exec-plugin/
*/

/*
* NOTICE:
* If you notice any issues or bugs in the plugin please email them to tom.thorogood@ymail.com
* If you make any revisions to and/or re-release this plugin please notify tom.thorogood@ymail.com
*/

/*
* Copyright © 2010 Tom Thorogood (email: tom.thorogood@ymail.com)
* 
* This file is part of "WP exec PHP" Wordpress Plugin.
* 
* "WP exec PHP" is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
* 
* "WP exec PHP" is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
* 
* You should have received a copy of the GNU General Public License
* along with "WP exec PHP". If not, see <http://www.gnu.org/licenses/>.
*/

function _wp_exec_php_execute($matches) {
	$m1 = trim($matches[1]);
	if (!empty($m1)) {
		ob_start();
		eval($matches[1]);
		return ob_get_clean();
	}
}

function wp_exec_php_do($text = '') {
	return preg_replace_callback('/\[exec\](.*)\[\/exec\]/Us', '_wp_exec_php_execute', $text);
}

function wp_exec_php_content($text = '') {
	global $wp_exec_php_options;
	$userdata = get_userdata(get_the_author_meta('ID'));
	if ($userdata && $userdata->user_level >= $wp_exec_php_options['level'])
		return wp_exec_php_do($text);
	else
		return preg_replace('/\[exec\](.*)\[\/exec\]/Us', '', $text);
}

function wp_exec_php_parse_options($options, $prefix = '') {
	global $wp_exec_php_defaults;
	if (isset($options[$prefix . 'level']) && !is_int($options[$prefix . 'level']) && is_numeric($options[$prefix . 'level']))
		$options[$prefix . 'level'] = intval($options[$prefix . 'level']);
	if (isset($options[$prefix . 'level']) && (!$options[$prefix . 'level'] || !is_numeric($options[$prefix . 'level']) || $options[$prefix . 'level'] <= 0 || $options[$prefix . 'level'] >= 9))
		unset($options[$prefix . 'level']);
	$options[$prefix . 'support'] = (isset($options[$prefix . 'support']) && $options[$prefix . 'support']);
	$options[$prefix . 'widget'] = (isset($options[$prefix . 'widget']) && $options[$prefix . 'widget']);
	$return = array();
	foreach($wp_exec_php_defaults as $key => $value) {
		if (isset($options[$prefix . $key]) && /*!empty($options[$prefix . $key]) &&*/ $options[$prefix . $key] != $value)
			$return[$key] = $options[$prefix . $key];
	}
	return $return;
}

function wp_exec_php_options_page() {
	global $wp_exec_php_options;
	if (isset($_POST['wp-exec-php-submit'])) {
		global $wp_exec_php_defaults;
		$opts = wp_exec_php_parse_options($_POST, 'wp-exec-php_');
		$wp_exec_php_options = (($opts && count($opts) >= 1) ? @array_merge($wp_exec_php_defaults, $opts) : $wp_exec_php_defaults);
		if (!$wp_exec_php_options)
			$wp_exec_php_options = $wp_exec_php_defaults;
		update_option('wp-exec-php', maybe_serialize($opts));
		echo "\t" . '<div class="updated"><p>Options saved successfully.</p></div>' . "\n";
	}
?>
	<div class="wrap">
		<h2>WP exec PHP Options</h2>
		<form method="post">
			<fieldset class="options">
				<table style="width: 100%;" cellspacing="2" cellpadding="5" class="editform">
					<tr>
						<th style="width: 20%; font-weight: normal; font-size: 0.9em;" scope="row">User Level:</th>
						<td><input name="wp-exec-php_level" type="text" value="<?php echo $wp_exec_php_options['level']; ?>" size="1" maxlength="1" /> The minimum <a href="http://codex.wordpress.org/User_Levels" target="_blank">user level</a> required to run PHP in posts.</td>
					</tr>
					<tr>
						<th style="width: 20%; font-weight: normal; font-size: 0.9em;" scope="row">Execute in Widgets:</th>
						<td><input name="wp-exec-php_widget" type="checkbox"<?php if ($wp_exec_php_options['widget']) { echo ' checked="checked"'; } ?> /> Wheather or not to execute PHP inside text widgets.</td>
					</tr>
					<tr>
						<th style="width: 20%; font-weight: normal; font-size: 0.9em;" scope="row">Display powered by link:</th>
						<td><input name="wp-exec-php_support" type="checkbox"<?php if ($wp_exec_php_options['support']) { echo ' checked="checked"'; } ?> /> Support the plugin.</td>
					</tr>
				</table>
				<p class="submit"><input type="submit" name="wp-exec-php-submit" value="Save" /></p>
			</fieldset>
		</form>
	</div>
<?php
}

function wp_exec_php_add_options_page() {
	add_options_page('WP exec PHP', 'WP exec PHP', 9, 'wp-exec-php.php', 'wp_exec_php_options_page');
}

function wp_exec_php_footer() {
	echo '<p id="wp-exec-php-footer" style="margin:0 auto 0 auto;font-size:0.8em;padding:0 0 7px 0;text-align:center;">Powered by <a href="http://tom-thorogood.gotdns.com/wordpress-plugins/wp-exec-php/" target="_blank">WP exec PHP</a>.</p>';
}

function wp_exec_php_init() {
	global $wp_exec_php_init, $wp_exec_php_defaults, $wp_exec_php_options;
	if ($wp_exec_php_init)
		return;
	$wp_exec_php_init = true;
	$wp_exec_php_defaults = array('level' => 9, 'support' => true, 'widget' => true);
	$opts = maybe_unserialize(get_option('wp-exec-php'));
	$wp_exec_php_options = (($opts && count($opts) >= 1) ? @array_merge($wp_exec_php_defaults, $opts) : $wp_exec_php_defaults);
	if (!$wp_exec_php_options)
		$wp_exec_php_options = $wp_exec_php_defaults;
	add_action('admin_menu', 'wp_exec_php_add_options_page');
	if ($wp_exec_php_options['support'])
		add_action('wp_footer', 'wp_exec_php_footer', 1);
	add_filter('the_content', 'wp_exec_php_content', 1);
	add_filter('the_excerpt', 'wp_exec_php_content', 1);
	if ($wp_exec_php_options['widget'])
		add_filter('widget_text', 'wp_exec_php_do', 1);
}

$wp_exec_php_defaults = $wp_exec_php_options = $wp_exec_php_init = false;
wp_exec_php_init();
?>