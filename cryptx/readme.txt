=== CryptX ===
Contributors: d3395
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=4026696
Tags: antispam, mail, spam protection, email encryption, privacy
Requires at least: 6.7
Tested up to: 6.9
Stable tag: 4.0.11
Requires PHP: 8.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

No more SPAM by spiders scanning your site for email addresses!

== Description ==

No more SPAM by spiders scanning your site for email addresses. With CryptX you can hide all your email addresses, with and without a mailto-link, by converting them using javascript or UNICODE.

CryptX protects your email addresses from spambots while keeping them readable and functional for your visitors. The plugin automatically detects email addresses in your content and encrypts them using various methods including JavaScript encryption, Unicode conversion, and image replacement.

**Key Features:**

* **Automatic Email Detection** - Finds and encrypts email addresses in posts, pages, comments, and widgets
* **Multiple Encryption Methods** - JavaScript, Unicode, image replacement, and custom text options
* **Widget Support** - Works with text widgets and other widget content
* **RSS Feed Control** - Option to disable encryption in RSS feeds
* **Whitelist Support** - Exclude specific domains from encryption
* **Per-Post Control** - Enable/disable encryption on individual posts and pages
* **Shortcode Support** - Use `[cryptx]email@example.com[/cryptx]` for manual encryption
* **Template Functions** - Developer-friendly functions for theme integration

[Plugin Homepage](http://weber-nrw.de/wordpress/cryptx/ "Plugin Homepage")

== Screenshots ==

1. Plugin settings - General configuration options
2. Email encryption methods and display options
3. Advanced settings and whitelist configuration

== Installation ==

1. Upload the CryptX folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the plugin settings under Settings > CryptX
4. Your email addresses will now be automatically protected!

== Frequently Asked Questions ==

= How does CryptX protect my email addresses? =

CryptX uses various methods to hide email addresses from spambots while keeping them functional for visitors. Methods include JavaScript encryption, Unicode conversion, and replacing emails with images or custom text.

= Will this affect my website's performance? =

CryptX is designed to be lightweight and only loads JavaScript when needed. The performance impact is minimal.

= Can I exclude certain email addresses from encryption? =

Not directly; currently, specific email addresses cannot be excluded. It is possible to add individual posts/pages to the exclusion list using their ID. These pages/posts will then not be processed by CryptX.

= Does it work with contact forms? =

CryptX primarily works with email addresses displayed in content. It doesn't interfere with contact forms or other form functionality.

= Can I disable encryption on specific posts? =

Yes, you can enable the meta box feature to control encryption on individual posts and pages.

For more information, visit the [Plugin Homepage](http://weber-nrw.de/wordpress/cryptx/ "Plugin Homepage")

== Changelog ==
= 4.0.11 =
* fixed a bug in the deprecated "encryptx" function (thx to <a href="https://wordpress.org/support/users/hillyfov/">Machtnix</a>)
= 4.0.10 =
* fixed a <a href="https://wordpress.org/support/topic/fatal-typeerror-in-processwidgetcontent/">bug</a> in CryptX\CryptX::processWidgetContent() (thx to <a href="https://wordpress.org/support/users/mkoscher/">mkoscher</a>)
* added support for themes with block support
= 4.0.9 =
* A bug in the "cryptx_encrypt" function has been fixed, where attributes became unusable due to multiple escaping.
* fixed a bug where existing css ids and classes were overwritten
* removed unused class methods for cleaner code
= 4.0.8 =
* fixed a bug with _wpnonce check
= 4.0.7 =
* added more sanitization for more security
= 4.0.6 =
* added more sanitization for more security
= 4.0.5 =
* **Security Fix** fixed issue with XSS vulnerability
* **DEPRECATED** Due to the WordPress Plugin Checker, the template function 'encryptx' is deprecated and will be removed in the next release. The new function 'cryptx_encrypt' should be used instead.
* changed some variable names and added more sanitization to pass most as possible of the plugin checks (https://wordpress.org/plugins/plugin-check/)
= 4.0.4 =
* fixed issue of not loading new javascript if client has cached an old version.
= 4.0.3 =
* added option for PBKDF2 iterations to choose between more security or less performance impact (Thx to Alexander for hinting me)
= 4.0.2 =
* minor fix: changed the priority from the auto link filter back to 11 from 10 (Thx to Alexander: https://wordpress.org/support/topic/4-0-0-breaks-cryptx-in-custom-shortcode-output/)
= 4.0.1 =
* The "encryptx" function was mistakenly removed during code cleanup. The function has now been added back. (Thx to Jan: https://wordpress.org/support/topic/version-4-breaks-because-of-undefined-function-encryptx/)
= 4.0.0 =
* **Major Update**: Complete code refactoring and modernization
* Improved PHP 8.1+ compatibility and performance
* Enhanced plugin architecture with better separation of concerns
* Improved widget filtering and universal widget support
* Better error handling and debugging capabilities
* Updated minimum requirements: WordPress 6.7+ and PHP 8.1+
* Improved security and code quality
* Enhanced admin interface and settings organization
* Better handling of complex HTML structures and multiline content
= 3.5.2 =
* Fixed a bug where activating CryptX for the first time caused a PHP Fatal error
* Fixed a bug that caused CryptX email addresses in multi-line code, e.g. in an Elementor button with a mailto-link as the target address, to not be recognized correctly and to be converted incorrectly.
= 3.5.1 =
* fixed a bug with missing function
= 3.5.0 =
* Parts of the code have been rewritten to make the plugin more maintainable.
* fixed some bugs
* added option to disable CryptX on RSS feeds (requested: https://wordpress.org/support/topic/cryptx-should-be-disabled-for-rss-content/)
* Added new Javascript function to add CryptX mailto links via javascript on client side (requested: https://wordpress.org/support/topic/javascript-function-to-encrypt-emails/)
= 3.4.5.3 =
* fixed a Critical error in combination with WPML
= 3.4.5.2 =
* fixed that mails are always displayed in this way: name [at] domain [dot] tld
= 3.4.5.1 =
* forgot to set the default value of the $args argument from encryptx function
= 3.4.5 =
* The "encryptx" template function has been revised so that it accepts arguments again, as in previous versions.
= 3.4.4 =
* changed type hinting of an argument to be string or null on some methods
= 3.4.3 =
* fixed a bug in the cryptx shortcode handler. (special thx to: <a href="https://wordpress.org/support/users/jamminjames/">jamminjames</a>,<a href="https://wordpress.org/support/users/basicweb/">basicweb</a>)
= 3.4.2 =
* changed WordPress required version in the plugin meta data
= 3.4.1 =
* changed some method declarations to be compatible with older PHP versions
= 3.4 =
* main code rewritten as class to prevent problems with WordPress or other plugin functions.
* added documentation blocks to class methods for better readability.
* renamed methods for better readability.
* fixed some bugs
= 3.3.3.2 =
* fixed the "Double Slashes in cryptx-asset-URL" issue
= 3.3.3.1 =
* trouble with SVN :(
= 3.3.3 =
* fixed some issues with PHP 8
= 3.3.2 =
* re-added the $args argument to the template function 'encryptx' with some changes.
= 3.3.1 =
* fixed a bug which causes a PHP Warning: call_user_func_array(). Sorry for this.
= 3.3.0 =
* new design of the settings page
* added plus sign (+) to autolink function
* added value check while saving the settings
* changed image replacement for the link text with WordPress media selector, so every image from the media library can now be used and will not be deleted by updates
* changed color input field for PNG image creation to WordPress color picker
* removed some unused code/files
* removed $args from template function 'enctrypx'
* documentation in progress ;)
= 3.2.18 =
* fixed compatibility problems with Shariff Wrapper, which mailto-links doesn't contain an email address.
= 3.2.17 =
* bug fixing and performance improvements. (Thanks to <a href="https://profiles.wordpress.org/mkwprel">mkwprel</a>)
= 3.2.16 =
* "Notice: Only variables should be passed by reference in..." fixed
= 3.2.15 =
* added whitelist of extension to solve the retina filename issue.
= 3.2.14 =
* fixed a bug in combination with retina images @2x (thx to <a href="https://wordpress.org/support/users/stuwetueho/">StuWeTueHo</a>)
* regex expression improvements (thx to <a href="https://wordpress.org/support/users/leitner/">Leitner</a>)
= 3.2.12 =
* fixed a bug in generating the CryptX hash value
= 3.2.11 =
* fixed a bug in javascript
= 3.2.10 =
* added a blacklist of chars which never should be used as javascript encryption hash
= 3.2.9 =
* fixed the single quote bug in javascript encryption
= 3.2.8 =
* minor bug fixes
= 3.2.7 =
* the javascript will be loaded only if really needed!
= 3.2.6 =
* bug fix!!!
= 3.2.5 =
* changed the way to include the javascript. Now using wp_enque_script() !
= 3.2.4 =
* minor bug fixed
= 3.2.3 =
* minor bugs fixed
* added support for wordpress multisites
= 3.2.2 =
* minor bugs fixed
* deprecated template function 'cryptx' removed
= 3.2.1 =
* fixed a bug at the installed plugins page (Thx to Ben)
= 3.2 =
* fixed many bugs
* added new template function encrypts()
* added experimental support for custom fields
= 3.1.2 =
* fixed a bug in the template function (should now work without errors)
= 3.1.1 =
* added support for subject information in the template function
* added some missing translation strings
= 3.1 =
* added support for custom fields
* removed the vertical-align for the generated image. The alignment should be done by css with the class 'cryptxImage'.
= 3.0 =
* huge parts of code rewritten to fix some problems. (Thx to Harald Bertels)
= 2.8 =
* complete code review! All errors shown with WP_DEBUG where fixed.
= 2.7.1 =
* bug fixing with some php installations (thx to Norman Rzepka)
= 2.7 =
* added the shortcode [cryptx]...[/cryptx]! The shortcode was implemented for posts and pages, where CryptX was switched off.
= 2.6.6 =
* fixed a bug in the template function. (thx to Jessica for reporting the bug)
= 2.6.5 =
* fixed a missing slash at the end of the image tag.
= 2.6.4 =
* fixed a bug with some php versions.
= 2.6.3 =
* some bugs are fixed, e.g. the non functional "add mailto checkbox" on the option page.
= 2.6.2 =
* added the option to choose where the needed javascript is loaded (header/footer)
= 2.6.1 =
* bugfix for the autolink function ( see comment: http://weber-nrw.de/wordpress/cryptx/comment-page-7/#comment-415 )
= 2.6.0 =
* Added new feature to convert email adress into an image
= 2.5.1 =
* Added Option to disabled/enable the CryptX Widget on editing a post or page.
= 2.5.0 =
* Changed the location to store the disabled per post/page option from postmeta to CryptX Options. This should keep the postmeta fields clean.
= 2.4.6 =
* added support for ssl-secured sites
= 2.4.5 =
* added support for mailto links without email adress, like a link from "Sociable"
= 2.4.4 =
* added support for widgets
* added information how to implement CryptX in your template
= 2.4.3 =
* added support for content provided by shortcodes like "WP-Table Reloaded"
= 2.4.2 =
* missed to delete my internal Debug function :-(
= 2.4.1 =
* Changed routine in the new Option if Custom Field not exist.
= 2.4.0 =
* Add Option to disable CryptX on single post/page

== Upgrade Notice ==

= 4.0.0 =
Major update with improved PHP 8.1+ compatibility, enhanced performance, and modernized codebase. Please test on a staging site first. Minimum requirements: WordPress 6.7+ and PHP 8.1+.

= 3.5.2 =
Bug fixes for activation errors and Elementor compatibility issues.

