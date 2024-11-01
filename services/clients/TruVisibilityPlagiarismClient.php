<?php
class TruVisibilityPlagiarismClient
{
    private $plagiarism_endpoint;

	private $auth_client;

	public function __construct($auth_client)
	{
		$this->plagiarism_endpoint = 'https://analytics.'.TruVisibilityPlagiarismConfig::$TvUmbrellaRoot.'/api/account/plagiarism';
		$this->auth_client = $auth_client;
	}

    public function check_post($post_id, $title, $html)
    {
		$this->auth_client->update_access_if_expired();

		$body = array(
			'title' => $title,
	        'html' => $html,
	        'stopLimit' => 100 - get_option('truvisibility_plagiarism_usage')['min_uniqueness']);

		$args = $this->default_args();
		$args['body'] = $body;

		$response = wp_remote_post($this->plagiarism_endpoint.'/texts/'.$post_id, $args);
		
		return self::parse($response);
    }

	public function get_report($post_id, $post_time, $get_details = false)
	{
		$this->auth_client->update_access_if_expired();

		$url = $this->plagiarism_endpoint.'/reports/'.$post_id.'?time='.$post_time;
		$url = $get_details ? $url.'&getDetails=true&getTexts=true' : $url;

		$response = wp_remote_get($url, $this->default_args());

		return self::parse($response);
	}

	public function get_checked_posts()
	{
		$this->auth_client->update_access_if_expired();

		$response = wp_remote_get($this->plagiarism_endpoint.'/posts', $this->default_args());

		return self::parse($response);
	}

	public function get_checked_posts_from_interval($from_date, $to_date)
	{
		$this->auth_client->update_access_if_expired();

		$url = $this->plagiarism_endpoint.'/posts-by-time?fromTime='.$from_date.'&toTime='.$to_date;

		$response = wp_remote_get($url, $this->default_args());

		return self::parse($response);
	}

	public function get_extra_checks_price()
	{
		$this->auth_client->update_access_if_expired();

		$response = wp_remote_get($this->plagiarism_endpoint.'/checks-price', $this->default_args());

		return self::parse($response);
	}

	public function buy_extra_checks()
	{
		$this->auth_client->update_access_if_expired();

		$response = wp_remote_post($this->plagiarism_endpoint.'/checks-this-month', $this->default_args());

		return self::parse($response);
	}

	public function get_quota($with_details = false)
	{
		$this->auth_client->update_access_if_expired();

        $response = wp_remote_get($this->plagiarism_endpoint.'/checks-this-month?withDetails='.json_encode($with_details), $this->default_args());

		return self::parse($response);
	}

	public function get_plans()
	{
		$this->auth_client->update_access_if_expired();

        $response = wp_remote_get($this->plagiarism_endpoint.'/plans', $this->default_args());

		return self::parse($response);
	}

	public function notify_about_plagiarized_post($email, $title, $url, $uniqueness, $min_uniqueness)
	{
		$request_url = $this->plagiarism_endpoint.'/notifications?email='.$email.'&title='.$title.'&url='.urlencode($url).'&uniqueness='.$uniqueness.'&minUniqueness='.$min_uniqueness;
		$response = wp_remote_post($request_url, $this->default_args());

		return self::parse($response);
	}

	private static function parse($response)
	{
		return (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200) ? $response : json_decode($response['body']);
	}

	private function default_args()
	{
		return array(
			'timeout' => 30, 
			'sslverify' => TruVisibilityPlagiarismConfig::$SslVerify, 
			'headers' => array('Authorization' => 'Bearer '.get_option('truvisibility_plagiarism_account')['access-token']));
	}
}
?>