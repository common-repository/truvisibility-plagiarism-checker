<?php
class TruVisibilityPlagiarismRestApi
{
	private $plagiarism_client;

	private $auth_client;

	public function __construct($plagiarism_client, $auth_client) 
	{
		$this->plagiarism_client = $plagiarism_client;
		$this->auth_client = $auth_client;

		add_action('rest_api_init', array($this, 'init'));
	}

	public function init()
	{
		register_rest_route('truvisibility/plagiarism/v1', '/auth/(?P<code>[\\w\\d]+)', array('methods' => 'GET', 'callback' => array($this, 'complete_authorization')));
		register_rest_route('truvisibility/plagiarism/v1', '/auth/clean', array('methods' => 'POST', 'callback' => array($this, 'clean_authorization')));
		register_rest_route('truvisibility/plagiarism/v1', '/plans', array('methods' => 'GET', 'callback' => array($this, 'get_plans')));
		register_rest_route('truvisibility/plagiarism/v1', '/account', array('methods' => 'GET', 'callback' => array($this, 'get_account')));
		register_rest_route('truvisibility/plagiarism/v1', '/account/ensure', array('methods' => 'POST', 'callback' => array($this, 'ensure_account')));
		register_rest_route('truvisibility/plagiarism/v1', '/history', array('methods' => 'GET', 'callback' => array($this, 'get_history')));
		register_rest_route('truvisibility/plagiarism/v1', '/checks/quota', array('methods' => 'GET', 'callback' => array($this, 'get_quota')));
		register_rest_route('truvisibility/plagiarism/v1', '/checks/quota/extended', array('methods' => 'GET', 'callback' => array($this, 'get_quota_extended')));
		register_rest_route('truvisibility/plagiarism/v1', '/checks/price', array('methods' => 'GET', 'callback' => array($this, 'get_extra_checks_price')));
		register_rest_route('truvisibility/plagiarism/v1', '/checks/buy', array('methods' => 'POST', 'callback' => array($this, 'buy_extra_checks')));
		register_rest_route('truvisibility/plagiarism/v1', '/texts/(?P<post_id>[\\w-//]+)', array('methods' => 'POST', 'callback' => array($this, 'check_post')));
		register_rest_route('truvisibility/plagiarism/v1', '/reports/(?P<post_id>[\\w-//]+)', array('methods' => 'GET', 'callback' => array($this, 'get_report')));
		register_rest_route('truvisibility/plagiarism/v1', '/posts/(?P<post_id>[\\d-]+)/publish', array('methods' => 'POST', 'callback' => array($this, 'mark_post_to_publish_anyway')));
	}

	function get_plans()
	{
		return $this->plagiarism_client->get_plans();
	}

	function get_account()
	{
		$account = array(
			'name' => get_option('truvisibility_plagiarism_account')['name'], 
			'quota' => $this->plagiarism_client->get_quota(false));

		return $account;
	}

	function ensure_account()
	{
		return $this->auth_client->ensure_account();
	}

	function check_post($request)
	{
		$body_params = $request->get_body_params();

		$report = $this->plagiarism_client->check_post($request['post_id'], $body_params['title'], $body_params['html']);
		
		if (is_wp_error($report) || is_array($report))
		{
			return $report;
		}

		$post_id = split('/', $request['post_id'])[2];
		$post = get_post($post_id);
		$post->post_title = $body_params['title'];
		$post->post_content = $body_params['html'];
		$post->post_status = 'enqueued';

		wp_update_post($post);
		wp_schedule_event(time(), '5_seconds', 'truvisibility_plagiarism_check_status', array($request['post_id'].';'.$report->time));

		return $report;
	}

	function get_report($request)
	{
		$report = $this->plagiarism_client->get_report($request['post_id'], $request['post_time'], true);

		return $report;
	}

	function get_history($request)
	{
		if (isset($request['from_time']) && isset($request['to_time']))
		{
			return $this->plagiarism_client->get_checked_posts_from_interval($request['from_time'], $request['to_time']);
		}

		return $this->plagiarism_client->get_checked_posts();
	}

	function get_quota($request)
	{
		return $this->plagiarism_client->get_quota(false);
	}

	function get_quota_extended($request)
	{
		return $this->plagiarism_client->get_quota(true);
	}

	function get_extra_checks_price($request)
	{
		return $this->plagiarism_client->get_extra_checks_price();
	}

	function buy_extra_checks($request)
	{
		return $this->plagiarism_client->buy_extra_checks();
	}

	function complete_authorization($request)
	{
		$this->auth_client->save_access($request['code']);
	}

	function clean_authorization($request)
	{
		delete_option('truvisibility_plagiarism_account');
	}

	function mark_post_to_publish_anyway($request)
	{
		return add_post_meta(intval($request['post_id']), 'truvisibility_plagiarism_publish_anyway', true, true);
	}
}
?>