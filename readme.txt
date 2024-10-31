=== Recommat for WooCommerce ===
Contributors: joetsuihk
Donate link: http://recommat.com/
Tags: woocommerce, recommat, machine learning
Requires at least: 5.6
Tested up to: 5.7
Requires PHP: 5.6
Stable tag: 1.0
WC requires at least: 4.5
WC tested up to: 5.9
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Product recommendation based on order data

== Description ==

This plugin takes in the past order information and learn, recommend "frequently bought together" products

Notes:

* as woocommerce's product variation behaviour, it will link up the "parent" of the product instead of the varient

== Server requirements ==
- install redis lib for php - https://github.com/phpredis/phpredis/blob/develop/INSTALL.markdown

== Installation ==
1. move this folder to `wp-content/plugins`
2. Activate the plugin
3. get access to a redis instance
  - You can get a free redis in https://redis.com/
4. Enter the redis connection info in plugin's settings page
5. Done

== Frequently Asked Questions ==

= "Fatal error: Class 'Redis' not found" =

Please install php-redis lib for your apache/nginx: https://github.com/phpredis/phpredis/blob/develop/INSTALL.markdown

== Screenshots ==
1. Settings page
2. Report page

== Changelog ==

= 1.0 =
* First stable release


== Upgrade Notice ==

= 1.0 =
None.