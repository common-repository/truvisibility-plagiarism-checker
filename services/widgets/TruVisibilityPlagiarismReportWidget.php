<?php 
class TruVisibilityPlagiarismReportWidget
{
	public function __construct()
	{
		add_action('add_meta_boxes', array($this, 'init'));
	}

	public function init()
	{
		add_meta_box('rast-report', 'TruVisibility Plagiarism Report', array($this, 'rast_report_design'), 'post', 'normal', 'high');

		add_action('admin_enqueue_scripts', array($this, 'insert_styles_and_scripts'));
	}

	public function rast_report_design($post, $box)
	{
		?>
		<span id="rast-post-id" style="display: none;"><?php echo $post->ID; ?></span>
		
		<div id="rast-publish-dialog" class="rast-modal">
			<div class="rast-modal-content">
				<div style="padding: 8px 12px; border-bottom: 1px solid #eee; cursor: pointer;">
					<strong>Plagiarism checking confirmation</strong>
					<span id="rast-publish-dialog-close" class="dashicons dashicons-no-alt" style="float: right;"></span>
				</div>
				<div style="padding: 0 12px 12px 12px;">
					<p id="rast-quota-not-exceeded-label" class="rast-hidden">Do you want to publish this post or check it for plagiarism before? If post's content is unique, it will be published automatically. Otherwise moved to draft.</p>
					<p id="rast-quota-exceeded-label" class="rast-hidden">Your quota has been exceeded. You can buy extra checks from plugin settings page.</p>
					<div>
						<div id="rast-publish-without-check" class="button button-primary" style="margin: 6px 6px 0 0;">Publish</div>
						<div id="rast-publish-with-check" class="button" style="margin: 6px 6px 0 0;">Publish with checking</div>
						<div id="rast-quota" style="display: inline-block; margin: 7px; position: relative; top: 4px;"></div>
					</div>
				</div>
			</div>
		</div>

		<div id="rast-warning-dialog" class="rast-modal">
			<div class="rast-modal-content">
				<div style="padding: 8px 12px; border-bottom: 1px solid #eee; cursor: pointer;">
					<strong>Warning</strong>
					<span id="rast-warning-dialog-close" class="dashicons dashicons-no-alt" style="float: right;"></span>
				</div>
				<div style="padding: 0 12px 12px 12px;">
					<p>Plagiate has been detected on this post. Do you want to publish it anyway?</p>
					<div>
						<div id="rast-publish-cancel" class="button button-primary" style="margin: 6px 6px 0 0;">Cancel</div>
						<div id="rast-publish-plagiarized-anyway" class="button" style="margin: 6px 6px 0 0;">Publish</div>
					</div>
				</div>
			</div>
		</div>

		<div id="rast-errors" class="rast-text-intro-board" align="center" style="display: none;">
			<div id="report-errors" style="margin-bottom: 1em;"></div>
		</div>

		<div id="rast-in-queue" class="rast-text-intro-board" align="center" style="display: none;"></div>

		<div id="rast-not-checked-early" class="rast-text-intro-board" style="display: none;">
			<div align="center" style="margin-bottom: 1em;">
				This post has not been checked before. Click to <strong>Publish</strong> button to check this post for plagiarism.
			</div>
		</div>

		<div id="rast-report-info" class="container-fluid" style="display: none;">
			<div class="row">
				<div class="col-6" id="progress-container">
					<p id="progress-value"></p>
					<div class="progress">
						<div id="progress" class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
					</div>
				</div>
				<div id="rast-check-post-again" class="col-6 hidden">
					<p>Check completed <span id="rast-report-time" style="color: #888;"/></p>
					<a href="javascript:void(0)" id="rast-check-again" class="button">Check Again</a>
				</div>
				<div class="col-6 results">
					<div class="doughnut">
						<svg id="multi-value" width="100%" height="100%" viewbox="0 0 180 180" xmlns="http://www.w3.org/2000/svg" version="1.1">
							<g>
								<path id="first-sector" stroke="#c55754" fill="none"></path>
							</g>
							<g>
								<path id="second-sector" stroke="#1ba694" fill="none"></path>
							</g>
						</svg>
						<svg id="single-value" class="hidden" width="100%" height="100%" viewbox="0 0 180 180" xmlns="http://www.w3.org/2000/svg" version="1.1">
							<circle id="outer-circle" stroke-width="0"/>
							<circle id="inner-circle" stroke-width="0" fill="white"/>
						</svg>
					</div>
					<div class="uniqueness">
						<span id="rast-post-uniqueness"></span>
						<p>Unique content</p>
					</div>
					<div class="plagiarized">
						<span id="rast-post-plagiarized"></span>
						<p>Plagiarized content</p>
					</div>
				</div>
			</div>
			<div class="row" id="min-uniqueness-error" style="display:none;">
				<div class="alert alert-danger" role="alert">
					<span class="dashicons dashicons-warning"></span>
					Your content is only <strong id="unique-value"></strong>. Publishing was cancelled, this may significally reduce your page SEO. Try to make it at least <strong id="min-unique"></strong>.
				</div>
			</div>
			<div id="rast-report-fragments" class="row" style="display: none;">
				<table>
					<thead>
						<tr>
							<th class="plagiarized-fragments-header">Plagiarized Fragments</th>
						</tr>
					</thead>
					<tbody id="tbody">
					</tbody>
				</table>
			</div>
		</div>

        <?php
	}

	public function insert_styles_and_scripts()
    {
		wp_enqueue_style('rast-common-style', plugins_url('../../css/common.css', __FILE__));
		wp_enqueue_style('rast-report-widget-style', plugins_url('../../css/report-widget-style.css', __FILE__));

		wp_enqueue_script('rast-utils', plugins_url('../../js/utils.js', __FILE__));
        wp_enqueue_script('rast-report-widget', plugins_url('../../js/report.widget.js', __FILE__), array('jquery', 'rast-utils'));
		
		$usage = get_option('truvisibility_plagiarism_usage');

		wp_localize_script(
			'rast-report-widget', 
			'rastSettings', 
			array(
				'nonce' => wp_create_nonce('wp_rest'),
				'restUrl' => rest_url(),
				'currentBlogId' => get_current_blog_id(),
				'checkingEnabled' => $usage['checking_enabled'] == 'on',
				'minUniqueness' => $usage['min_uniqueness']
			));
    }
}
?>