<?php
/**
 * APNS 多執行緒實作類別
 * Class Apnsthread
 *
 * 1. 實作 makeData
 * 2. 實作 runnable
 * 3. 實作 setThreadsDataLimit
 * 4. 實作 setThreadsLimit
 * @author Zheyu
 */
class Apnsthread extends Abstractthread
{
	/**
	 * @var array 共用資源
	 */
	private $data;

	/**
	 * @var Pushqueue model
	 */
	private  $queue_model;

	/**
	 * Apnsthread constructor.
	 * @param array $data
	 * @param Pushqueue $queue_model
	 */
	public function __construct(Array $data, Pushqueue $queue_model)
	{
		$this->data = $data;
		$this->queue_model = $queue_model;

	}

	/**
	 * 設定資料筆數
	 * @return int
	 */
	public function setThreadsDataLimit()
	{
		return 1500;
	}

	/**
	 * 設定 process limit
	 * @return int
	 */
	public function setThreadsLimit()
	{
		return 20;
	}

	/**
	 * 實作共用資源方法
	 * 如不使用 data 共同資源
	 * 請在建構子執行 $this->unsetData();
	 * @return array
	 */
	public function makeData()
	{
		return $this->data;
	}

	/**
	 * 實作可運行方法
	 * @param array $data
	 */
	public function runnable($data)
	{
		ltnlog::setFilePath(_JSONPATH . '/pns/apns_thread', getmypid());
		ltnlog::doWrite('handle data: ' . count($data));

		if (empty($data)) {
			ltnlog::doWrite('threads empty data!!');
			trigger_error('threads empty data!!', E_USER_NOTICE);
			exit;
		}
		$start_time = microtime(true);
		$applepns = new Applepns();
		$applepns->setCallback(array($this, 'callback'));
		$applepns->setQueues($data);

		$applepns->setNotification(
			new Notification('apple')
		);

		$applepns->send();
		usleep(1000);
		$end_time = microtime(true);
		ltnlog::doWrite('Send APNS time consuming: ' . intval($end_time - $start_time) . 's');
	}

	/**
	 * $applepns use parent process callback
	 * @param array $data
	 */
	public function callback($data)
	{
		$this->queue_model->delete_queue($data['no']);
	}

}
