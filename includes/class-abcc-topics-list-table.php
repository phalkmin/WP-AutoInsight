<?php
/**
 * Topic Library list table.
 *
 * @package WP-AutoInsight
 * @since 4.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List table for Topic Library entries.
 *
 * @since 4.2.0
 */
class ABCC_Topics_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'abcc_topic',
				'plural'   => 'abcc_topics',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'title'     => __( 'Topic', 'automated-blog-content-creator' ),
			'frequency' => __( 'Frequency', 'automated-blog-content-creator' ),
			'status'    => __( 'Status', 'automated-blog-content-creator' ),
			'next_run'  => __( 'Next Run', 'automated-blog-content-creator' ),
			'last_run'  => __( 'Last Run', 'automated-blog-content-creator' ),
		);
	}

	/**
	 * Load items.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->items           = abcc_get_topics();
	}

	/**
	 * Empty-state message.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No topics yet. Add your first topic above — it becomes a recurring source of generated posts.', 'automated-blog-content-creator' );
	}

	/**
	 * Topic column with row actions.
	 *
	 * @param array $item Topic row.
	 * @return string
	 */
	public function column_title( $item ) {
		$actions = array(
			'edit'    => sprintf(
				'<a href="#" class="abcc-topic-edit" data-topic="%s">%s</a>',
				esc_attr( wp_json_encode( $item ) ? wp_json_encode( $item ) : '{}' ),
				esc_html__( 'Edit', 'automated-blog-content-creator' )
			),
			'toggle'  => sprintf(
				'<a href="#" class="abcc-topic-toggle" data-topic-id="%d">%s</a>',
				(int) $item['id'],
				$item['active'] ? esc_html__( 'Pause', 'automated-blog-content-creator' ) : esc_html__( 'Resume', 'automated-blog-content-creator' )
			),
			'run_now' => sprintf(
				'<a href="#" class="abcc-topic-run-now" data-topic-id="%d">%s</a>',
				(int) $item['id'],
				esc_html__( 'Run Now', 'automated-blog-content-creator' )
			),
			'delete'  => sprintf(
				'<a href="#" class="abcc-topic-delete" data-topic-id="%d">%s</a>',
				(int) $item['id'],
				esc_html__( 'Delete', 'automated-blog-content-creator' )
			),
		);

		return sprintf(
			'<strong>%s</strong>%s',
			esc_html( $item['title'] ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * column_title() already renders the row actions; core would add a second toggle.
	 *
	 * @param array  $item        Row.
	 * @param string $column_name Column.
	 * @param string $primary     Primary column.
	 * @return string
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		return '';
	}

	/**
	 * Frequency column.
	 *
	 * @param array $item Topic row.
	 * @return string
	 */
	public function column_frequency( $item ) {
		$labels = array(
			'hourly'   => __( 'Hourly', 'automated-blog-content-creator' ),
			'every_2h' => __( 'Every 2 hours', 'automated-blog-content-creator' ),
			'every_6h' => __( 'Every 6 hours', 'automated-blog-content-creator' ),
			'daily'    => __( 'Daily', 'automated-blog-content-creator' ),
			'weekly'   => __( 'Weekly', 'automated-blog-content-creator' ),
		);

		return esc_html( $labels[ $item['frequency'] ] ?? $item['frequency'] );
	}

	/**
	 * Status column.
	 *
	 * @param array $item Topic row.
	 * @return string
	 */
	public function column_status( $item ) {
		if ( $item['active'] ) {
			return '<span class="abcc-job-status-badge abcc-job-status-badge--succeeded">' . esc_html__( 'Active', 'automated-blog-content-creator' ) . '</span>';
		}

		$label = $item['consecutive_failures'] >= 5
			? __( 'Paused (failures)', 'automated-blog-content-creator' )
			: __( 'Paused', 'automated-blog-content-creator' );

		return '<span class="abcc-job-status-badge abcc-job-status-badge--failed">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Next run column.
	 *
	 * @param array $item Topic row.
	 * @return string
	 */
	public function column_next_run( $item ) {
		if ( ! $item['active'] ) {
			return '&mdash;';
		}

		return esc_html( wp_date( 'Y-m-d H:i', (int) $item['next_run'] ) );
	}

	/**
	 * Last run column (links to the last job's result when available).
	 *
	 * @param array $item Topic row.
	 * @return string
	 */
	public function column_last_run( $item ) {
		if ( empty( $item['last_run'] ) ) {
			return '&mdash;';
		}

		$label = wp_date( 'Y-m-d H:i', (int) $item['last_run'] );

		if ( $item['last_job_id'] ) {
			$result_post_id = (int) get_post_meta( $item['last_job_id'], '_abcc_job_result_post_id', true );
			if ( $result_post_id ) {
				return sprintf(
					'<a href="%s">%s</a>',
					esc_url( (string) get_edit_post_link( $result_post_id ) ),
					esc_html( $label )
				);
			}
		}

		return esc_html( $label );
	}

	/**
	 * Default column fallback.
	 *
	 * @param array  $item        Topic row.
	 * @param string $column_name Column.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( (string) $item[ $column_name ] ) : '';
	}
}
