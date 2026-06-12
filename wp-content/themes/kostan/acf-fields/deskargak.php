<?php
/**
 * Register ACF field group for the Deskargak block.
 *
 * @package Kostan
 */

if ( ! function_exists( 'acf_add_local_field_group' ) ) {
	return;
}

acf_add_local_field_group([
	'key'      => 'group_deskargak_item',
	'title'    => 'Deskargak Item',
	'fields'   => [
		[
			'key'           => 'field_deskargak_file',
			'label'         => 'Archivo',
			'name'          => 'file',
			'type'          => 'file',
			'instructions'  => 'Selecciona el archivo para este idioma.',
			'return_format' => 'array',
			'library'       => 'all',
			'wpml_cf_preferences' => 3,
		],
		[
			'key'          => 'field_deskargak_title',
			'label'        => 'Título visible',
			'name'         => 'title',
			'type'         => 'text',
			'instructions' => 'Opcional. Si se deja vacío se usa el título del archivo.',
			'wpml_cf_preferences' => 2,
		],
		[
			'key'          => 'field_deskargak_type',
			'label'        => 'Tipo de archivo',
			'name'         => 'type',
			'type'         => 'select',
			'choices'      => [
				'auto'  => 'Automático',
				'pdf'   => 'PDF',
				'zip'   => 'ZIP',
				'doc'   => 'DOC',
				'xls'   => 'XLS',
				'ppt'   => 'PPT',
				'image' => 'IMG',
				'video' => 'VIDEO',
				'audio' => 'AUDIO',
				'other' => 'OTRO',
			],
			'default_value' => 'auto',
			'ui'            => 1,
			'return_format' => 'value',
			'wpml_cf_preferences' => 2,
		],
	],
	'location' => [
		[
			[
				'param'    => 'block',
				'operator' => '==',
				'value'    => 'acf/deskargak-item',
			],
		],
	],
]);