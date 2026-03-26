<?php
/**
 * Register ACF field group for the Hero Carousel block.
 *
 * @package Kostan
 */

if ( ! function_exists( 'acf_add_local_field_group' ) ) {
	return;
}

acf_add_local_field_group([
	'key'      => 'group_hero_carousel',
	'title'    => 'Hero Carousel',
	'fields'   => [
		[
			'key'          => 'field_hero_slides',
			'label'        => 'Slides',
			'name'         => 'hero_slides',
			'type'         => 'repeater',
			'layout'       => 'block',
			'button_label' => 'Añadir slide',
			'min'          => 1,
			'sub_fields'   => [
				[
					'key'           => 'field_slide_image',
					'label'         => 'Imagen',
					'name'          => 'slide_image',
					'type'          => 'image',
					'return_format' => 'array',
					'preview_size'  => 'large',
					'mime_types'    => 'jpg,jpeg,png,webp',
				],

			],
		],
	],
	'location' => [
		[
			[
				'param'    => 'block',
				'operator' => '==',
				'value'    => 'acf/hero-carousel',
			],
		],
	],
]);
