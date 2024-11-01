<?php
class TruVisibilityPlagiarismHistory
{
	public function __construct() 
	{
		add_action('admin_menu', array($this, 'init'));

		if (isset($_GET['page']) && $_GET['page'] == 'truvisibility_plagiarism_history')
		{
			add_action('admin_enqueue_scripts', array($this, 'insert_styles_and_scripts'));
		}
	}

	 public function init() 
	{
		add_submenu_page(
			'truvisibility_plagiarism_settings',
			'TruVisibility Plagiarism &raquo; Checks History',
			'History',
			'publish_posts',
			'truvisibility_plagiarism_history',
			array($this, 'fill_content'));
	}

	public function fill_content() 
	{
		?>
		<div class="wrap">
			<div style="float: right; margin-top: 7px;">
				<div class="rast-time-selector">
					<div id="rast-interval-month" class="item" onclick="window.location.hash = 'month'">Month</div>
					<div id="rast-interval-week" class="item" onclick="window.location.hash = 'week'">Week</div>
					<div id="rast-interval-day" class="item" onclick="window.location.hash = 'day'">Day</div>
				</div>
			</div>
			<div>
				<h1><?php echo esc_html(get_admin_page_title()); ?></h1>
			</div>				
		</div>
		<div class="wrap">
			<div id="rast-history-label-empty" class="history-empty" style="display: none;">
				<h2>Your history of checks is empty</h2>
				<p>Go to the <a href="<?php get_admin_url() ?>edit.php">posts editing page</a> and use TruVisibility Plagiarism Checker to ensure your content is unique.</p>
			</div>
			<table id="rast-history-list" class="wp-list-table widefat fixed striped posts"></table>
		</div>
		<?php
	}

	public function insert_styles_and_scripts() 
	{
		wp_enqueue_style('rast-history-style', plugins_url('../css/history-style.css', __FILE__));

		wp_enqueue_script('rast-utils', plugins_url('../js/utils.js', __FILE__));
		wp_enqueue_script('rast-history', plugins_url('../js/history.js', __FILE__), array('jquery', 'rast-utils'));
		
		wp_localize_script('rast-history', 'rastSettings', array('restUrl' => rest_url(), 'adminUrl' => get_admin_url(), 'blogId' => get_current_blog_id()));
	}
}
?>