<?php

/**
 * Created by PhpStorm.
 * User: James
 * Date: 2016/7/4
 * Time: 13:39
 */
class Notification
{
	/**
	 * @var array
	 */
	private $types = array('google', 'apple');

	/**
	 * @var
	 */
	private $type;

	/**
	 * Notification constructor.
	 * @param $type
	 */
	public function __construct($type)
	{
		if (! in_array($type, $this->types)) {
			trigger_error('Plz Setting google or apple of type', E_USER_ERROR);
			exit;
		}

		$this->type = $type;
	}

	/**
	 * @param array $item
	 * @return array|string
	 */
	public function getContent(Array $item)
	{
		switch ($this->type) {
		case 'apple':

			$content = array(
				'aps' => array(
					'alert' => array('body' => $item['title']),
					'sound' => 'default'
				),
				'custom_key' => $item['content']
			);

			return  trim(json_encode($content));
		case 'google':

			return array(
				'message' => $item['title'],
				'text' => $item['content']
			);
		}

	}
}
