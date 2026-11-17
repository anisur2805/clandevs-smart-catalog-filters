/**
 * Clandevs Smart Catalog Filters Pro — Block editor settings panel.
 */
(function (blocks, element, blockEditor, components, i18n, serverSideRender) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var ToggleControl = components.ToggleControl;
	var SelectControl = components.SelectControl;
	var RangeControl = components.RangeControl;
	var ServerSideRender = serverSideRender;

	var categories = (window.cscfpBlock && window.cscfpBlock.categories) || [
		{ label: __('All categories', 'clandevs-smart-catalog-filters'), value: '' }
	];

	blocks.registerBlockType('cscf/catalog-filters', {
		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			var inspector = el(
				InspectorControls,
				{},
				el(
					PanelBody,
					{ title: __('Layout', 'clandevs-smart-catalog-filters'), initialOpen: true },
					el(SelectControl, {
						label: __('Layout', 'clandevs-smart-catalog-filters'),
						value: attributes.layout,
						options: [
							{
								label: __('Sidebar', 'clandevs-smart-catalog-filters'),
								value: 'sidebar'
							},
							{
								label: __('Horizontal', 'clandevs-smart-catalog-filters'),
								value: 'horizontal'
							}
						],
						onChange: function (value) {
							setAttributes({ layout: value });
						}
					}),
					el(RangeControl, {
						label: __('Columns', 'clandevs-smart-catalog-filters'),
						value: attributes.columns,
						min: 1,
						max: 6,
						onChange: function (value) {
							setAttributes({ columns: value });
						}
					}),
					el(RangeControl, {
						label: __('Products per page', 'clandevs-smart-catalog-filters'),
						value: attributes.perPage,
						min: 1,
						max: 48,
						onChange: function (value) {
							setAttributes({ perPage: value });
						}
					})
				),
				el(
					PanelBody,
					{ title: __('Filters', 'clandevs-smart-catalog-filters'), initialOpen: false },
					el(ToggleControl, {
						label: __('Show filter sidebar', 'clandevs-smart-catalog-filters'),
						checked: !!attributes.showFilters,
						onChange: function (value) {
							setAttributes({ showFilters: value });
						}
					}),
					el(ToggleControl, {
						label: __('Show pagination', 'clandevs-smart-catalog-filters'),
						checked: !!attributes.showPagination,
						onChange: function (value) {
							setAttributes({ showPagination: value });
						}
					}),
					el(SelectControl, {
						label: __('Limit to category', 'clandevs-smart-catalog-filters'),
						value: attributes.category,
						options: categories,
						onChange: function (value) {
							setAttributes({ category: value });
						}
					}),
					el(
						'p',
						{ className: 'components-base-control__help' },
						__(
							'Individual filter sections are toggled under Smart Catalog Filters → Filter Settings.',
							'clandevs-smart-catalog-filters'
						)
					)
				)
			);

			var preview = el(ServerSideRender, {
				block: 'cscf/catalog-filters',
				attributes: attributes
			});

			var blockProps = useBlockProps ? useBlockProps() : {};

			return el('div', blockProps, inspector, preview);
		},

		// Rendered by the PHP render_callback.
		save: function () {
			return null;
		}
	});
})(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n,
	window.wp.serverSideRender
);
