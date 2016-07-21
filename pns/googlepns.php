<?php
class Googlepns extends Abstractpns
{
	/**
	 * @var string google secret key
	 */
	private $api_key = 'AIzaSyDtyGcy0Yz2IikvPsbHewT7jUNGIGRLkII';

	/**
	 * @var string push gcm url
	 */
	private $url = 'https://gcm-http.googleapis.com/gcm/send';

	/**
	 * @var Notification $not
	 */
	protected $not;

	/**
	 * @var 推播用資料
	 */
	protected $queues;

	/**
	 * @var array
	 */
	private $feedbackTokens = array();

	/**
	 * @return array
	 */
	public function getFeedbackTokens()
	{
		return $this->feedbackTokens;
	}

	/**
	 * @param array $feedbackTokens
	 */
	public function setFeedbackTokens($feedbackToken)
	{
		$this->feedbackTokens = $feedbackToken;
	}

	/**
	 * 運行推播
	 * @return mixed
	 */
	public function send()
	{
		if (empty($this->queues)) {
			trigger_error('not have queues,Plz use set_device method', E_USER_NOTICE);
			return false;
		}

		$feedback = array();
		$json = array(
			'registration_ids' => $this->queues['devices'],
			'data' => $this->not->getContent($this->queues)
		);

		$headers = array(
			'Authorization: key=' . $this->api_key,
			'Content-Type: application/json'
		);

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $this->url);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);	//忽略SSL驗證
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($json));
		$ret = curl_exec($curl);
		curl_close($curl);

		$this->setFeedbackTokens(json_decode($ret, true));
	}
}
