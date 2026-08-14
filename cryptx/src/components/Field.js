/**
 * Renders one option from its schema entry.
 *
 * Every branch here maps a type from SettingsSchema.php to a control. The help
 * text is never optional -- it comes from the schema, and the schema requires
 * one for every field.
 */

import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import {
	BaseControl,
	Button,
	RadioControl,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
	__experimentalVStack as VStack,
} from '@wordpress/components';

/**
 * Whether a field's condition is met by the current values.
 *
 * @param {Object} field  The schema entry.
 * @param {Object} values Current values.
 * @return {boolean} True when the field should be shown.
 */
export function isVisible( field, values ) {
	if ( ! field.depends ) {
		return true;
	}

	return Object.entries( field.depends ).every(
		// Loose comparison: a choice value of 1 arrives as a number from the
		// schema but may sit in the form state as the string "1".
		// eslint-disable-next-line eqeqeq
		( [ key, expected ] ) => values[ key ] == expected
	);
}

/**
 * Opens the media library and hands back the chosen attachment.
 *
 * @param {Function} onSelect Receives the attachment id and its preview url.
 */
function openMediaLibrary( onSelect ) {
	if ( ! window.wp?.media ) {
		return;
	}

	const frame = window.wp.media( {
		title: __( 'Choose an image', 'cryptx' ),
		multiple: false,
		library: { type: 'image' },
	} );

	frame.on( 'select', () => {
		const attachment = frame.state().get( 'selection' ).first().toJSON();

		onSelect( attachment.id, attachment.url );
	} );

	frame.open();
}

export default function Field( { field, value, onChange } ) {
	const [ mediaUrl, setMediaUrl ] = useState( null );
	const id = `cryptx-field-${ field.key }`;

	switch ( field.type ) {
		case 'boolean':
			return (
				<ToggleControl
					__nextHasNoMarginBottom
					label={ field.label }
					help={ field.help }
					checked={ !! Number( value ) }
					onChange={ ( checked ) =>
						onChange( field.key, checked ? 1 : 0 )
					}
				/>
			);

		case 'choice': {
			const options = field.choices.map( ( choice ) => ( {
				label: choice.label,
				value: String( choice.value ),
			} ) );

			// Radio buttons show every option and its consequence at a glance,
			// which matters for the security choices. Beyond four they turn
			// into a wall, so those become a select.
			if ( options.length <= 4 ) {
				return (
					<RadioControl
						label={ field.label }
						help={ field.help }
						selected={ String( value ) }
						options={ options }
						onChange={ ( next ) => onChange( field.key, next ) }
					/>
				);
			}

			return (
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ field.label }
					help={ field.help }
					value={ String( value ) }
					options={ options }
					onChange={ ( next ) => onChange( field.key, next ) }
				/>
			);
		}

		case 'integer':
			return (
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					type="number"
					min={ field.min }
					max={ field.max }
					label={ field.label }
					help={ field.help }
					value={ value }
					onChange={ ( next ) => onChange( field.key, next ) }
				/>
			);

		case 'idlist':
			return (
				<TextareaControl
					__nextHasNoMarginBottom
					label={ field.label }
					help={ field.help }
					value={ value }
					rows={ 3 }
					onChange={ ( next ) => onChange( field.key, next ) }
				/>
			);

		case 'color':
			return (
				<BaseControl
					__nextHasNoMarginBottom
					id={ id }
					label={ field.label }
					help={ field.help }
				>
					<div className="cryptx-color">
						{ /* A native colour input works on every device,
						     including the phone's own colour picker, and needs
						     no extra bundle. */ }
						<input
							id={ id }
							type="color"
							value={ value || '#000000' }
							onChange={ ( event ) =>
								onChange( field.key, event.target.value )
							}
						/>
						<TextControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Hex value', 'cryptx' ) }
							hideLabelFromVision
							value={ value }
							onChange={ ( next ) => onChange( field.key, next ) }
						/>
					</div>
				</BaseControl>
			);

		case 'media':
			return (
				<BaseControl
					__nextHasNoMarginBottom
					id={ id }
					label={ field.label }
					help={ field.help }
				>
					<VStack spacing={ 2 } className="cryptx-media">
						{ mediaUrl && (
							<img
								src={ mediaUrl }
								alt=""
								className="cryptx-media__preview"
							/>
						) }
						<div className="cryptx-media__buttons">
							<Button
								variant="secondary"
								__next40pxDefaultSize
								onClick={ () =>
									openMediaLibrary( ( attachmentId, url ) => {
										onChange( field.key, attachmentId );
										setMediaUrl( url );
									} )
								}
							>
								{ Number( value )
									? __( 'Replace image', 'cryptx' )
									: __( 'Choose image', 'cryptx' ) }
							</Button>
							{ !! Number( value ) && (
								<Button
									variant="tertiary"
									isDestructive
									__next40pxDefaultSize
									onClick={ () => {
										onChange( field.key, 0 );
										setMediaUrl( null );
									} }
								>
									{ __( 'Remove', 'cryptx' ) }
								</Button>
							) }
						</div>
					</VStack>
				</BaseControl>
			);

		case 'url':
			return (
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					type="url"
					label={ field.label }
					help={ field.help }
					value={ value }
					onChange={ ( next ) => onChange( field.key, next ) }
				/>
			);

		case 'htmlid':
		case 'string':
		default:
			return (
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ field.label }
					help={ field.help }
					value={ value }
					onChange={ ( next ) => onChange( field.key, next ) }
				/>
			);
	}
}
