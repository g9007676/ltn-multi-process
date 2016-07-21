<?php
/**
 * gcm 多執行緒實作類別
 * Class Gcmthread
 *
 * 1. 實作 makeData
 * 2. 實作 runnable
 * 3. 實作 setThreadsDataLimit
 * 4. 實作 setThreadsLimit
 * @author Zheyu
 */
class Gcmthread extends Abstractthread
{
	/**
	 * @var array 共用資源
	 */
	private $data;

	/**
	 * @var Pushqueue model
	 */
	private $queue_model;

	/**
	 * @var Device model
	 */
	private $device_model;

	/**
	 * Gcmthread constructor.
	 * @param array $data
	 * @param Pushqueue $queue_model
	 * @param Device $device_model
	 */
	public function __construct(Array $data, Pushqueue $queue_model, Device $device_model)
	{
		$this->data = $data;
		$this->queue_model = $queue_model;
		$this->device_model = $device_model;
	}

	/**
	 * 設定資料筆數
	 * @return int
	 */
	public function setThreadsDataLimit()
	{
		return 10000;
	}

	/**
	 * 設定 process limit
	 * @return int
	 */
	public function setThreadsLimit()
	{
		return 3;
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
	 *  運行 runnable
	 * @param array $queues
	 */
	public function runnable($queues)
	{
		ltnlog::setFilePath(_JSONPATH . '/pns/gcm_thread', getmypid());
		ltnlog::doWrite('handle data: ' . count($queues));

		if (empty($queues)) {
			ltnlog::doWrite('threads empty data!!');
			trigger_error('threads empty data!!', E_USER_NOTICE);
			exit;
		}

		$data = array();
		$index = 0;

		foreach ($queues as $key => $que) {

			if ($key % 500 == 0) {
				$index++;
			}
			$data[$index][] = $que;
		}
		unset($queues);
		ltnlog::doWrite('DO LOOP!!! : ' . count($data));

		$gcmpns = new Googlepns();
		$gcmpns->setNotification(
			new Notification('google')
		);

		$start_time = microtime(true);
		foreach($data as $val) {
			$devices = array();
			foreach ($val as $sval) {
				$devices[] = $sval['device'];
			}
			ltnlog::doWrite('send devices :' . count($devices));

			$gcmpns->setQueues(
				array_merge(
					$val[0],
					array('devices' => $devices)
				)
			);

			$gcmpns->send();
			$this->callback($gcmpns->getFeedbackTokens(), $val);
		}
		$end_time = microtime(true);
		ltnlog::doWrite('Send gcm time consuming: ' . intval($end_time - $start_time) . 's');
	}

	/**
	 * GCM callback
	 * @param $feedback
	 * @param $queues
	 */
	public function callback($feedback, $queues)
	{
		if (empty($feedback)) {
			ltnlog::doWrite('empty feedback !!! end!!');
			exit;
		}

		foreach($feedback['results'] as $key => $val) {
			$que = $queues[$key];
			$this->queue_model->delete_queue($que['no']);

			if ((! empty($val['error']) && 'NotRegistered' == $val['error']) ||
				(! empty($val['error']) && 'InvalidRegistration' == $val['error'])
			) {
				$this->device_model->update_device_bydevice(array('LTND_Status' => 3), $que['device']);
			}
		}
	}
}
