/**
 * Bloque Gutenberg "Boletines › Formulario".
 */
( function ( blocks, element, i18n, blockEditor, components ) {
	const { registerBlockType } = blocks;
	const { createElement: el, Fragment } = element;
	const { __ } = i18n;
	const { InspectorControls, useBlockProps } = blockEditor;
	const { PanelBody, TextControl, SelectControl, ToggleControl } = components;

	const data  = window.BoletinesBlockData || { lists: [] };
	const listOptions = [{ label: __( 'Por defecto (primera lista)', 'boletines' ), value: 0 }]
		.concat( data.lists.map( function ( l ) { return { label: l.name, value: l.id }; } ) );

	registerBlockType( 'boletines/form', {
		title: 'Boletines › Formulario',
		icon: 'email-alt',
		category: 'widgets',
		attributes: {
			listId:      { type: 'number',  default: 0 },
			title:       { type: 'string',  default: 'Suscríbete a nuestro boletín' },
			description: { type: 'string',  default: 'Recibe nuestras novedades en tu correo.' },
			button:      { type: 'string',  default: 'Suscribirme' },
			showName:    { type: 'boolean', default: true }
		},

		edit: function ( props ) {
			const a = props.attributes;
			const setAttr = props.setAttributes;
			const blockProps = useBlockProps( {
				style: {
					border: '1px solid #e5e7eb',
					borderRadius: '10px',
					padding: '24px',
					background: '#fff',
					maxWidth: '480px',
					margin: '0 auto'
				}
			} );

			return el( Fragment, null,
				el( InspectorControls, null,
					el( PanelBody, { title: __( 'Ajustes del formulario', 'boletines' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Lista destino', 'boletines' ),
							value: a.listId,
							options: listOptions,
							onChange: function ( v ) { setAttr( { listId: parseInt( v, 10 ) || 0 } ); }
						} ),
						el( TextControl, { label: __( 'Texto del botón', 'boletines' ), value: a.button, onChange: function ( v ) { setAttr( { button: v } ); } } ),
						el( ToggleControl, { label: __( 'Mostrar campo Nombre', 'boletines' ), checked: !! a.showName, onChange: function ( v ) { setAttr( { showName: !! v } ); } } )
					)
				),
				el( 'div', blockProps,
					el( 'h3', { style: { margin: '0 0 6px', fontSize: '20px' } },
						el( 'input', {
							type: 'text',
							value: a.title,
							onChange: function ( e ) { setAttr( { title: e.target.value } ); },
							style: { border: 0, background: 'transparent', width: '100%', font: 'inherit' }
						} )
					),
					el( 'p', { style: { margin: '0 0 16px', color: '#6b7280', fontSize: '14px' } },
						el( 'input', {
							type: 'text',
							value: a.description,
							onChange: function ( e ) { setAttr( { description: e.target.value } ); },
							style: { border: 0, background: 'transparent', width: '100%', font: 'inherit', color: '#6b7280' }
						} )
					),
					a.showName && el( 'div', { style: { marginBottom: '12px' } },
						el( 'div', { style: { fontSize: '13px', fontWeight: 500, marginBottom: '4px' } }, __( 'Nombre', 'boletines' ) ),
						el( 'div', { style: { padding: '10px 12px', border: '1px solid #d1d5db', borderRadius: '6px', color: '#9ca3af' } }, '—' )
					),
					el( 'div', { style: { marginBottom: '12px' } },
						el( 'div', { style: { fontSize: '13px', fontWeight: 500, marginBottom: '4px' } }, __( 'Correo electrónico', 'boletines' ) ),
						el( 'div', { style: { padding: '10px 12px', border: '1px solid #d1d5db', borderRadius: '6px', color: '#9ca3af' } }, 'tu@correo.com' )
					),
					el( 'div', { style: { padding: '11px 16px', background: '#2563eb', color: '#fff', borderRadius: '6px', textAlign: 'center', fontWeight: 600 } }, a.button )
				)
			);
		},

		save: function () { return null; } // Render dinámico desde PHP.
	} );

} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.i18n,
	window.wp.blockEditor,
	window.wp.components
);
