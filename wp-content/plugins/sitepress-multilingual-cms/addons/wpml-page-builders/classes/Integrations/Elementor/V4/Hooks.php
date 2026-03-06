<?php

namespace WPML\PB\Elementor\V4;

use WPML\FP\Obj;
use WPML\LIB\WP\Hooks as WPHooks;

use function WPML\FP\spreadArgs;

class Hooks implements \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const V4_WIDGET_PREFIX      = 'e-';
	const TYPE_KEY              = '$$type';
	const DESTINATION_TYPE_PATH = [ 'value', 'destination', self::TYPE_KEY ];
	const ID_PATH               = [ 'value', 'destination', 'value', 'id', 'value' ];

	/** @var \SitePress */
	private $sitepress;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		WPHooks::onFilter( 'elementor/frontend/builder_content_data' )
			->then( spreadArgs( [ $this, 'translateLinkIds' ] ) );
	}

	/**
	 * @param array $data
	 *
	 * @return array
	 */
	public function translateLinkIds( array $data ) {
		foreach ( $data as &$element ) {
			if ( $this->isV4Widget( $element ) ) {
				$element['settings'] = $this->translateSettingsLinkIds( $element['settings'] );
			}

			if ( ! empty( $element['elements'] ) ) {
				$element['elements'] = $this->translateLinkIds( $element['elements'] );
			}
		}

		return $data;
	}

	/**
	 * @param array $element
	 *
	 * @return bool
	 */
	private function isV4Widget( array $element ) {
		$widgetType = $element['widgetType'] ?? null;

		return $widgetType
			&& strpos( $widgetType, self::V4_WIDGET_PREFIX ) === 0
			&& isset( $element['settings'] )
			&& is_array( $element['settings'] );
	}

	/**
	 * @param array $settings
	 *
	 * @return array
	 */
	private function translateSettingsLinkIds( array $settings ) {
		foreach ( $settings as $key => $value ) {
			if ( $this->isLinkWithQueryDestination( $value ) ) {
				$settings[ $key ] = $this->translateLinkQueryId( $value );
			}
		}

		return $settings;
	}

	/**
	 * @param mixed $value
	 *
	 * @return bool
	 */
	private function isLinkWithQueryDestination( $value ) {
		return is_array( $value )
			&& 'link' === Obj::prop( self::TYPE_KEY, $value )
			&& 'query' === Obj::path( self::DESTINATION_TYPE_PATH, $value )
			&& null !== Obj::path( self::ID_PATH, $value );
	}

	/**
	 * @param array $link
	 *
	 * @return array
	 */
	private function translateLinkQueryId( array $link ) {
		$postId       = Obj::path( self::ID_PATH, $link );
		$translatedId = $this->sitepress->get_object_id( $postId, get_post_type( $postId ), true );

		if ( $translatedId ) {
			$link = Obj::assocPath( self::ID_PATH, $translatedId, $link );
		}

		return $link;
	}
}
