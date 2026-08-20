/**
 * The editor side of the protected-address block.
 *
 * What the canvas shows is deliberately NOT what the visitor gets. The front end
 * is rendered on the server -- encrypted, per request -- and showing that here
 * would be a wall of base64 that tells the author nothing about the thing they
 * are editing. So the canvas shows the readable form, and says once, quietly,
 * that the address is hidden on the published page.
 *
 * There is no ServerSideRender for the same reason: it would cost a REST round
 * trip on every keystroke to render something unreadable.
 */

import { registerBlockType } from '@wordpress/blocks';
import {
	useBlockProps,
	InspectorControls,
	BlockControls,
} from '@wordpress/block-editor';
// No __experimental* imports here on purpose. The settings screen carries one
// already, and an API that WordPress reserves the right to remove is a poor
// thing to hang an editor block on: when it goes, the block stops loading and
// the post it sits in cannot be edited. The controls below keep their default
// margins instead, which is what stacks them in an inspector panel anyway.
import {
	PanelBody,
	Placeholder,
	TextControl,
	TextareaControl,
	ToolbarButton,
	ToolbarGroup,
	Button,
	Notice,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import metadata from './block.json';
import './index.css';

/**
 * Strips whatever a paste dragged in with it.
 *
 * An address never contains whitespace, so removing all of it is safe and
 * saves the author a puzzle: copy one out of Word or a PDF and it usually
 * arrives with a non-breaking space attached. PHP's trim() does not remove
 * that one, so the address would be rejected on the server and the block would
 * be absent from the published page -- over a character nobody can see.
 *
 * Cleaning it away here means the stored value is always one both sides agree
 * about, whichever field it was typed or pasted into. The warning below then
 * stays for addresses that are genuinely the wrong shape.
 *
 * @param {string} value The address as it arrived.
 *
 * @return {string} The address without invisible characters.
 */
// Written as escapes, not as the characters themselves: a literal
// non-breaking space in the source is invisible to the next reader and
// survives exactly one careless reformat.
const cleanAddress = ( value ) =>
	value.replace( /[\s\u00A0\uFEFF\u200B-\u200D\u2060]+/g, '' );

/**
 * The address as the author should see it while writing.
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    The block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 *
 * @return {Element} The editor markup.
 */
function Edit( { attributes, setAttributes } ) {
	const { address, linkText, subject, body, cc, bcc } = attributes;
	const blockProps = useBlockProps();

	// Kept separate from the attribute so a half-typed address is not stored on
	// every keystroke -- and so the placeholder does not vanish mid-word.
	const [ draft, setDraft ] = useState( '' );
	const [ editing, setEditing ] = useState( false );

	// PHP refuses to render anything it cannot recognise as an address, which
	// is the right call -- there is nothing to protect, and printing it would
	// only publish whatever was typed. But refusing quietly means the block is
	// simply absent from the published page, with the editor still showing it.
	// "My block is gone" is a bad way to find that out, so it is said here.
	//
	// The plugin's own pattern, character for character -- the one in
	// CryptX\Exposure, which is what PHP gates on. Not is_email(): that accepts
	// quotes and slashes in a local part, so it would stay silent about an
	// address the server then refuses. Not a looser guess either, which would
	// warn about addresses that work perfectly well. Both sides have to agree,
	// or the warning is worse than none.
	//
	// cleanAddress() rather than a trim: both fields store through it, so an
	// invisible character pasted in with the address never survives to be
	// judged differently by the two sides.
	const looksLikeAddress =
		address === '' ||
		/^[_a-zA-Z0-9-+]+(\.[_a-zA-Z0-9-+]+)*@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*(\.[a-zA-Z]{2,})$/.test(
			cleanAddress( address )
		);

	const warning = ! looksLikeAddress && (
		<Notice status="warning" isDismissible={ false }>
			{ __(
				'This does not look like an email address, so nothing will appear on the published page. Addresses with accented or non-Latin characters in the domain cannot be protected; write the domain in its punycode form (xn--…) instead.',
				'cryptx'
			) }
		</Notice>
	);

	const inspector = (
		<InspectorControls>
			<PanelBody title={ __( 'Address', 'cryptx' ) }>
				<div className="cryptx-block-fields">
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						type="email"
						label={ __( 'Email address', 'cryptx' ) }
						value={ address }
						onChange={ ( next ) =>
							setAttributes( { address: cleanAddress( next ) } )
						}
					/>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Link text', 'cryptx' ) }
						help={ __(
							'Leave empty to use whatever the CryptX settings say. Fill it in to show something else, such as "Write to us".',
							'cryptx'
						) }
						value={ linkText }
						onChange={ ( next ) =>
							setAttributes( { linkText: next } )
						}
					/>
				</div>
			</PanelBody>
			<PanelBody
				title={ __( 'Prefilled message', 'cryptx' ) }
				initialOpen={ false }
			>
				<div className="cryptx-block-fields">
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Subject', 'cryptx' ) }
						value={ subject }
						onChange={ ( next ) =>
							setAttributes( { subject: next } )
						}
					/>
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __( 'Message', 'cryptx' ) }
						rows={ 3 }
						value={ body }
						onChange={ ( next ) => setAttributes( { body: next } ) }
					/>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Cc', 'cryptx' ) }
						value={ cc }
						onChange={ ( next ) => setAttributes( { cc: next } ) }
					/>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Bcc', 'cryptx' ) }
						help={ __(
							'These travel inside the encrypted link, so they are not readable in the page either.',
							'cryptx'
						) }
						value={ bcc }
						onChange={ ( next ) => setAttributes( { bcc: next } ) }
					/>
				</div>
			</PanelBody>
		</InspectorControls>
	);

	if ( ! address || editing ) {
		return (
			<div { ...blockProps }>
				{ inspector }
				<Placeholder
					icon="email-alt"
					label={ __( 'Protected email address', 'cryptx' ) }
					instructions={ __(
						'The address is replaced before the page is delivered, so a spam bot reading the source finds nothing to collect.',
						'cryptx'
					) }
				>
					<form
						onSubmit={ ( event ) => {
							event.preventDefault();
							setAttributes( {
								address: cleanAddress( draft ),
							} );
							setEditing( false );
						} }
					>
						<TextControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							type="email"
							label={ __( 'Email address', 'cryptx' ) }
							hideLabelFromVision
							placeholder={ __( 'info@example.com', 'cryptx' ) }
							value={ draft || address }
							onChange={ setDraft }
						/>
						<Button
							__next40pxDefaultSize
							variant="primary"
							type="submit"
							disabled={ ! ( draft || address ).trim() }
							accessibleWhenDisabled
						>
							{ __( 'Use this address', 'cryptx' ) }
						</Button>
					</form>
					{ warning }
				</Placeholder>
			</div>
		);
	}

	return (
		<div { ...blockProps }>
			{ inspector }
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						icon="edit"
						title={ __( 'Change the address', 'cryptx' ) }
						onClick={ () => {
							setDraft( address );
							setEditing( true );
						} }
					/>
				</ToolbarGroup>
			</BlockControls>
			{ warning }
			<a
				href="#cryptx-preview"
				onClick={ ( event ) => event.preventDefault() }
			>
				{ /* Through the cleaner, so the canvas shows the string the
				     page will show. Attributes can reach a block without
				     passing a setter -- pasted markup, the post code editor,
				     an import -- and then the two would differ. */ }
				{ linkText || cleanAddress( address ) }
			</a>
		</div>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	// Nothing is saved but the attributes. The markup is built on the server on
	// every request, because it is encrypted with the site's secret -- a saved
	// ciphertext would stop resolving the moment that secret changed.
	save: () => null,
} );
