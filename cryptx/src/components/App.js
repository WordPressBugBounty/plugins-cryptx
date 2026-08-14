/**
 * The screen itself: loads the schema and values, renders the tabs, saves.
 */

import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Flex,
	FlexItem,
	Notice,
	Spinner,
	__experimentalConfirmDialog as ConfirmDialog,
} from '@wordpress/components';

import Tabs from './Tabs';
import SettingsTab from './SettingsTab';
import HelpTab from './HelpTab';

const HELP_TAB = 'help';

/**
 * Reads the tab from the address so a reload, a bookmark or a shared link all
 * land on the same tab.
 *
 * @param {Array} tabs Known tabs.
 * @return {string} The tab id to show.
 */
function tabFromLocation( tabs ) {
	const requested = new URLSearchParams( window.location.search ).get(
		'tab'
	);
	const known = [ ...tabs.map( ( tab ) => tab.id ), HELP_TAB ];

	return known.includes( requested ) ? requested : tabs[ 0 ]?.id;
}

export default function App() {
	const [ schema, setSchema ] = useState( null );
	const [ values, setValues ] = useState( null );
	const [ savedValues, setSavedValues ] = useState( null );
	const [ activeTab, setActiveTab ] = useState( null );
	const [ notice, setNotice ] = useState( null );
	const [ isSaving, setSaving ] = useState( false );
	const [ loadError, setLoadError ] = useState( null );
	const [ confirmReset, setConfirmReset ] = useState( false );

	useEffect( () => {
		apiFetch( { path: '/cryptx/v1/settings' } )
			.then( ( response ) => {
				setSchema( response.schema );
				setValues( response.values );
				setSavedValues( response.values );
				setActiveTab( tabFromLocation( response.schema.tabs ) );
			} )
			.catch( ( error ) => {
				setLoadError(
					error?.message ||
						__( 'The settings could not be loaded.', 'cryptx' )
				);
			} );
	}, [] );

	const isDirty = useMemo( () => {
		if ( ! values || ! savedValues ) {
			return false;
		}

		return Object.keys( values ).some(
			( key ) => String( values[ key ] ) !== String( savedValues[ key ] )
		);
	}, [ values, savedValues ] );

	// Leaving with unsaved changes is the one mistake this screen can make on
	// the user's behalf, so it is worth the browser's own warning.
	useEffect( () => {
		if ( ! isDirty ) {
			return undefined;
		}

		const warn = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};

		window.addEventListener( 'beforeunload', warn );

		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ isDirty ] );

	// Keep the browser's back button meaningful: each tab is a history entry.
	useEffect( () => {
		const onPopState = () => {
			if ( schema ) {
				setActiveTab( tabFromLocation( schema.tabs ) );
			}
		};

		window.addEventListener( 'popstate', onPopState );

		return () => window.removeEventListener( 'popstate', onPopState );
	}, [ schema ] );

	const selectTab = useCallback( ( id ) => {
		setActiveTab( id );

		const url = new URL( window.location.href );
		url.searchParams.set( 'tab', id );
		window.history.pushState( {}, '', url );
	}, [] );

	const setValue = useCallback( ( key, value ) => {
		setValues( ( previous ) => ( { ...previous, [ key ]: value } ) );
	}, [] );

	const save = useCallback( () => {
		setSaving( true );
		setNotice( null );

		apiFetch( {
			path: '/cryptx/v1/settings',
			method: 'POST',
			data: { values },
		} )
			.then( ( response ) => {
				setValues( response.values );
				setSavedValues( response.values );
				setNotice( { status: 'success', text: response.message } );
			} )
			.catch( ( error ) => {
				setNotice( {
					status: 'error',
					text:
						error?.message ||
						__( 'The settings could not be saved.', 'cryptx' ),
				} );
			} )
			.finally( () => setSaving( false ) );
	}, [ values ] );

	const reset = useCallback( () => {
		setConfirmReset( false );
		setSaving( true );

		apiFetch( { path: '/cryptx/v1/settings/reset', method: 'POST' } )
			.then( ( response ) => {
				setValues( response.values );
				setSavedValues( response.values );
				setNotice( { status: 'success', text: response.message } );
			} )
			.catch( ( error ) => {
				setNotice( {
					status: 'error',
					text:
						error?.message ||
						__( 'The settings could not be reset.', 'cryptx' ),
				} );
			} )
			.finally( () => setSaving( false ) );
	}, [] );

	if ( loadError ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ loadError }
			</Notice>
		);
	}

	if ( ! schema || ! values ) {
		return (
			<div className="cryptx-loading">
				<Spinner />
				<span>{ __( 'Loading the settings…', 'cryptx' ) }</span>
			</div>
		);
	}

	const tabs = [
		...schema.tabs,
		{
			id: HELP_TAB,
			label: __( 'Help', 'cryptx' ),
			description: __(
				'Shortcode, template functions and what changed in each release.',
				'cryptx'
			),
		},
	];

	const current = tabs.find( ( tab ) => tab.id === activeTab ) || tabs[ 0 ];

	return (
		<div className="cryptx-settings">
			<header className="cryptx-settings__header">
				<h1>{ __( 'CryptX', 'cryptx' ) }</h1>
				<p className="cryptx-settings__intro">
					{ __(
						'CryptX hides email addresses in your pages from spam bots while keeping them usable for your visitors.',
						'cryptx'
					) }
				</p>
			</header>

			<Tabs tabs={ tabs } active={ current.id } onSelect={ selectTab } />

			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.text }
				</Notice>
			) }

			<p className="cryptx-settings__tab-description">
				{ current.description }
			</p>

			<main className="cryptx-settings__body">
				{ current.id === HELP_TAB ? (
					<HelpTab />
				) : (
					<SettingsTab
						tab={ current.id }
						fields={ schema.fields }
						values={ values }
						onChange={ setValue }
					/>
				) }
			</main>

			{ current.id !== HELP_TAB && (
				<div
					className={
						'cryptx-settings__actions' +
						( isDirty ? ' is-dirty' : '' )
					}
				>
					<Flex justify="space-between" wrap>
						<FlexItem>
							<Button
								variant="primary"
								onClick={ save }
								isBusy={ isSaving }
								disabled={ isSaving || ! isDirty }
								__next40pxDefaultSize
							>
								{ __( 'Save changes', 'cryptx' ) }
							</Button>
						</FlexItem>
						<FlexItem>
							<Button
								variant="tertiary"
								isDestructive
								onClick={ () => setConfirmReset( true ) }
								disabled={ isSaving }
								__next40pxDefaultSize
							>
								{ __( 'Restore defaults', 'cryptx' ) }
							</Button>
						</FlexItem>
					</Flex>
					{ /* The state is said in words as well as shown by the
					     button, because a disabled button on its own reads as
					     "broken" rather than as "nothing to do". */ }
					<p
						className={
							'cryptx-settings__status' +
							( isDirty ? ' is-dirty' : '' )
						}
						aria-live="polite"
					>
						{ isDirty
							? __( 'You have unsaved changes.', 'cryptx' )
							: __( 'All changes saved.', 'cryptx' ) }
					</p>
				</div>
			) }

			<ConfirmDialog
				isOpen={ confirmReset }
				onConfirm={ reset }
				onCancel={ () => setConfirmReset( false ) }
				confirmButtonText={ __( 'Restore defaults', 'cryptx' ) }
			>
				{ __(
					'This puts every CryptX setting back to its default, on every tab. Addresses already delivered in cached pages keep working. Continue?',
					'cryptx'
				) }
			</ConfirmDialog>
		</div>
	);
}
