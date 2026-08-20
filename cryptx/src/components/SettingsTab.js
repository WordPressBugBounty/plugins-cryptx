/**
 * One settings tab: the fields that belong to it, grouped into sections.
 *
 * Two tabs carry something that is not a setting: Appearance shows the preview,
 * because seeing the result answers the question faster than reading about it,
 * and Advanced offers to replace the encryption secrets, because that is an
 * action and belongs where somebody would look for it.
 */

import { Card, CardBody, CardHeader } from '@wordpress/components';

import Field, { isVisible } from './Field';
import Preview from './Preview';
import Secrets from './Secrets';

const APPEARANCE_TAB = 'appearance';
const ADVANCED_TAB = 'advanced';

export default function SettingsTab( {
	tab,
	fields,
	values,
	onChange,
	rotatedAt,
	isNetwork = false,
} ) {
	const forThisTab = fields.filter(
		( field ) => field.tab === tab && isVisible( field, values )
	);

	// Sections keep their order of first appearance in the schema, so the
	// schema file reads in the same order as the screen.
	const sections = [];
	forThisTab.forEach( ( field ) => {
		const existing = sections.find(
			( section ) => section.title === field.section
		);

		if ( existing ) {
			existing.fields.push( field );
		} else {
			sections.push( { title: field.section, fields: [ field ] } );
		}
	} );

	return (
		<div
			className="cryptx-tab-panel"
			role="tabpanel"
			id={ `cryptx-panel-${ tab }` }
			aria-labelledby={ `cryptx-tab-${ tab }` }
		>
			{ /* Neither belongs on the network defaults: the preview renders
			     with THIS site's secret and settings, and the secrets are per
			     site by design -- there is no network-wide one to replace. */ }
			{ tab === APPEARANCE_TAB && ! isNetwork && (
				<Preview values={ values } />
			) }

			{ sections.map( ( section ) => (
				<Card key={ section.title } className="cryptx-section">
					<CardHeader>
						<h2 className="cryptx-section__title">
							{ section.title }
						</h2>
					</CardHeader>
					<CardBody>
						<div className="cryptx-section__fields">
							{ section.fields.map( ( field ) => (
								<div key={ field.key } className="cryptx-field">
									<Field
										field={ field }
										value={ values[ field.key ] }
										onChange={ onChange }
									/>
								</div>
							) ) }
						</div>
					</CardBody>
				</Card>
			) ) }

			{ tab === ADVANCED_TAB && ! isNetwork && (
				<Secrets rotatedAt={ rotatedAt } />
			) }
		</div>
	);
}
