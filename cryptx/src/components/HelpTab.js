/**
 * Reference material: the shortcode, the template functions, the JavaScript
 * helpers and what changed in each release.
 *
 * The per-option explanations deliberately live next to the options rather
 * than here -- this tab is for the things that have no control to sit beside.
 */

import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Card,
	CardBody,
	CardHeader,
	ExternalLink,
} from '@wordpress/components';

function Snippet( { children } ) {
	return (
		<pre className="cryptx-help__snippet">
			<code>{ children }</code>
		</pre>
	);
}

/**
 * One release: its version and what changed in it.
 *
 * @param {Object}  props           Component props.
 * @param {Object}  props.release   The release, as the endpoint delivers it.
 * @param {boolean} props.isCurrent Whether this is the installed version.
 * @return {Element} The rendered release.
 */
function Release( { release, isCurrent } ) {
	return (
		<section className="cryptx-help__release">
			<h3>
				{ release.version }
				{ isCurrent && (
					<span className="cryptx-help__badge">
						{ __( 'installed', 'cryptx' ) }
					</span>
				) }
			</h3>
			<ul>
				{ release.items.map( ( item, index ) => (
					<li
						key={ index }
						// The entries come from readme.txt and were run through
						// wp_kses on the server; they carry links to the support
						// forum.

						dangerouslySetInnerHTML={ { __html: item } }
					/>
				) ) }
			</ul>
		</section>
	);
}

function Changelog() {
	const [ data, setData ] = useState( null );

	useEffect( () => {
		apiFetch( { path: '/cryptx/v1/changelog' } )
			.then( ( response ) => setData( response ) )
			.catch( () => setData( { releases: [], current: '' } ) );
	}, [] );

	if ( data === null ) {
		return <p>{ __( 'Loading…', 'cryptx' ) }</p>;
	}

	const { releases, current } = data;

	if ( ! releases || releases.length === 0 ) {
		return <p>{ __( 'No changelog available.', 'cryptx' ) }</p>;
	}

	const [ newest, ...older ] = releases;

	return (
		<div className="cryptx-help__changelog">
			<Release
				release={ newest }
				isCurrent={ newest.version === current }
			/>

			{ /* Twelve releases printed in full ran to some two thousand pixels
			     and buried the shortcode documentation above them. The one
			     people came for is the newest; the rest are one click away
			     rather than gone. */ }
			{ older.length > 0 && (
				<details className="cryptx-help__older">
					<summary>
						{ sprintf(
							/* translators: %d: number of older releases */
							_n(
								'Show %d earlier release',
								'Show %d earlier releases',
								older.length,
								'cryptx'
							),
							older.length
						) }
					</summary>
					{ older.map( ( release ) => (
						<Release
							key={ release.version }
							release={ release }
							isCurrent={ release.version === current }
						/>
					) ) }
				</details>
			) }
		</div>
	);
}

export default function HelpTab() {
	return (
		<div
			className="cryptx-tab-panel"
			role="tabpanel"
			id="cryptx-panel-help"
			aria-labelledby="cryptx-tab-help"
		>
			<Card className="cryptx-section">
				<CardHeader>
					<h2 className="cryptx-section__title">
						{ __( 'Protect a single address', 'cryptx' ) }
					</h2>
				</CardHeader>
				<CardBody>
					<p>
						{ __(
							'In the editor, look for the "Protected email address" block — it does the same thing with fields instead of syntax, and it is the easier way in. The shortcode below is for everywhere a block cannot go: a widget, a custom field, a theme template.',
							'cryptx'
						) }
					</p>
					<p>
						{ __(
							'Both outrank the two ways of switching CryptX off: they work in posts you excluded, and inside them no address is exempt, however the list under Exceptions reads. That makes them the way to protect one address in otherwise untouched content.',
							'cryptx'
						) }
					</p>
					<Snippet>{ '[cryptx]info@example.com[/cryptx]' }</Snippet>
					<p>
						{ __(
							'Almost any setting from this screen can be overridden for a single shortcode by using its option name, written in lower case. The list of addresses to leave alone is the exception, and deliberately so: a shortcode says "protect this one", so nothing inside it is ever exempt.',
							'cryptx'
						) }
					</p>
					<Snippet>
						{
							'[cryptx opt_linktext="1" alt_linktext="Contact us"]info@example.com[/cryptx]'
						}
					</Snippet>
					<p>
						{ __(
							'On top of the settings, four attributes describe the mail itself. They end up inside the encrypted link, so they stay hidden from spam bots just like the address:',
							'cryptx'
						) }
					</p>
					<Snippet>
						{
							'[cryptx subject="Price enquiry" cc="sales@example.com"]info@example.com[/cryptx]'
						}
					</Snippet>
					<p>
						{ __(
							'Available are subject, body, cc and bcc — the headers RFC 6068 allows in a mailto link. Anything else is dropped. A link you wrote yourself with its own "?subject=" keeps it; the attribute only fills in where nothing is set.',
							'cryptx'
						) }
					</p>
					<p className="cryptx-help__note">
						{ __(
							'The shortcode needs an address between its tags. Written self-closing, as [cryptx subject="…" /], there is nothing to protect and nothing is output — the attributes go nowhere.',
							'cryptx'
						) }
					</p>
					<p className="cryptx-help__note">
						{ __(
							'Until 4.1.0 the attribute "subject" was accepted and then silently discarded, and an address written as "info@example.com?subject=…" lost its subject on the way as well. Both work now. The attribute "linktext", also listed by older versions of this page, never existed — use alt_linktext.',
							'cryptx'
						) }
					</p>
				</CardBody>
			</Card>

			<Card className="cryptx-section">
				<CardHeader>
					<h2 className="cryptx-section__title">
						{ __( 'In a theme', 'cryptx' ) }
					</h2>
				</CardHeader>
				<CardBody>
					<Snippet>
						{
							"<?php echo cryptx_encrypt( 'info@example.com' ); ?>"
						}
					</Snippet>
					<p>
						{ __(
							'Takes an optional array of settings as its second argument, using the same option names as the shortcode.',
							'cryptx'
						) }
					</p>
					<p className="cryptx-help__note">
						{ __(
							'The older function encryptx() still exists but is deprecated and will be removed in a future major release.',
							'cryptx'
						) }
					</p>
				</CardBody>
			</Card>

			<Card className="cryptx-section">
				<CardHeader>
					<h2 className="cryptx-section__title">
						{ __( 'In your own JavaScript', 'cryptx' ) }
					</h2>
				</CardHeader>
				<CardBody>
					<p>
						{ __(
							'For addresses that are added to the page after it loaded, and therefore never passed through the server side filters.',
							'cryptx'
						) }
					</p>
					<Snippet>
						{ `const link = document.createElement( 'a' );
link.href = generateDeCryptXHandler( 'info@example.com' );
link.textContent = 'Contact us';` }
					</Snippet>
				</CardBody>
			</Card>

			<Card className="cryptx-section">
				<CardHeader>
					<h2 className="cryptx-section__title">
						{ __( 'On the command line', 'cryptx' ) }
					</h2>
				</CardHeader>
				<CardBody>
					<p>
						{ __(
							'With WP-CLI installed. The first two read and write the settings, through the same validation this screen uses.',
							'cryptx'
						) }
					</p>
					<Snippet>
						{ `wp cryptx settings
wp cryptx settings opt_linktext
wp cryptx settings disable_rss 0` }
					</Snippet>
					<p>
						{ __(
							'The third is the one worth knowing about: it runs the body and the title of every published post through the filters that render it, and reports the ones that still carry a readable address. That is the question you have after changing a setting, and the preview above cannot answer it — it only ever renders a single sample.',
							'cryptx'
						) }
					</p>
					<Snippet>{ 'wp cryptx scan' }</Snippet>
					<p className="cryptx-help__note">
						{ __(
							'A verdict of "encoded" means the address is in the page as HTML entities. That is invisible to a naive scanner and plain to anything that decodes them, which is most things — it is not the same as "hidden".',
							'cryptx'
						) }
					</p>
					<p className="cryptx-help__note">
						{ __(
							'An address in a post title is always reported, because CryptX cannot protect one: the title reaches the document head through WordPress itself, along a path no plugin filter touches. Take it out of the title. And what the scan does not cover: widgets, comments, feeds, and whatever a theme prints on its own.',
							'cryptx'
						) }
					</p>
					<p>
						{ __(
							'On a multisite network both take --url, so one shell loop covers every site:',
							'cryptx'
						) }
					</p>
					<Snippet>
						{
							'wp site list --field=url | xargs -I{} wp cryptx scan --url={}'
						}
					</Snippet>
				</CardBody>
			</Card>

			<Card className="cryptx-section">
				<CardHeader>
					<h2 className="cryptx-section__title">
						{ __( 'What changed', 'cryptx' ) }
					</h2>
				</CardHeader>
				<CardBody>
					<Changelog />
					<p>
						<ExternalLink href="https://wordpress.org/plugins/cryptx/#developers">
							{ __( 'Full history on wordpress.org', 'cryptx' ) }
						</ExternalLink>
					</p>
				</CardBody>
			</Card>
		</div>
	);
}
