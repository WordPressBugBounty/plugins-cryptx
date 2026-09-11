=== CryptX ===
Contributors: d3395
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=4026696
Tags: antispam, mail, spam protection, email encryption, privacy
Requires at least: 6.7
Tested up to: 7.1
Stable tag: 4.2.1
Requires PHP: 8.1
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
* **False-Positive List** - Endings such as jpeg or png that keep file names like logo@2x.png from being read as addresses
* **Exempt Addresses** - Leave a single address, or a whole domain, exactly as written
* **Per-Post Control** - Enable/disable encryption on individual posts and pages
* **Editor Block** - "Protected email address", with fields for link text, subject, cc and bcc
* **WP-CLI** - `wp cryptx settings` and `wp cryptx scan`, both per site on a network
* **Multisite** - every site keeps its own settings; a network default decides what a new one starts with
* **Shortcode Support** - Use `[cryptx]email@example.com[/cryptx]` for manual encryption
* **Site Health Check** - Reports whether your addresses really are hidden, measured rather than described
* **Template Functions** - Developer-friendly functions for theme integration

[Plugin Homepage](http://weber-nrw.de/wordpress/cryptx/ "Plugin Homepage")

== Screenshots ==

1. Protection: where CryptX looks for addresses, and how it hides them. Every option is explained where you set it.
2. A live preview shows what visitors see and what a spam bot finds in the source, and warns when a setting leaves an address readable.
3. Exceptions: single posts, file names that look like addresses, and feeds.
4. Advanced settings. The defaults are right for almost every site.
5. Help: shortcode, template functions, and what changed in each release.
6. The settings screen on a phone.
7. The "Protected email address" block in the editor, with its link text and prefilled message.

== Installation ==

1. Upload the CryptX folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the plugin settings under Settings > CryptX
4. Your email addresses will now be automatically protected!

== Development ==

The settings screen is built with React and the WordPress component library. The
human-readable source ships with the plugin, so the compiled files can be
rebuilt and compared:

1. `cd` into the plugin directory
2. `npm install`
3. `npm run build`

That reproduces `build/index.js`, `build/index.css`, `build/index-rtl.css` and
`build/index.asset.php` byte for byte from `src/`. The toolchain is
`@wordpress/scripts`; `package.json` and `package-lock.json` are included so the
exact dependency versions are pinned.

The front end script `js/cryptx.min.js` is produced from `js/cryptx.js` with
`terser@5.50.0 -c -m`.

`js/admin-notice.js` has no build step at all. It is twenty lines, it runs only
in the admin, and it ships exactly as written.

== Frequently Asked Questions ==

= How does CryptX protect my email addresses? =

CryptX uses various methods to hide email addresses from spambots while keeping them functional for visitors. Methods include JavaScript encryption, Unicode conversion, and replacing emails with images or custom text.

= Will this affect my website's performance? =

CryptX is designed to be lightweight and only loads JavaScript when needed. The performance impact is minimal.

= Can I exclude certain email addresses from encryption? =

Yes. Under Settings / CryptX / Exceptions there is a list of addresses to leave alone. Write "info@example.com" for a single address, or "@example.com" to cover every address at that domain. CryptX leaves those addresses exactly as written -- it does not mask, link or encrypt them -- which is what you want for an address a helpdesk has to read out of the page, or one shown in a code example.

There are two limits on purpose. Inside `[cryptx]...[/cryptx]` -- and inside the "Protected email address" block, which is the same instruction in a different shape -- nothing is exempt: it says "protect this one, here", and a setting made months ago on another screen is not an answer to that. Where the shortcode is written somewhere WordPress never expands it, such as a hand-written excerpt, this reaches a little further: an exempt address standing next to it in the same text is protected as well. That is the harmless direction, but worth knowing if you exempted an address precisely so a machine could read it. And in comments only a whole address counts, never the domain form -- otherwise exempting your own domain would hand out every address at that domain a visitor happened to leave in a comment. One more thing worth knowing about comments: WordPress itself turns a bare address into a link before CryptX ever sees it, so an exempt address stays readable there but does become a link.

That distinction rests on which filter the text arrives through, and only comments can be told apart with certainty. Forum and front-end submission plugins -- bbPress and BuddyPress among them -- send what a visitor wrote through the same filter as your own posts, so the domain form does apply there. If your site takes text from visitors that way, exempt the individual addresses rather than a whole domain.

Two neighbouring settings answer different questions. The list of endings ("jpeg,jpg,png,gif") is what keeps file names such as logo@2x.png from being mistaken for an address in the first place. The list of post IDs switches CryptX off for a whole post or page.

= What about addresses at an internationalised domain? =

An address such as "post@münchen.de" is not protected -- CryptX looks for addresses using the ASCII form, so it does not recognise one with an accented or non-Latin domain in the first place. Write the domain in its punycode form ("post@xn--mnchen-3ya.de") and everything works as usual. The editor block says so where you type it; elsewhere the address is simply left as it was.

= Does it work on a multisite network? =

Yes, including network activation. Every site keeps its own settings and its own encryption secret, so nothing one site publishes can be read with another site's key. Sites created later are set up the same way as those that existed at activation time, and uninstalling removes the plugin's data from every site in the network.

The settings live per site, because that is where the addresses and the design live. A site administrator configures their own site as usual. Since 4.2.0 a network administrator can set the defaults a newly created site starts with, under Network Admin / Settings / CryptX -- a starting point, not an instruction: sites that already exist are never changed by it, and a site administrator can change theirs at any time.

Two settings are missing from that screen on purpose. The list of excluded post IDs and the uploaded image both refer to things that exist on one site only: post 17 on one site has nothing to do with post 17 on another, and copying that list would exclude the wrong posts -- which is precisely what left addresses unprotected on some networks before 4.1.1.

= Can I use CryptX from the command line? =

Yes, with WP-CLI. `wp cryptx settings` lists every setting with its current value; `wp cryptx settings <name>` reads one and `wp cryptx settings <name> <value>` writes it, through the same validation the settings screen uses.

`wp cryptx scan` is the one worth knowing about. It runs every published post through the filters that render it and reports the ones that still carry a readable address -- the question you actually have after changing a setting, and the one the settings screen cannot answer, because it only ever renders a single sample. An "encoded" verdict means the address is in the page as HTML entities: invisible to a naive scanner, plain to anything that decodes them.

It reads the body and the title of each post, and a "where" column says which of the two. Titles matter here because CryptX cannot protect them: a title goes into the document head through WordPress itself, along a path no plugin filter touches. An address in a post title is readable, and the only fix is to take it out of the title.

What the scan does not cover: widgets, comments, feeds, and anything a theme prints on its own. It is a check on your posts and pages, not a clean bill of health for the whole site.

On a network both take `--url`, so `wp site list --field=url | xargs -I{} wp cryptx scan --url={}` covers the whole network.

= Does it work with contact forms? =

CryptX primarily works with email addresses displayed in content. It doesn't interfere with contact forms or other form functionality.

= Can I disable encryption on specific posts? =

Yes, you can enable the meta box feature to control encryption on individual posts and pages.

For more information, visit the [Plugin Homepage](http://weber-nrw.de/wordpress/cryptx/ "Plugin Homepage")

== Changelog ==
= 4.2.1 =
* **Fixed** with the JavaScript variant switched on, the script is now delivered on every page, not only on pages that themselves carry a protected address. Themes that exchange page content in the browser without rebuilding the document -- swup.js and other client-side routers work that way -- brought protected links onto the screen for which no click handler had ever been loaded. Those links did nothing at all until the visitor reloaded the page in full. It has been that way since 4.0.12, when delivery was made conditional on the page at hand
* **Fixed** the same for the stylesheet with "Instead of the address, show" set to one of the picture options: `css/cryptx.css` is now delivered as soon as a picture variant is configured, so a link that reaches the browser after the page was built is still shown at the right size
* **Fixed** a single click could open the mail program twice wherever a theme or a loader runs the script a second time on the same document. The guard against attaching the click handler twice is now kept on the document rather than inside the script, which also makes `initCryptxLinkHandler(document)` work on a second document, as it was always meant to
* if you run a full-page cache, empty it after the update: pages stored before it do not carry the script yet, and they stay broken until the cache turns over by itself

= 4.2.0 =
* **New** on a multisite network, the network administrator can set the defaults a newly created site starts with, under Network Admin / Settings / CryptX. Sites that already exist are never changed -- every site keeps its own settings, as it has since 4.1.1. Two settings are deliberately not shareable: excluded post IDs and the uploaded image refer to things that exist on one site only, and copying the first of them is exactly what left addresses unprotected before 4.1.1
* **New** WP-CLI: `wp cryptx settings` reads and writes the settings, `wp cryptx scan` runs the body and the title of every published post through the real filters and reports the ones that still carry a readable address -- including addresses in titles, which CryptX cannot protect because a title reaches the page along a path no plugin filter touches. With `--url` both work per site, so a network can be handled from a shell loop rather than from forty screens
* **Fixed** changing "Key strengthening" under Advanced silently broke every link that had already been delivered -- in every cached page and in every browser tab still open. The number of rounds was read from the page's configuration at the moment of the click, not from the link, so a link made with the old value could no longer be opened. Each link now records what it was made with, and the ones written before this update keep working as they did
* **New** the encryption secrets can be replaced, under Advanced. Useful after restoring a backup that may have been seen by somebody else. Links already published keep working, because each carries the key it was made with; the secret behind the picture variant is opened on your server instead, so the replaced one is kept for 30 days and pictures in pages still cached go on working until then
* **Security** with "The address drawn into a picture" selected, the address stood in the web address the picture is fetched under. It was written into the page as HTML entities, which looks hidden and is not -- the browser resolves them before it makes the request, so the address travelled in plain text in the request line of every image load: into your access log, and through every proxy and CDN on the way. The variant meant to hide addresses best handed them to more machines than a plainly written one would have. The picture is now fetched under a token that says nothing about the address, and the alt text no longer carries it either. Pictures in pages that were already cached keep working -- that fallback is a bridge over the lifetime of a page cache and is planned to go in 5.0
* **New** a block for the editor: "Protected email address", with fields for the link text and for a prefilled subject, message, cc and bcc. Until now the only deliberate way to protect one address was to type a shortcode into a paragraph, which works but is invisible in the inserter. The block is rendered on the server on every request, so nothing encrypted is stored in the post -- a saved link would stop working the moment the encryption secret changed
* **New** single addresses can be left alone. A new field under Exceptions takes a list -- "info@example.com" for one address, "@example.com" for every address at a domain -- and CryptX leaves those exactly as written: no masking, no link, no encryption. Until now the answer was that it could not be done, which left no way to keep a helpdesk address machine-readable or an address in a code example intact. Two limits on purpose: inside `[cryptx]...[/cryptx]` nothing is exempt, because a shortcode is a narrower instruction than a setting; and in comments only a whole address counts, not the domain form, so an exempt domain cannot be used to harvest the addresses visitors leave behind
* **Fixed** the marker CryptX leaves behind while it sets a shortcode aside could be written by an author. Whoever typed it -- in a post explaining CryptX, in a code example, or in a comment -- had the set-aside content substituted into their text. The marker now carries a random part per page, so it cannot be typed
* **New** a Site Health check reports whether addresses really are hidden. It runs a test address through the same filters your pages use and judges the result, instead of describing what the settings ought to do. Choices that deliberately leave addresses in the open -- unprotected feeds, excluded posts, a filter switched off -- are listed but do not count against the result
* **New** after a feature update, CryptX asks once whether you would write a review. Once, and with every limit that word implies: a fortnight after the update rather than on the day of it, never after a bugfix release, gone by itself after a month even if you ignore it, and gone for good the moment you decline -- for you, not for your colleagues, who each get their own chance to answer. Nothing is attached to it: no setting unlocked in return, no reminder that comes back later

= 4.1.1 =
* **Fixed** a mailto link carrying a subject lost it, and the address inside the encrypted link was corrupted -- "sales@example.com?subject=Hello" became "sales@example.comsubjectHello" and the link went nowhere. Subject, body, cc and bcc now travel inside the encrypted link (thx to <a href="https://wordpress.org/support/users/pbmedia/">pbmedia</a>)
* **New** the shortcode understands subject, body, cc and bcc: `[cryptx subject="Price enquiry" cc="sales@example.com"]info@example.com[/cryptx]`. The attribute "subject" was accepted and silently discarded before
* **Fixed** cryptx_encrypt() turned markup in the passed content into visible text -- a `<br>` came out as `&lt;br&gt;`. It now keeps what a post may contain and still drops scripts (thx to <a href="https://wordpress.org/support/users/fint/">Fint Studio</a>)
* **Fixed** an address directly following a tag, as in `Contact:<br>info@example.com`, was not linked, while the display text was replaced anyway -- the address disappeared from the page without a working link taking its place
* the setting for the old "javascript:" link format now warns that page builders which run content through wp_kses_post, Elementor's text widget among them, strip the protocol and break every link
* **Fixed** on block themes the shortcode did nothing at all: CryptX runs on render_block, which fires before do_shortcode, so it saw the raw "[cryptx]" text. The address went unlinked but was replaced anyway, and the inserted "[at]"/"[dot]" tore the shortcode apart. Unexpanded shortcodes are now left alone until they are expanded
* **Fixed** a link written "MAILTO:" in capitals kept its href and ended up dead
* the shortcode is left alone only where WordPress expands it afterwards; in comments, excerpts and custom fields it stays visible as text, but the address inside it is obfuscated as before
* **Fixed** a shortcode inside a registered block pattern was left unprotected: core/pattern renders with do_blocks() alone, so nothing came along afterwards to expand it
* **Fixed** with "Leave RSS feeds unprotected" switched off, the feed still carried the address in its `<description>`. A feed is built from its own filters -- the_excerpt_rss and the_content_feed -- and CryptX was on neither
* **Fixed** a very long subject or body produced a link the browser refused to follow: the limit counted characters before encoding, while the browser counts the encoded address. 400 characters of Japanese became more than 3600
* when a mailto link has to be shortened to stay inside the length a browser will follow, whole cc and bcc addresses are dropped rather than cut -- a fragment like "chef@examp" in a header is worse than a missing recipient
* with "Leave RSS feeds unprotected" on, CryptX now leaves feeds alone entirely; the autolink step still rewrote bare addresses there
* **Fixed** every update reset settings it had no business touching: the chosen font fell back to the first available one, the text colour gained another "#" each time -- "#3366ff" became "##3366ff" -- and the encryption secret was discarded, so links on already cached pages stopped resolving. These were one-time migrations from 4.0.12 that ran on every version bump; each is now tied to the version it belongs to
* **Security** on a multisite network, activating the plugin network-wide copied the first site's settings to every other site. The exclusion list came with them, so a post ID excluded on the first site left the post with that ID unprotected on all the others -- addresses in plain text on sites whose administrator had excluded nothing. The encryption secret was copied as well; that matters less, because it is published in every generated link anyway, but it did let one site's key open a stray ciphertext from another. Each site now keeps its own settings
* **New** full multisite support: network activation sets every site up individually, sites created later are handled the same way, deactivation clears the transients of all of them, and errors that only a network administrator can act on are now shown in the network backend
* **Fixed** the front end script declared CONFIG, ITERATIONS, SecureUtils and other very general names in the global scope. A second script using any of them did not overwrite CryptX, it stopped one of the two scripts outright. Everything now lives in a closure; the documented entry points stay where they were and a `window.CryptX` namespace was added
* the link in the plugin list is built from the settings page slug instead of the directory name, so renaming the folder no longer breaks it
* version warnings on activation are shown only to users who can act on them
* removed a registration on "wp_update_post", a hook WordPress does not have; updates were always covered by "wp_insert_post"

= 4.1.0 =
* **New** the settings screen has been rebuilt from scratch: mobile first, with every option explained where you set it
* **New** a live preview shows what visitors see and what a spam bot finds in the source, updated as you change settings -- including a warning when a setting leaves an address readable
* the settings are now grouped by what you want to achieve: Protection, Appearance, Exceptions, Advanced, Help
* the link format and the PBKDF2 iteration count can now be set in the interface; previously they could only be changed in the database
* "Use secure encryption" and "Encryption mode" were two switches for one decision and could contradict each other. They are now a single choice
* switching tabs no longer reloads the page, and the address bar still carries the tab so links and bookmarks keep working
* unsaved changes are kept when switching tabs, and leaving the page warns about them
= 4.0.12 =
* **Security** fixed an issue where a failed PNG request could print PHP warnings into the image stream, disclosing the server path, and where a long request URL could make the plugin allocate hundreds of megabytes -- an unauthenticated way to exhaust the memory limit
* **Security** the exclusion setting "Disable CryptX for this post/page" is now protected by a nonce and a capability check
* **Fixed** "Disable CryptX for this post/page" no longer gets silently cleared. Any save that did not come from the classic editor form -- the REST API, WP-CLI, an autosave, the block editor -- used to drop the post from the exclusion list
* **Performance** the encryption key is now derived once per page instead of once per email address. On a page with 20 addresses in secure mode this cuts about 1.8 seconds of server time
* **Performance** javascript and stylesheet are only loaded when the page actually contains a protected address
* Encrypted links no longer use a "javascript:" URI, which any stricter Content-Security-Policy blocks outright. The payload now travels in data attributes and a click handler takes over. Links already delivered keep working; set the option "link_mode" to "js" to get the old form back
* The encryption password is no longer derived from AUTH_KEY. It is published in the page markup, so it is now a random secret instead. Existing installations keep their stored value
* added uninstall.php -- the plugin option used to stay in the database forever after deletion
* fixed broken markup in the image variant, where the alt attribute was missing its closing quote
* content is no longer lost if a regular expression hits the PCRE backtrack limit
* **Security** the PBKDF2 iteration count from the settings is now validated. A non-numeric or zero value made the front end fatal on every page carrying an address
* **Fixed** a font whose name ends in a letter that also appears in ".ttf" was shown truncated in the settings ("Liberation Seri")
* **Fixed** anchors carrying a ">" inside an attribute value are no longer mangled when the address is encrypted
* **Fixed** the changelog tab no longer breaks if the readme cannot be parsed
* **Fixed** presentation settings were losing a backslash on every save
* declared compatibility with WordPress 7.0
* **Licensing** replaced the bundled fonts Arial, Times New Roman and Verdana with the freely licensed Liberation Sans, Liberation Serif and DejaVu Sans. The previous files were the original Monotype/Microsoft typefaces, whose licence does not allow redistribution inside a GPL package. If you had selected one of them, CryptX falls back to the first available font automatically.
* fixed the plugin version constant, which still read 4.0.10 in version 4.0.11 and therefore kept browsers from loading the updated javascript
* corrected the declared PHP requirement to 8.1, matching the check performed at runtime
* fixed the minimum WordPress version shown in the error notice (said 5.0, checked for 6.7)
* fixed a PHP warning caused by an undefined variable when activating the plugin without a font setting
* the default font is now chosen in a reproducible order instead of depending on the file system
* the shortcode documentation listed the attributes "linktext" and "subject", which were never evaluated. It now describes the attributes that actually work.
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

= 4.2.1 =
Fixes protected links that did nothing on themes which exchange page content in the browser. Nothing to do -- unless you run a full-page cache: empty it, because pages stored before the update do not carry the script yet and stay broken until the cache turns over.

= 4.2.0 =
Contains a security fix: with the picture variant, the address stood in the web address the picture was fetched under, and so in your access log. Also fixes changing "Key strengthening", which until now broke every link already published. Nothing to do.

= 4.1.1 =
Bug fixes. Nothing to do on a single site. On a multisite network activated network-wide before 4.1.1, all sites received the settings of the first. Check each under Settings / CryptX / Exceptions and remove post IDs it never excluded -- those posts show addresses unprotected.


= 4.1.0 =
The settings screen is completely new. Your settings are carried over unchanged; nothing needs to be reconfigured.

= 4.0.12 =
Contains two security fixes; install promptly. Note: "Disable CryptX for this post/page" was silently cleared by any save outside the classic editor. Check your exclusions after updating -- old entries cannot be recovered.


= 4.0.0 =
Major update with improved PHP 8.1+ compatibility, enhanced performance, and modernized codebase. Please test on a staging site first. Minimum requirements: WordPress 6.7+ and PHP 8.1+.

= 3.5.2 =
Bug fixes for activation errors and Elementor compatibility issues.

