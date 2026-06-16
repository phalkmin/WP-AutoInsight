<?php
/**
 * Tab: Topics — Topic Library list table and add/edit form.
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

		return esc_html( date_i18n( 'Y-m-d H:i', $item['next_run'] ) );
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

		$label = date_i18n( 'Y-m-d H:i', $item['last_run'] );

		if ( $item['last_job_id'] ) {
			$result_post_id = (int) get_post_meta( $item['last_job_id'], '_abcc_job_result_post_id', true );
			if ( $result_post_id ) {
				return sprintf(
					'<a href="%s">%s</a>',
					esc_url( get_edit_post_link( $result_post_id ) ),
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

$abcc_topics_table = new ABCC_Topics_List_Table();
$abcc_topics_table->prepare_items();

$abcc_model_options = abcc_get_available_text_model_options();
?>

<div class="abcc-topics-tab">
	<h2><?php esc_html_e( 'Topic Library', 'automated-blog-content-creator' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Topics are reusable prompts that generate posts on their own schedule. Each topic runs independently — pause, resume, or trigger them manually at any time.', 'automated-blog-content-creator' ); ?>
	</p>

	<div class="abcc-topic-form-wrap" id="abcc-topic-form-wrap">
		<h3 id="abcc-topic-form-title"><?php esc_html_e( 'Add Topic', 'automated-blog-content-creator' ); ?></h3>
		<form id="abcc-topic-form">
			<input type="hidden" id="abcc-topic-id" value="">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="abcc-topic-title"><?php esc_html_e( 'Topic name', 'automated-blog-content-creator' ); ?></label></th>
					<td><input type="text" id="abcc-topic-title" class="regular-text" required></td>
				</tr>
				<tr>
					<th scope="row"><label for="abcc-topic-prompt"><?php esc_html_e( 'Prompt', 'automated-blog-content-creator' ); ?></label></th>
					<td><textarea id="abcc-topic-prompt" class="large-text" rows="4" required></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="abcc-topic-frequency"><?php esc_html_e( 'Frequency', 'automated-blog-content-creator' ); ?></label></th>
					<td>
						<select id="abcc-topic-frequency">
							<option value="hourly"><?php esc_html_e( 'Hourly', 'automated-blog-content-creator' ); ?></option>
							<option value="every_2h"><?php esc_html_e( 'Every 2 hours', 'automated-blog-content-creator' ); ?></option>
							<option value="every_6h"><?php esc_html_e( 'Every 6 hours', 'automated-blog-content-creator' ); ?></option>
							<option value="daily" selected><?php esc_html_e( 'Daily', 'automated-blog-content-creator' ); ?></option>
							<option value="weekly"><?php esc_html_e( 'Weekly', 'automated-blog-content-creator' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="abcc-topic-status-override"><?php esc_html_e( 'Post status', 'automated-blog-content-creator' ); ?></label></th>
					<td>
						<select id="abcc-topic-status-override">
							<option value=""><?php esc_html_e( 'Use global setting', 'automated-blog-content-creator' ); ?></option>
							<option value="draft"><?php esc_html_e( 'Always draft', 'automated-blog-content-creator' ); ?></option>
							<option value="publish"><?php esc_html_e( 'Always publish', 'automated-blog-content-creator' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="abcc-topic-provider-override"><?php esc_html_e( 'Provider', 'automated-blog-content-creator' ); ?></label></th>
					<td>
						<select id="abcc-topic-provider-override">
							<option value=""><?php esc_html_e( 'Use global model', 'automated-blog-content-creator' ); ?></option>
							<?php foreach ( $abcc_model_options as $abcc_provider_data ) : ?>
								<optgroup label="<?php echo esc_attr( $abcc_provider_data['group'] ); ?>">
									<?php foreach ( $abcc_provider_data['options'] as $abcc_model_id => $abcc_model_data ) : ?>
										<option value="<?php echo esc_attr( $abcc_model_id ); ?>"><?php echo esc_html( $abcc_model_data['name'] ); ?></option>
									<?php endforeach; ?>
								</optgroup>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>
			<p>
				<button type="submit" class="button button-primary" id="abcc-topic-save"><?php esc_html_e( 'Save Topic', 'automated-blog-content-creator' ); ?></button>
				<button type="button" class="button" id="abcc-topic-cancel" style="display:none;"><?php esc_html_e( 'Cancel', 'automated-blog-content-creator' ); ?></button>
				<span class="abcc-topic-form-feedback" id="abcc-topic-form-feedback" aria-live="polite"></span>
			</p>
		</form>
	</div>

	<?php $abcc_topics_table->display(); ?>
</div>
