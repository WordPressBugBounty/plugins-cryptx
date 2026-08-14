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
							'The shortcode works even in posts you excluded from CryptX, which makes it the way to protect one address in otherwise untouched content.',
							'cryptx'
						) }
					</p>
					<Snippet>{ '[cryptx]info@example.com[/cryptx]' }</Snippet>
					<p>
						{ __(
							'Any setting from this screen can be overridden for a single shortcode by using its option name, written in lower case:',
							'cryptx'
						) }
					</p>
					<Snippet>
						{
							'[cryptx opt_linktext="1" alt_linktext="Contact us"]info@example.com[/cryptx]'
						}
					</Snippet>
					<p className="cryptx-help__note">
						{ __(
							'Older versions of this page listed attributes called "linktext" and "subject". Neither was ever evaluated; they were silently discarded.',
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
