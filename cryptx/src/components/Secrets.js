/**
 * Replacing the two secrets CryptX keeps.
 *
 * Sits on the Advanced tab for the same reason the preview sits on Appearance:
 * it is an action, not a setting, and it belongs where somebody would look for
 * it rather than in a row of switches.
 *
 * The text carries the one thing that decides whether pressing it is safe, and
 * it is not obvious: links already published go on working for ever, because
 * the key travels inside them. Pictures do not -- their token is opened on the
 * server -- so the replaced secret is kept for a while and the screen says
 * until when.
 */

import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

export default function Secrets( { rotatedAt } ) {
	const [ isBusy, setBusy ] = useState( false );
	const [ result, setResult ] = useState( null );
	// The date arrives ready-formatted from the server. Formatting it here
	// would use the reader's time zone and language instead of the site's, and
	// the success message right above it is formatted with wp_date() -- two
	// dates in the same card disagreeing by a day, on a card whose job is to
	// make an unexpected date stand out.
	const [ lastRotated, setLastRotated ] = useState( rotatedAt || '' );
	const [ confirming, setConfirming ] = useState( false );

	const rotate = () => {
		setBusy( true );
		setConfirming( false );

		apiFetch( { path: '/cryptx/v1/secrets/rotate', method: 'POST' } )
			.then( ( response ) => {
				setResult( { status: 'success', text: response.message } );
				setLastRotated( response.secretsRotatedAt || '' );
			} )
			.catch( ( error ) =>
				setResult( {
					status: 'error',
					text:
						error?.message ||
						__( 'The secrets could not be replaced.', 'cryptx' ),
				} )
			)
			.finally( () => setBusy( false ) );
	};

	return (
		<Card className="cryptx-section">
			<CardHeader>
				<h2 className="cryptx-section__title">
					{ __( 'Encryption secrets', 'cryptx' ) }
				</h2>
			</CardHeader>
			<CardBody>
				<p>
					{ __(
						"CryptX keeps two secrets. One is used to encrypt the links and travels inside each of them, so that a visitor's browser can open it. The other never leaves your server and stands in for the address when one is drawn as a picture.",
						'cryptx'
					) }
				</p>
				<p>
					{ __(
						'Replacing them is safe and rarely necessary — after restoring a backup that may have been seen by someone else, for instance. Links that are already published keep working, because each one carries the key it was made with. Pictures are opened on the server, so the old secret is kept for 30 days and the pictures in pages that are still cached go on working until then. Only one old secret is kept, so replacing them a second time inside those 30 days ends the grace period for the first — which is also how you cut it short deliberately, if the old secret needs to stop working now.',
						'cryptx'
					) }
				</p>

				{ lastRotated && (
					<p className="cryptx-secrets__last">
						{ sprintf(
							/* translators: %s: a date */
							__( 'Last replaced on %s.', 'cryptx' ),
							lastRotated
						) }
					</p>
				) }

				{ result && (
					<Notice
						status={ result.status }
						isDismissible={ false }
						className="cryptx-secrets__notice"
					>
						{ result.text }
					</Notice>
				) }

				{ confirming ? (
					<div className="cryptx-secrets__confirm">
						<p>{ __( 'Replace both secrets now?', 'cryptx' ) }</p>
						<Button
							variant="primary"
							isDestructive
							onClick={ rotate }
							isBusy={ isBusy }
							disabled={ isBusy }
							__next40pxDefaultSize
						>
							{ __( 'Yes, replace them', 'cryptx' ) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ () => setConfirming( false ) }
							disabled={ isBusy }
							__next40pxDefaultSize
						>
							{ __( 'Cancel', 'cryptx' ) }
						</Button>
					</div>
				) : (
					<Button
						variant="secondary"
						onClick={ () => setConfirming( true ) }
						disabled={ isBusy }
						__next40pxDefaultSize
					>
						{ __( 'Create new secrets', 'cryptx' ) }
					</Button>
				) }
			</CardBody>
		</Card>
	);
}
