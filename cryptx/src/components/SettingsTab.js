/**
 * One settings tab: the fields that belong to it, grouped into sections.
 *
 * The appearance tab additionally carries the preview, because that is the one
 * place where seeing the result answers the question faster than reading about
 * it.
 */

import { Card, CardBody, CardHeader } from '@wordpress/components';

import Field, { isVisible } from './Field';
import Preview from './Preview';

const APPEARANCE_TAB = 'appearance';

export default function SettingsTab( { tab, fields, values, onChange } ) {
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
			{ tab === APPEARANCE_TAB && <Preview values={ values } /> }

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
		</div>
	);
}
