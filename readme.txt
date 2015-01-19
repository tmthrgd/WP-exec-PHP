=== WP exec PHP ===
Contributors: TheTomThorogood
Donate link: http://xenthrax.com/donate/
Tags: PHP,execute,posts,pages,widgets,text widgets,editor
Requires at least: 2.7
Tested up to: 3.5.1
Stable tag: 2.0.1

Execute PHP inside posts, pages and text widgets. Add filters and actions using custom feilds.

== Description ==

Execute PHP inside posts, pages and text widgets. Add filters and actions using custom feilds. Use `<?php .... ?>` to execute PHP.

Inspired by [PHP Exec](http://priyadi.net/archives/2005/03/02/wordpress-php-exec-plugin "") by Priyadi Iman Nurcahyo.

== Installation ==

1. Upload `/wp-exec-php/` to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.

== Changelog ==

= 2.0.1 =
* Removed arguments from hook wp-exec-php-options-page.
* Fixed breaking comment bug.
* Added `<label>`'s on options page.

= 2.0 =
* Complete rewrite of the plugin.
* Moved from `[exec][/exec]` to `<?php ?>`.
* Hooking onto actions and filters using "Custom Fields".

= 1.0 =
* Initial Release.

== Upgrade Notice ==

= 2.0.1 =
Fixed breaking comment bug.

= 2.0 =
Complete rewrite.

`<?php code(); ?>`