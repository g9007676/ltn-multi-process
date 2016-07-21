<?php

/**
 * Created by PhpStorm.
 * User: James
 * Date: 2016/7/4
 * Time: 13:17
 */
abstract class Abstractpns
{
	/**
	 * 實作推播方法
	 * @return mixed
	 */
	abstract function send();

	/**
	 * 推播用資料
	 * @var
	 */
	protected $queues;

	/**
	 * Notification not
	 * @var
	 */
	protected $not;

	/**
	 * 寫入排成資料
	 * @param $queues
	 */
	public function setQueues(Array $queues)
	{
		$this->queues = $queues;
	}

	/**
	 * @return mixed
	 */
	public function getQueues()
	{
		return $this->queues;
	}

	/**
	 * 注入 Notification 物件
	 * @param Notification $not
	 */
	public function setNotification(Notification $not)
	{
		$this->not = $not;
	}
}
