<?php
/**
 * Generation job entity.
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Generation job utilities.
 */
class ABCC_Job {

	const POST_TYPE      = 'abcc_job';
	const STATUS_QUEUED  = 'queued';
	const STATUS_RUNNING = 'running';
	const STATUS_SUCCESS = 'succeeded';
	const STATUS_FAILED  = 'failed';
}
