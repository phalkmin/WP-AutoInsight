<?php
/**
 * Citation transients must not collide across concurrent generations,
 * and the write/read pair within ONE generation must agree on the key.
 */

abcc_test(
	'citation transient key is unique per job',
	function () {
		$a = abcc_citation_transient_key( array( 'job_id' => 101 ) );
		$b = abcc_citation_transient_key( array( 'job_id' => 102 ) );

		abcc_assert_true( $a !== $b, 'Two jobs must not share a citation transient key.' );
		abcc_assert_true( 0 === strpos( $a, 'abcc_pplx_citations_' ), 'Key should keep its prefix: ' . $a );
	}
);

abcc_test(
	'citation transient key is stable within one interactive generation',
	function () {
		// The provider callback WRITES citations with this key mid-generation
		// and abcc_openai_generate_post READS them moments later in the same
		// request. If the two calls disagree, citations are silently dropped.
		$write = abcc_citation_transient_key( array() );
		$read  = abcc_citation_transient_key( array() );

		abcc_assert_same( $write, $read, 'Write and read must use the same key in one request.' );
	}
);

abcc_test(
	'background generations always key citations per job, not per user',
	function () {
		// Cron has no current user, so concurrency safety comes from the job
		// ID — every queued generation threads one through (class-abcc-job.php
		// sets $payload['job_id'] before processing).
		$a = abcc_citation_transient_key( array( 'job_id' => 7 ) );
		$b = abcc_citation_transient_key( array( 'job_id' => 8 ) );

		abcc_assert_true( $a !== $b, 'Concurrent jobs must not share a key.' );
		abcc_assert_true( false !== strpos( $a, 'job_7' ), 'Job keys should embed the job ID: ' . $a );
	}
);
