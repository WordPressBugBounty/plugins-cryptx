/**
 * The tab bar.
 *
 * Built by hand rather than with TabPanel, because the address has to carry
 * the tab: a bookmark, a reload and a link shared with a colleague should all
 * arrive in the same place. TabPanel keeps that state to itself.
 *
 * On a narrow screen the bar scrolls sideways instead of wrapping, which keeps
 * the tabs on one line and the content where the eye expects it.
 */

import { useEffect, useRef, useState } from '@wordpress/element';

export default function Tabs( { tabs, active, onSelect } ) {
	const listRef = useRef( null );
	const [ atEnd, setAtEnd ] = useState( false );

	// Keep the selected tab in view. Arriving with ?tab=advanced on a phone
	// otherwise showed the first tab underlined off-screen and the last one cut
	// in half, with nothing to say the bar could be scrolled at all.
	useEffect( () => {
		const list = listRef.current;

		if ( ! list ) {
			return undefined;
		}

		// scrollLeft by hand rather than scrollIntoView: the latter walks up the
		// ancestors and scrolls whatever it finds, which on a phone dragged the
		// whole admin page a few pixels sideways every time a tab was picked.
		// This touches nothing but the bar itself.
		const activeTab = list.querySelector( '.is-active' );

		if ( activeTab ) {
			const overflowRight =
				activeTab.offsetLeft +
				activeTab.offsetWidth -
				( list.scrollLeft + list.clientWidth );
			const overflowLeft = list.scrollLeft - activeTab.offsetLeft;

			if ( overflowRight > 0 ) {
				list.scrollLeft += overflowRight + 16;
			} else if ( overflowLeft > 0 ) {
				list.scrollLeft -= overflowLeft + 16;
			}
		}

		// The fade at the right edge announces "there is more". Once the end is
		// reached it would be announcing something untrue, so it is dropped.
		const update = () => {
			const remaining =
				list.scrollWidth - list.scrollLeft - list.clientWidth;
			setAtEnd( remaining <= 1 );
		};

		update();
		list.addEventListener( 'scroll', update, { passive: true } );
		window.addEventListener( 'resize', update );

		return () => {
			list.removeEventListener( 'scroll', update );
			window.removeEventListener( 'resize', update );
		};
	}, [ active, tabs ] );

	/**
	 * Left and right move between tabs, as the tab pattern expects.
	 *
	 * @param {Object} event The keyboard event.
	 * @param {number} index Position of the focused tab.
	 */
	const onKeyDown = ( event, index ) => {
		let next = null;

		if ( event.key === 'ArrowRight' ) {
			next = ( index + 1 ) % tabs.length;
		} else if ( event.key === 'ArrowLeft' ) {
			next = ( index - 1 + tabs.length ) % tabs.length;
		} else if ( event.key === 'Home' ) {
			next = 0;
		} else if ( event.key === 'End' ) {
			next = tabs.length - 1;
		}

		if ( next === null ) {
			return;
		}

		event.preventDefault();
		onSelect( tabs[ next ].id );
		listRef.current?.querySelectorAll( '[role="tab"]' )[ next ]?.focus();
	};

	return (
		<div
			className={ 'cryptx-tabs' + ( atEnd ? ' is-scrolled-end' : '' ) }
			role="tablist"
			ref={ listRef }
		>
			{ tabs.map( ( tab, index ) => {
				const isActive = tab.id === active;

				return (
					<button
						key={ tab.id }
						type="button"
						role="tab"
						id={ `cryptx-tab-${ tab.id }` }
						aria-selected={ isActive }
						aria-controls={ `cryptx-panel-${ tab.id }` }
						tabIndex={ isActive ? 0 : -1 }
						className={
							'cryptx-tabs__tab' +
							( isActive ? ' is-active' : '' )
						}
						onClick={ () => onSelect( tab.id ) }
						onKeyDown={ ( event ) => onKeyDown( event, index ) }
					>
						{ tab.label }
					</button>
				);
			} ) }
		</div>
	);
}
