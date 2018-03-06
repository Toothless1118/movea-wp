/**
 * Ninja Forms Form Block
 *
 * A block for embedding a Ninja Forms form into a post/page.
 */
( function( blocks, i18n, element, components ) {

	var el = element.createElement, // function to create elements
      // TextControl = blocks.InspectorControls.TextControl, // not needed

      SelectControl = components.SelectControl, // select control
      InspectorControls = blocks.InspectorControls, // sidebar controls
      Sandbox = components.Sandbox; // needed to register the block

	// register our block
	blocks.registerBlockType( 'ninja-forms/form', {
		title: 'Ninja Forms',
		icon: 'feedback',
		category: 'common',

		attributes: {
            formID: {
                type: 'integer',
                default: 0
            },
		},

		edit: function( props ) {

        var focus = props.focus;

        var formID = props.attributes.formID;

        var children = [];

        if( ! formID ) formID = ''; // Default.

		function onFormChange( newFormID ) {
			// updates the form id on the props
			props.setAttributes( { formID: newFormID } );
		}

		// Set up the form dropdown in the side bar 'block' settings
        var inspectorControls = el( InspectorControls, {},
            el( SelectControl, { label: 'Form ID', value: formID, options: ninjaFormsBlock.forms, onChange: onFormChange } )
        );


		/**
		 * Create the div container, add an overlay so the user can interact
		 * with the form in Gutenberg, then render the iframe with form
		 */
		if( '' === formID ) {
			children.push( el( 'div', {style : {width: '100%'}}, el( 'img',
				{ src: ninjaFormsBlock.block_logo}),
				el( SelectControl, { value: formID, options: ninjaFormsBlock.forms, onChange: onFormChange })
			) );
		} else {
			children.push(
				el( 'div', { className: 'nf-iframe-container' },
					el( 'div', { className: 'nf-iframe-overlay' } ),
					el( 'iframe', { src: ninjaFormsBlock.siteUrl + '?nf_preview_form='
						+ formID + '&nf_iframe', height: '0', width: '500', scrolling: 'no' })
				)
			)
		}
		return [
			children,
			// inspectorControls
			!! focus && inspectorControls
        ];
		},

		save: function( props ) {

            var formID = props.attributes.formID;

            if( ! formID ) return '';
			/**
			 * we're essentially just adding a short code, here is where
			 * it's save in the editor
			 *
			 * return content wrapped in DIV b/c raw HTML is unsupported
			 * going forward
			 */
			var returnHTML = '[ninja_forms id=' + parseInt( formID ) + ']';
			return el( 'div', null, returnHTML);
		}
	} );


} )(
	window.wp.blocks,
	window.wp.i18n,
	window.wp.element,
	window.wp.components
);
