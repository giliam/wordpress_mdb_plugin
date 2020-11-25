( function( blocks, editor, element ) {
	var el = element.createElement;

	blocks.registerBlockType( 'caisse/boutique-tags-list', {
		title: 'Liste des fournisseurs', // The title of block in editor.
		icon: 'money', // The icon of block in editor.
		category: 'common', // The category of block in editor.
		attributes: {
        },
		edit: function() {
            return el(
                'p',
                {},
                'Hello World, step 1 (from the editor).'
            );
        },
        save: function() {
            return el(
                'p',
                {},
                'Hello World, step 1 (from the frontend).'
            );
        },
	} );
} )( wp.blocks, wp.editor, wp.element );

