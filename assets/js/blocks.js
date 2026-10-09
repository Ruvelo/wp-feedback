/**
 * Ruvelo Feedback blocks in the editor. The blocks are rendered in PHP; the
 * editor shows that same output (ServerSideRender) with a few settings in
 * the sidebar. Plain JavaScript with WordPress globals: no build step.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var ToggleControl = wp.components.ToggleControl;
	var SelectControl = wp.components.SelectControl;
	var RangeControl = wp.components.RangeControl;
	var TextControl = wp.components.TextControl;
	var Disabled = wp.components.Disabled;
	var ServerSideRender = wp.serverSideRender;

	function preview( name, attributes ) {
		return el( Disabled, null, el( ServerSideRender, {
			block: name,
			attributes: attributes,
			skipBlockSupportAttributes: true,
		} ) );
	}

	function common( props, extra ) {
		var a = props.attributes;
		var set = props.setAttributes;
		var controls = extra || [];
		if ( a.showNav !== undefined ) {
			controls.push( el( ToggleControl, {
				key: 'nav',
				label: __( 'Show the portal tabs', 'ruvelo-feedback' ),
				help: __( 'Feedback, Roadmap and Changelog, once their pages exist.', 'ruvelo-feedback' ),
				checked: a.showNav,
				onChange: function ( value ) { set( { showNav: value } ); },
				__nextHasNoMarginBottom: true,
			} ) );
		}
		if ( a.showHeading !== undefined ) {
			controls.push( el( ToggleControl, {
				key: 'heading',
				label: __( 'Show the heading', 'ruvelo-feedback' ),
				checked: a.showHeading,
				onChange: function ( value ) { set( { showHeading: value } ); },
				__nextHasNoMarginBottom: true,
			} ) );
		}
		return controls;
	}

	function edit( name, settings ) {
		return function ( props ) {
			var blockProps = useBlockProps();
			var controls = settings ? settings( props ) : common( props );
			return el( 'div', blockProps,
				controls.length ? el( InspectorControls, null, el( PanelBody, { title: __( 'Settings', 'ruvelo-feedback' ) }, controls ) ) : null,
				preview( name, props.attributes )
			);
		};
	}

	function number( props, key, label, min, max ) {
		return el( RangeControl, {
			key: key,
			label: label,
			value: props.attributes[ key ],
			min: min,
			max: max,
			onChange: function ( value ) { var change = {}; change[ key ] = value; props.setAttributes( change ); },
			__next40pxDefaultSize: true,
			__nextHasNoMarginBottom: true,
		} );
	}

	var editors = {
		'ruvelo/feedback-board': function ( props ) {
			return common( props, [
				el( SelectControl, {
					key: 'sort',
					label: __( 'Sort by default', 'ruvelo-feedback' ),
					value: props.attributes.sort,
					options: [
						{ label: __( 'Top', 'ruvelo-feedback' ), value: 'top' },
						{ label: __( 'New', 'ruvelo-feedback' ), value: 'new' },
						{ label: __( 'Trending', 'ruvelo-feedback' ), value: 'trending' },
					],
					onChange: function ( value ) { props.setAttributes( { sort: value } ); },
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true,
				} ),
				number( props, 'perPage', __( 'Requests per page', 'ruvelo-feedback' ), 5, 50 ),
			] );
		},
		'ruvelo/roadmap': function ( props ) {
			return common( props, [ number( props, 'limit', __( 'Requests per column', 'ruvelo-feedback' ), 3, 50 ) ] );
		},
		'ruvelo/changelog': function ( props ) {
			return common( props, [ number( props, 'perPage', __( 'Entries per page', 'ruvelo-feedback' ), 1, 30 ) ] );
		},
		'ruvelo/whats-new': function ( props ) {
			return [
				el( TextControl, {
					key: 'label',
					label: __( 'Label', 'ruvelo-feedback' ),
					placeholder: __( 'What’s new', 'ruvelo-feedback' ),
					value: props.attributes.label,
					onChange: function ( value ) { props.setAttributes( { label: value } ); },
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true,
				} ),
				number( props, 'limit', __( 'Entries in the dropdown', 'ruvelo-feedback' ), 1, 10 ),
			];
		},
		'ruvelo/submit-request': null,
		'ruvelo/feedback-request': function () { return []; },
		'ruvelo/changelog-entry': function () { return []; },
	};

	Object.keys( editors ).forEach( function ( name ) {
		wp.blocks.registerBlockType( name, {
			edit: edit( name, editors[ name ] ),
			save: function () { return null; },
		} );
	} );
}( window.wp ) );
