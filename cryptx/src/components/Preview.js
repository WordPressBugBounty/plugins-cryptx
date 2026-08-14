/**
 * Shows what the current settings actually produce.
 *
 * The markup is rendered on the server through the same three filters the
 * front end uses, so this is the real result rather than a reconstruction.
 * Both halves matter: what a visitor sees, and what is left in the source for
 * a spam bot to find. The second half is the whole point of the plugin and was
 * previously invisible.
 */

import { useEffect, useRef, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { Card, CardBody, CardHeader, Spinner } from '@wordpress/components';

/**
 * Waits until the user stops changing things before asking the server.
 */
const DEBOUNCE_MS = 400;

/**
 * What the server found in the markup, and how worried to be about it.
 *
 * The middle case is the one worth having: an address written as HTML entities
 * is invisible to a search of the raw source and perfectly visible to anything
 * that decodes entities first. Reporting that as "protected" would be a
 * comforting lie, and this box exists to prevent exactly that.
 */
const VERDICTS = {
	none: {
		tone: 'good',
		text: () =>
			__( 'No readable address is left in the source.', 'cryptx' ),
	},
	encoded: {
		tone: 'warning',
		text: () =>
			__(
				'The address is in the source as HTML entities. That defeats a simple scanner, but any spam bot that decodes entities will read it.',
				'cryptx'
			),
	},
	plain: {
		tone: 'bad',
		text: () =>
			__(
				'An address is still plainly readable in the source with these settings.',
				'cryptx'
			),
	},
};

export default function Preview( { values } ) {
	const [ preview, setPreview ] = useState( null );
	const [ isLoading, setLoading ] = useState( false );
	const [ error, setError ] = useState( null );
	const requestId = useRef( 0 );

	useEffect( () => {
		const handle = setTimeout( () => {
			const id = ++requestId.current;
			setLoading( true );

			apiFetch( {
				path: '/cryptx/v1/preview',
				method: 'POST',
				data: { values },
			} )
				.then( ( response ) => {
					// A slower earlier request must not overwrite a newer
					// answer, or the preview shows the wrong settings.
					if ( id === requestId.current ) {
						setPreview( response );
						setError( null );
					}
				} )
				.catch( ( caught ) => {
					if ( id === requestId.current ) {
						setError(
							caught?.message ||
								__(
									'The preview could not be generated.',
									'cryptx'
								)
						);
					}
				} )
				.finally( () => {
					if ( id === requestId.current ) {
						setLoading( false );
					}
				} );
		}, DEBOUNCE_MS );

		return () => clearTimeout( handle );
	}, [ values ] );

	return (
		<Card className="cryptx-preview">
			<CardHeader>
				<h2 className="cryptx-section__title">
					{ __( 'Preview', 'cryptx' ) }
				</h2>
				{ isLoading && <Spinner /> }
			</CardHeader>
			<CardBody>
				{ error && <p className="cryptx-preview__error">{ error }</p> }

				{ preview && (
					<>
						<div className="cryptx-preview__block">
							<h3>{ __( 'What visitors see', 'cryptx' ) }</h3>
							{ /* The markup comes from our own filters, applied
							     to a fixed sample address. */ }
							<div
								className="cryptx-preview__rendered"
								dangerouslySetInnerHTML={ {
									__html: preview.markup,
								} }
							/>
						</div>

						<div className="cryptx-preview__block">
							<h3>
								{ __(
									'What a spam bot finds in the source',
									'cryptx'
								) }
							</h3>
							<pre className="cryptx-preview__source">
								<code>{ preview.markup }</code>
							</pre>
						</div>

						<p
							className={
								'cryptx-preview__verdict is-' +
								(
									VERDICTS[ preview.exposure ] ||
									VERDICTS.none
								).tone
							}
						>
							{ (
								VERDICTS[ preview.exposure ] || VERDICTS.none
							).text() }
						</p>
					</>
				) }
			</CardBody>
		</Card>
	);
}
