<?php
/**
 * Public system status. See Naturedex\Status for what may appear here and why.
 *
 * @package Naturedex
 */

defined( 'ABSPATH' ) || exit;

use Naturedex\Status;

$naturedex_report = class_exists( Status::class ) ? Status::report() : null;
$naturedex_when   = static fn( int $time ): string => $time ? wp_date( 'M j, g:i a T', $time ) : 'Unknown';

get_header();
if ( $naturedex_report ) :
	$naturedex_good  = 'operational' === $naturedex_report['overall'];
	$naturedex_tests = $naturedex_report['tests'];
	?>
	<div class="status-banner <?php echo $naturedex_good ? 'status-good' : 'status-bad'; ?>">
		<strong><?php echo $naturedex_good ? '✓ All systems healthy' : '! Something needs attention'; ?></strong>
		<span>Checked <?php echo esc_html( $naturedex_when( $naturedex_report['checkedAt'] ) ); ?>. This page can be up to 5 minutes old.</span>
	</div>
	<?php if ( $naturedex_report['stale'] ) : ?>
		<p class="fine-print status-note">Test and deployment history couldn't be refreshed just now, so the latest saved results are shown.</p>
	<?php endif; ?>

	<div class="status-grid">
		<section class="panel status-panel" aria-labelledby="status-site">
			<h2 id="status-site">Live site</h2>
			<ul class="status-list">
				<?php foreach ( $naturedex_report['checks'] as $naturedex_check ) : ?>
					<li>
						<span><?php echo esc_html( $naturedex_check['label'] ); ?></span>
						<span class="status-pill <?php echo $naturedex_check['ok'] ? 'status-pass' : 'status-fail'; ?>"><?php echo $naturedex_check['ok'] ? '✓ Working' : '! Problem'; ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>

		<section class="panel status-panel" aria-labelledby="status-tests">
			<h2 id="status-tests">Latest test run</h2>
			<?php if ( $naturedex_tests ) : ?>
				<table class="status-table">
					<thead><tr><th scope="col">Suite</th><th scope="col">Passed</th><th scope="col">Failed</th></tr></thead>
					<tbody>
						<?php foreach ( $naturedex_tests['suites'] as $naturedex_suite ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( $naturedex_suite['label'] ); ?></th>
								<td><?php echo (int) $naturedex_suite['passed']; ?></td>
								<td class="<?php echo $naturedex_suite['failed'] ? 'status-fail-text' : ''; ?>"><?php echo (int) $naturedex_suite['failed']; ?></td>
							</tr>
						<?php endforeach; ?>
						<tr>
							<th scope="row">Production image</th>
							<td colspan="2"><?php echo $naturedex_tests['image'] ? '✓ Passed' : '! Failed'; ?></td>
						</tr>
					</tbody>
				</table>
				<p class="fine-print">
					Run <?php echo esc_html( $naturedex_when( $naturedex_tests['at'] ) ); ?>
					<?php if ( $naturedex_tests['commit'] ) : ?>
						on commit <code><?php echo esc_html( $naturedex_tests['commit'] ); ?></code>
					<?php endif; ?>
				</p>
				<?php if ( $naturedex_tests['recent'] ) : ?>
					<?php $naturedex_passes = count( array_filter( array_column( $naturedex_tests['recent'], 'ok' ) ) ); ?>
					<p class="status-trend">
						<span class="sr-only"><?php echo (int) $naturedex_passes; ?> of the last <?php echo (int) count( $naturedex_tests['recent'] ); ?> test runs passed.</span>
						<?php foreach ( array_reverse( $naturedex_tests['recent'] ) as $naturedex_run ) : ?>
							<span class="<?php echo $naturedex_run['ok'] ? 'status-pass' : 'status-fail'; ?>" aria-hidden="true"><?php echo $naturedex_run['ok'] ? '✓' : '!'; ?></span>
						<?php endforeach; ?>
					</p>
				<?php endif; ?>
			<?php else : ?>
				<p>Test results aren't available right now.</p>
			<?php endif; ?>
		</section>

		<section class="panel status-panel status-wide" aria-labelledby="status-deploys">
			<h2 id="status-deploys">Recent deployments</h2>
			<?php if ( $naturedex_report['deployments'] ) : ?>
				<table class="status-table">
					<thead><tr><th scope="col">Started</th><th scope="col">Result</th><th scope="col">Duration</th><th scope="col">Commit</th></tr></thead>
					<tbody>
						<?php foreach ( $naturedex_report['deployments'] as $naturedex_deploy ) : ?>
							<?php
							$naturedex_labels = array(
								'succeeded' => '✓ Succeeded',
								'failed'    => '! Failed',
								'cancelled' => '– Cancelled',
								'running'   => '… In progress',
							);
							?>
							<tr>
								<th scope="row"><?php echo esc_html( $naturedex_when( $naturedex_deploy['at'] ) ); ?></th>
								<td><?php echo esc_html( $naturedex_labels[ $naturedex_deploy['result'] ] ); ?></td>
								<td><?php echo $naturedex_deploy['minutes'] ? (int) $naturedex_deploy['minutes'] . ' min' : '–'; ?></td>
								<td><code><?php echo esc_html( $naturedex_deploy['commit'] ); ?></code></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p>Deployment history isn't available right now.</p>
			<?php endif; ?>
		</section>
	</div>
	<p class="fine-print status-note">Results come from the automated test and deployment pipeline. Details are kept private on purpose.</p>
<?php else : ?>
	<p class="error-message" role="alert">Status is unavailable.</p>
<?php endif; ?>
<?php
get_footer();
