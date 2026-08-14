<?php
/**
 * Academic-year courses for talks.
 *
 * talk_date is the source of truth. Taxonomy `course` is assigned automatically.
 * Current-course permalinks stay short; archived talks use /{yy-yy}/{slug}/.
 *
 * @package Kostan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KOSTAN_COURSE_REWRITE_VERSION', '2' );
define( 'KOSTAN_COURSE_SLUG_PATTERN', '[0-9]{2}-[0-9]{2}' );

/**
 * WordPress timezone for talk_date and the current course.
 *
 * @return DateTimeZone
 */
function kostan_talk_timezone() {
	return function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'Europe/Madrid' );
}

/**
 * Parse a talk's ACF talk_date into a DateTime.
 *
 * @param int $post_id Talk post ID.
 * @return DateTime|null
 */
function kostan_parse_talk_date( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return null;
	}

	$tz = kostan_talk_timezone();

	$raw = get_post_meta( $post_id, 'talk_date', true );
	if ( is_string( $raw ) && $raw !== '' ) {
		foreach ( array( 'Y-m-d H:i:s', 'Y-m-d', 'Ymd' ) as $format ) {
			$dt = DateTime::createFromFormat( $format, $raw, $tz );
			if ( $dt instanceof DateTime ) {
				return $dt;
			}
		}
	}

	$acf = function_exists( 'get_field' ) ? get_field( 'talk_date', $post_id ) : '';
	if ( $acf instanceof DateTimeInterface ) {
		$dt = new DateTime( '@' . $acf->getTimestamp() );
		$dt->setTimezone( $tz );
		return $dt;
	}

	if ( is_string( $acf ) && $acf !== '' ) {
		foreach ( array( 'd/m/Y g:i a', 'd/m/Y H:i', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d' ) as $format ) {
			$dt = DateTime::createFromFormat( $format, $acf, $tz );
			if ( $dt instanceof DateTime ) {
				return $dt;
			}
		}

		$ts = strtotime( $acf );
		if ( $ts ) {
			$dt = new DateTime( '@' . $ts );
			$dt->setTimezone( $tz );
			return $dt;
		}
	}

	return null;
}

/**
 * Academic course for a datetime.
 *
 * Sep–Jun belongs to that school year. July–August belong to the next course
 * (same rule used for “current course” from 1 July).
 *
 * @param DateTimeInterface $date Date of the talk or “today”.
 * @return array{start:int,end:int,slug:string,label:string}
 */
function kostan_get_course_from_datetime( DateTimeInterface $date ) {
	$year  = (int) $date->format( 'Y' );
	$month = (int) $date->format( 'n' );
	$start = ( $month >= 7 ) ? $year : $year - 1;
	$end   = $start + 1;

	return array(
		'start'       => $start,
		'end'         => $end,
		'slug'        => sprintf( '%02d-%02d', $start % 100, $end % 100 ),
		'label'       => $start . '-' . $end,
		'short_label' => $start . '-' . sprintf( '%02d', $end % 100 ),
	);
}

/**
 * Course that is current for URLs and listings (switches on 1 July).
 *
 * @return array{start:int,end:int,slug:string,label:string}
 */
function kostan_get_current_course() {
	$now = new DateTimeImmutable( current_time( 'mysql' ), kostan_talk_timezone() );
	return kostan_get_course_from_datetime( $now );
}

/**
 * Course data for a talk, derived from talk_date.
 *
 * @param int $post_id Talk ID.
 * @return array{start:int,end:int,slug:string,label:string}|null
 */
function kostan_get_talk_course( $post_id ) {
	$dt = kostan_parse_talk_date( $post_id );
	if ( ! $dt ) {
		$terms = get_the_terms( $post_id, 'course' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$term = $terms[0];
			return array(
				'start'       => 0,
				'end'         => 0,
				'slug'        => $term->slug,
				'label'       => $term->name,
				'short_label' => $term->name,
			);
		}
		return null;
	}

	return kostan_get_course_from_datetime( $dt );
}

/**
 * Year-month keys for a course (September–June).
 *
 * @param string $slug Course slug like 25-26.
 * @return string[]
 */
function kostan_get_course_month_keys( $slug ) {
	$start = null;
	$end   = null;

	if ( preg_match( '/^([0-9]{2})-([0-9]{2})$/', $slug, $matches ) ) {
		$start = 2000 + (int) $matches[1];
		$end   = 2000 + (int) $matches[2];
		if ( $end < $start ) {
			$end += 100;
		}
	} else {
		$course = kostan_get_current_course();
		$start  = $course['start'];
		$end    = $course['end'];
	}

	$keys = array();
	for ( $month = 9; $month <= 12; $month++ ) {
		$keys[] = sprintf( '%04d-%02d', $start, $month );
	}
	for ( $month = 1; $month <= 6; $month++ ) {
		$keys[] = sprintf( '%04d-%02d', $end, $month );
	}

	return $keys;
}

/**
 * Whether a talk belongs to the current academic course.
 *
 * @param int $post_id Talk ID.
 * @return bool
 */
function kostan_talk_is_current_course( $post_id ) {
	$course  = kostan_get_talk_course( $post_id );
	$current = kostan_get_current_course();
	return $course && $course['slug'] === $current['slug'];
}

/**
 * Create the course term if needed.
 *
 * @param array{slug:string,label:string} $course Course data.
 * @return WP_Term|null
 */
function kostan_ensure_course_term( $course ) {
	if ( empty( $course['slug'] ) ) {
		return null;
	}

	$existing = get_term_by( 'slug', $course['slug'], 'course' );
	if ( $existing instanceof WP_Term ) {
		return $existing;
	}

	$result = wp_insert_term(
		$course['label'],
		'course',
		array(
			'slug' => $course['slug'],
		)
	);

	if ( is_wp_error( $result ) ) {
		if ( isset( $result->error_data['term_exists'] ) ) {
			$term = get_term( (int) $result->error_data['term_exists'], 'course' );
			return ( $term instanceof WP_Term ) ? $term : null;
		}
		return null;
	}

	$term = get_term( (int) $result['term_id'], 'course' );
	return ( $term instanceof WP_Term ) ? $term : null;
}

/**
 * Assign the course term from talk_date.
 *
 * @param int $post_id Post ID.
 */
function kostan_assign_talk_course( $post_id ) {
	if ( ! is_numeric( $post_id ) ) {
		return;
	}

	$post_id = (int) $post_id;
	if ( $post_id <= 0 || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	if ( get_post_type( $post_id ) !== 'talks' ) {
		return;
	}

	$course = kostan_get_talk_course( $post_id );
	if ( ! $course || empty( $course['slug'] ) ) {
		return;
	}

	$term = kostan_ensure_course_term( $course );
	if ( ! $term ) {
		return;
	}

	wp_set_object_terms( $post_id, array( (int) $term->term_id ), 'course', false );
}

/**
 * WP_Query args for talks, scoped to a course (current by default).
 *
 * @param array       $args        Extra WP_Query args.
 * @param string|null $course_slug Course slug, or null for the current course.
 * @return array
 */
function kostan_talks_query_args( $args = array(), $course_slug = null ) {
	if ( null === $course_slug ) {
		$course_slug = kostan_get_current_course()['slug'];
	}

	$defaults = array(
		'post_type'      => 'talks',
		'posts_per_page' => -1,
		'meta_key'       => 'talk_date',
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
	);

	$args = wp_parse_args( $args, $defaults );

	$course_clause = array(
		'taxonomy' => 'course',
		'field'    => 'slug',
		'terms'    => $course_slug,
	);

	if ( empty( $args['tax_query'] ) ) {
		$args['tax_query'] = array( $course_clause );
		return $args;
	}

	$existing = $args['tax_query'];
	unset( $existing['relation'] );

	$args['tax_query'] = array_merge(
		array(
			'relation' => 'AND',
			$course_clause,
		),
		array_values( $existing )
	);

	return $args;
}

/**
 * Group a talks query by Y-m from talk_date.
 *
 * @param WP_Query $query Query already executed.
 * @return array<string,int[]>
 */
function kostan_group_talks_by_month( WP_Query $query ) {
	$grouped = array();

	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();
			$dt  = kostan_parse_talk_date( get_the_ID() );
			$ts  = $dt ? $dt->getTimestamp() : get_the_time( 'U' );
			$key = wp_date( 'Y-m', $ts );
			$grouped[ $key ][] = get_the_ID();
		}
		wp_reset_postdata();
	}

	ksort( $grouped );

	return $grouped;
}

/**
 * Archived course terms (not the current course), newest first.
 *
 * @return WP_Term[]
 */
function kostan_get_archived_courses() {
	$current = kostan_get_current_course();
	$terms   = get_terms(
		array(
			'taxonomy'   => 'course',
			'hide_empty' => true,
		)
	);

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return array();
	}

	$archived = array();
	foreach ( $terms as $term ) {
		if ( $term->slug === $current['slug'] ) {
			continue;
		}
		$archived[] = $term;
	}

	usort(
		$archived,
		static function ( WP_Term $a, WP_Term $b ) {
			return strcmp( $b->slug, $a->slug );
		}
	);

	return $archived;
}

/**
 * URL of the Ponentziak listing page in the current language.
 *
 * @return string
 */
function kostan_get_ponentziak_page_id() {
	$query = new WP_Query(
		array(
			'post_type'              => 'page',
			'post_status'            => 'publish',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_key'               => '_wp_page_template',
			'meta_value'             => 'page-ponentziak.php',
		)
	);

	return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
}

/**
 * URL of the Ponentziak listing page in the current language.
 *
 * @return string
 */
function kostan_get_ponentziak_url() {
	$page_id = kostan_get_ponentziak_page_id();
	if ( $page_id ) {
		$url = get_permalink( $page_id );
		if ( $url ) {
			return $url;
		}
	}

	$archive = get_post_type_archive_link( 'talks' );
	return $archive ? $archive : home_url( '/' );
}

/**
 * Whether a string is a course slug like 25-26.
 *
 * @param string $slug Candidate slug.
 * @return bool
 */
function kostan_is_course_slug( $slug ) {
	return is_string( $slug ) && (bool) preg_match( '/^' . KOSTAN_COURSE_SLUG_PATTERN . '$/', $slug );
}

/**
 * Course slug requested via pretty URL or ?curso=, defaulting to current.
 *
 * @return string
 */
function kostan_get_requested_course_slug() {
	$slug = get_query_var( 'curso' );
	if ( ! kostan_is_course_slug( $slug ) && isset( $_GET['curso'] ) ) {
		$slug = sanitize_text_field( wp_unslash( $_GET['curso'] ) );
	}

	if ( kostan_is_course_slug( $slug ) ) {
		return $slug;
	}

	return kostan_get_current_course()['slug'];
}

/**
 * Listing URL for a course. Current course stays on /hitzaldiak/.
 *
 * @param string|null $slug Course slug.
 * @return string
 */
function kostan_get_course_listing_url( $slug = null ) {
	$current = kostan_get_current_course()['slug'];
	$base    = kostan_get_ponentziak_url();

	if ( ! $slug || $slug === $current ) {
		return $base;
	}

	return trailingslashit( $base ) . rawurlencode( $slug ) . '/';
}

/**
 * Calendar URL for a course.
 *
 * @param string|null $slug Course slug.
 * @return string
 */
function kostan_get_course_calendar_url( $slug = null ) {
	$url = get_post_type_archive_link( 'talks' );
	if ( ! $url ) {
		$url = home_url( '/' );
	}

	$current = kostan_get_current_course()['slug'];
	if ( ! $slug || $slug === $current ) {
		return $url;
	}

	return add_query_arg( 'curso', $slug, $url );
}

/**
 * Courses for the switcher: current (even if empty) plus archived terms.
 *
 * @return array<int,array{slug:string,label:string}>
 */
function kostan_get_nav_courses() {
	$current = kostan_get_current_course();
	$items   = array(
		array(
			'slug'  => $current['slug'],
			'label' => $current['label'],
		),
	);

	foreach ( kostan_get_archived_courses() as $term ) {
		$items[] = array(
			'slug'  => $term->slug,
			'label' => $term->name,
		);
	}

	return $items;
}

/**
 * Course dropdown. $context listing|calendar controls the destination URLs.
 *
 * @param string $active_slug Active course slug.
 * @param string $context     listing or calendar.
 */
function kostan_the_course_dropdown( $active_slug, $context = 'listing' ) {
	$courses = kostan_get_nav_courses();
	if ( empty( $courses ) ) {
		return;
	}

	$active_label = $active_slug;
	foreach ( $courses as $course ) {
		if ( $course['slug'] === $active_slug ) {
			$active_label = $course['label'];
			break;
		}
	}

	if ( 'listing' === $context ) {
		?>
		<details class="talks-nav__course">
			<summary class="talks-nav__course-toggle">
				<span><?php echo esc_html( $active_label ); ?></span>
				<svg class="talks-nav__course-chevron" width="10" height="7" viewBox="0 0 12 8" aria-hidden="true"><path fill="currentColor" d="M1.41.59 6 5.17 10.59.59 12 2 6 8 0 2z"/></svg>
			</summary>
			<ul class="talks-nav__course-menu">
				<?php foreach ( $courses as $course ) :
					$url     = kostan_get_course_listing_url( $course['slug'] );
					$current = ( $course['slug'] === $active_slug );
					?>
					<li>
						<a href="<?php echo esc_url( $url ); ?>"<?php echo $current ? ' aria-current="page"' : ''; ?>>
							<?php echo esc_html( $course['label'] ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</details>
		<?php
		return;
	}

	$id = 'calendar-course-filter';
	?>
	<div class="calendar-filter js-filter-dropdown" id="<?php echo esc_attr( $id ); ?>" aria-expanded="false">
		<button type="button" class="calendar-filter__toggle" aria-expanded="false" aria-haspopup="listbox">
			<span class="calendar-filter__label"><?php echo esc_html( $active_label ); ?></span>
			<svg class="calendar-filter__arrow" width="12" height="8" viewBox="0 0 12 8" aria-hidden="true"><path fill="currentColor" d="M1.41.59 6 5.17 10.59.59 12 2 6 8 0 2z"/></svg>
		</button>
		<ul class="calendar-filter__list" role="listbox">
			<?php foreach ( $courses as $course ) :
				$url     = kostan_get_course_calendar_url( $course['slug'] );
				$current = ( $course['slug'] === $active_slug );
				?>
				<li class="calendar-filter__option<?php echo $current ? ' calendar-filter__option--active' : ''; ?>" role="option">
					<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $course['label'] ); ?></a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
}

/**
 * CPT rewrite slug(s), including WPML translations.
 *
 * @return string[]
 */
function kostan_get_talks_rewrite_slugs() {
	$pto  = get_post_type_object( 'talks' );
	$base = ( $pto && ! empty( $pto->rewrite['slug'] ) ) ? $pto->rewrite['slug'] : 'hitzaldiak';
	$base = trim( (string) $base, '/' );

	$slugs = array( $base );

	$langs = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
	if ( is_array( $langs ) ) {
		foreach ( $langs as $code => $lang ) {
			$translated = apply_filters( 'wpml_get_translated_slug', $base, 'talks', $code );
			if ( is_string( $translated ) && $translated !== '' ) {
				$slugs[] = trim( $translated, '/' );
			}
		}
	}

	return array_values( array_unique( array_filter( $slugs ) ) );
}

/**
 * Talks archive slug in the current language.
 *
 * @return string
 */
function kostan_get_talks_base_slug() {
	$slugs = kostan_get_talks_rewrite_slugs();
	$pto   = get_post_type_object( 'talks' );
	$base  = ( $pto && ! empty( $pto->rewrite['slug'] ) ) ? trim( $pto->rewrite['slug'], '/' ) : 'hitzaldiak';

	$lang = apply_filters( 'wpml_current_language', null );
	if ( $lang ) {
		$translated = apply_filters( 'wpml_get_translated_slug', $base, 'talks', $lang );
		if ( is_string( $translated ) && $translated !== '' ) {
			return trim( $translated, '/' );
		}
	}

	return $slugs[0];
}

/**
 * Register course taxonomy.
 */
function kostan_register_course_taxonomy() {
	$labels = array(
		'name'          => __( 'Cursos', 'kostan' ),
		'singular_name' => __( 'Curso', 'kostan' ),
		'menu_name'     => __( 'Cursos', 'kostan' ),
		'search_items'  => __( 'Buscar cursos', 'kostan' ),
		'all_items'     => __( 'Todos los cursos', 'kostan' ),
		'edit_item'     => __( 'Editar curso', 'kostan' ),
		'update_item'   => __( 'Actualizar curso', 'kostan' ),
		'add_new_item'  => __( 'Anadir curso', 'kostan' ),
		'new_item_name' => __( 'Nuevo curso', 'kostan' ),
	);

	register_taxonomy(
		'course',
		array( 'talks' ),
		array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => false,
			'show_in_rest'       => false,
			'show_admin_column'  => false,
			'show_in_quick_edit' => false,
			'meta_box_cb'        => false,
			'hierarchical'       => false,
			'rewrite'            => false,
			'query_var'          => 'course',
		)
	);
}
add_action( 'init', 'kostan_register_course_taxonomy', 11 );

/**
 * Custom rewrite rules under the talks CPT slug.
 */
function kostan_register_course_rewrites() {
	add_rewrite_tag( '%kostan_course%', '(' . KOSTAN_COURSE_SLUG_PATTERN . ')' );
	add_rewrite_tag( '%curso%', '(' . KOSTAN_COURSE_SLUG_PATTERN . ')' );

	$slugs = kostan_get_talks_rewrite_slugs();
	$page_id = kostan_get_ponentziak_page_id();
	if ( $page_id ) {
		$page = get_post( $page_id );
		if ( $page && $page->post_name ) {
			$slugs[] = $page->post_name;
		}
	}

	foreach ( array_unique( array_filter( $slugs ) ) as $base ) {
		$quoted = preg_quote( $base, '/' );

		add_rewrite_rule(
			$quoted . '/(' . KOSTAN_COURSE_SLUG_PATTERN . ')/([^/]+)/?$',
			'index.php?post_type=talks&name=$matches[2]&kostan_course=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			$quoted . '/(' . KOSTAN_COURSE_SLUG_PATTERN . ')/?$',
			'index.php?curso=$matches[1]',
			'top'
		);
	}
}
add_action( 'init', 'kostan_register_course_rewrites', 12 );

/**
 * Expose the archived-talk course query var.
 *
 * @param string[] $vars Query vars.
 * @return string[]
 */
function kostan_course_query_vars( $vars ) {
	$vars[] = 'kostan_course';
	$vars[] = 'curso';
	return $vars;
}
add_filter( 'query_vars', 'kostan_course_query_vars' );

/**
 * Insert the course segment into archived talk permalinks.
 *
 * @param string  $post_link Permalink.
 * @param WP_Post $post      Post.
 * @return string
 */
function kostan_talk_post_type_link( $post_link, $post ) {
	if ( ! $post instanceof WP_Post || 'talks' !== $post->post_type ) {
		return $post_link;
	}

	if ( kostan_talk_is_current_course( $post->ID ) ) {
		return $post_link;
	}

	$course = kostan_get_talk_course( $post->ID );
	if ( ! $course || '' === $post->post_name ) {
		return $post_link;
	}

	$updated = preg_replace(
		'#/' . preg_quote( $post->post_name, '#' ) . '/?$#',
		'/' . $course['slug'] . '/' . $post->post_name . '/',
		$post_link
	);

	return is_string( $updated ) ? $updated : $post_link;
}
add_filter( 'post_type_link', 'kostan_talk_post_type_link', 20, 2 );

/**
 * Course archive URLs: /hitzaldiak/25-26/.
 *
 * @param string  $termlink Term permalink.
 * @param WP_Term $term     Term.
 * @param string  $taxonomy Taxonomy.
 * @return string
 */
function kostan_course_term_link( $termlink, $term, $taxonomy ) {
	if ( 'course' !== $taxonomy || ! $term instanceof WP_Term ) {
		return $termlink;
	}

	$path = ltrim( wp_parse_url( kostan_get_course_listing_url( $term->slug ), PHP_URL_PATH ), '/' );
	return home_url( user_trailingslashit( $path ) );
}
add_filter( 'term_link', 'kostan_course_term_link', 20, 3 );

/**
 * Canonical 301s for talks and the current-course archive.
 */
function kostan_course_canonical_redirect() {
	if ( is_admin() || wp_doing_ajax() || is_preview() || is_feed() || is_embed() ) {
		return;
	}

	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';
	if ( 'GET' !== $method && 'HEAD' !== $method ) {
		return;
	}

	if ( is_tax( 'course' ) ) {
		$term = get_queried_object();
		$slug = ( $term instanceof WP_Term ) ? $term->slug : '';
		wp_safe_redirect( kostan_get_course_listing_url( $slug ), 301 );
		exit;
	}

	$curso   = get_query_var( 'curso' );
	$current = kostan_get_current_course()['slug'];
	if ( kostan_is_course_slug( $curso ) && $curso === $current && ! is_post_type_archive( 'talks' ) ) {
		wp_safe_redirect( kostan_get_ponentziak_url(), 301 );
		exit;
	}

	if ( ! is_singular( 'talks' ) ) {
		return;
	}

	$post = get_queried_object();
	if ( ! $post instanceof WP_Post ) {
		return;
	}

	$canonical = get_permalink( $post );
	if ( ! $canonical ) {
		return;
	}

	$request_path   = wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH );
	$canonical_path = wp_parse_url( $canonical, PHP_URL_PATH );

	if ( ! is_string( $request_path ) || ! is_string( $canonical_path ) ) {
		return;
	}

	if ( untrailingslashit( $request_path ) !== untrailingslashit( $canonical_path ) ) {
		wp_safe_redirect( $canonical, 301 );
		exit;
	}
}
add_action( 'template_redirect', 'kostan_course_canonical_redirect', 1 );

/**
 * Stop redirect_canonical from looping pretty course URLs.
 *
 * @param string|false $redirect_url  Canonical URL.
 * @param string       $requested_url Requested URL.
 * @return string|false
 */
function kostan_course_disable_canonical_loop( $redirect_url, $requested_url ) {
	if ( get_query_var( 'curso' ) || get_query_var( 'kostan_course' ) || is_tax( 'course' ) ) {
		return false;
	}

	return $redirect_url;
}
add_filter( 'redirect_canonical', 'kostan_course_disable_canonical_loop', 10, 2 );

/**
 * Map /hitzaldiak/25-26/ to the Ponentziak page with curso query var.
 *
 * @param WP_Query $query Query.
 */
function kostan_curso_path_to_ponentziak( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( $query->get( 'kostan_course' ) || $query->get( 'name' ) ) {
		return;
	}

	if ( $query->is_post_type_archive( 'talks' ) || $query->is_page() ) {
		return;
	}

	$curso = $query->get( 'curso' );
	if ( ! kostan_is_course_slug( $curso ) ) {
		return;
	}

	$page_id = kostan_get_ponentziak_page_id();
	if ( ! $page_id ) {
		return;
	}

	$query->set( 'page_id', $page_id );
	$query->set( 'post_type', 'page' );
	$query->set( 'pagename', '' );
	$query->queried_object    = get_post( $page_id );
	$query->queried_object_id = $page_id;
	$query->is_page               = true;
	$query->is_singular           = true;
	$query->is_home               = false;
	$query->is_front_page         = false;
	$query->is_archive            = false;
	$query->is_post_type_archive  = false;
	$query->is_tax                = false;
	$query->is_404                = false;
}
add_action( 'parse_query', 'kostan_curso_path_to_ponentziak', 1 );

/**
 * Assign course after ACF (and core) saves a talk.
 *
 * @param int $post_id Post ID.
 */
function kostan_assign_talk_course_on_save( $post_id ) {
	kostan_assign_talk_course( $post_id );
}
add_action( 'acf/save_post', 'kostan_assign_talk_course_on_save', 20 );
add_action( 'save_post_talks', 'kostan_assign_talk_course_on_save', 20 );

/**
 * One-shot assignment for existing talks + rewrite flush.
 */
function kostan_maybe_migrate_talk_courses() {
	if ( get_option( 'kostan_talk_courses_migrated' ) === KOSTAN_COURSE_REWRITE_VERSION ) {
		kostan_maybe_flush_course_rewrites();
		return;
	}

	if ( ! taxonomy_exists( 'course' ) ) {
		return;
	}

	$talks = get_posts(
		array(
			'post_type'        => 'talks',
			'post_status'      => 'any',
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
		)
	);

	foreach ( $talks as $talk_id ) {
		kostan_assign_talk_course( (int) $talk_id );
	}

	flush_rewrite_rules( false );
	update_option( 'kostan_talk_courses_migrated', KOSTAN_COURSE_REWRITE_VERSION );
	update_option( 'kostan_course_rewrite_version', KOSTAN_COURSE_REWRITE_VERSION );
}
add_action( 'init', 'kostan_maybe_migrate_talk_courses', 30 );

/**
 * Flush rewrites when the version constant changes.
 */
function kostan_maybe_flush_course_rewrites() {
	if ( get_option( 'kostan_course_rewrite_version' ) === KOSTAN_COURSE_REWRITE_VERSION ) {
		return;
	}

	flush_rewrite_rules( false );
	update_option( 'kostan_course_rewrite_version', KOSTAN_COURSE_REWRITE_VERSION );
}

/**
 * Flush on theme switch.
 */
function kostan_flush_course_rewrites_on_switch() {
	delete_option( 'kostan_course_rewrite_version' );
	flush_rewrite_rules( false );
}
add_action( 'after_switch_theme', 'kostan_flush_course_rewrites_on_switch' );

/**
 * Read-only course column in the talks list table.
 *
 * @param array $columns Columns.
 * @return array
 */
function kostan_talks_course_column( $columns ) {
	$columns['course'] = __( 'Curso', 'kostan' );
	return $columns;
}
add_filter( 'manage_talks_posts_columns', 'kostan_talks_course_column' );

/**
 * Output the course column.
 *
 * @param string $column  Column id.
 * @param int    $post_id Post ID.
 */
function kostan_talks_course_column_content( $column, $post_id ) {
	if ( 'course' !== $column ) {
		return;
	}

	$terms = get_the_terms( $post_id, 'course' );
	if ( $terms && ! is_wp_error( $terms ) ) {
		echo esc_html( $terms[0]->name );
		return;
	}

	echo '—';
}
add_action( 'manage_talks_posts_custom_column', 'kostan_talks_course_column_content', 10, 2 );

/**
 * Order course taxonomy archives by talk_date.
 *
 * @param WP_Query $query Query.
 */
function kostan_course_archive_pre_get_posts( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_tax( 'course' ) ) {
		return;
	}

	$query->set( 'post_type', 'talks' );
	$query->set( 'posts_per_page', -1 );
	$query->set( 'meta_key', 'talk_date' );
	$query->set( 'orderby', 'meta_value' );
	$query->set( 'order', 'ASC' );
}
add_action( 'pre_get_posts', 'kostan_course_archive_pre_get_posts' );
