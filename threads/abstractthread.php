<?php
/**
 * Class Abstractthread
 *
 * 多執行緒抽象類別
 *
 * @author Zheyu
 */
abstract class Abstractthread
{
	/**
	 * 限制實作方法 data
	 * thread 產生共同資源方法
	 *
	 * @return mixed
	 */
	abstract function makeData();

	/**
	 * 限制實作方法
	 * 設定 process limit
	 * 
	 * @return mixed
	 */
	abstract function setThreadsLimit();

	/**
	 * 限定實作方法
	 * process data limit
	 *
	 * @return mixed
	 */
	abstract function setThreadsDataLimit();


	/**
	 * 限制實作方法
	 * 單一執行緒運行的方法
	 *
	 * @param array $data
	 * @return mixed
	 */
	abstract function runnable($data);

	/**
	 * @var pid
	 */
	private $pid;

	/**
	 * @var int 限制開啟幾個執行緒
	 */
	private $threads_limit = 10;

	/**
	 * @var int 限制資源每次的數量
	 */
	private $threads_data_limit = 20;

	/**
	 * @var bool 是否使用 data
	 */
	private $use_data = true;

	/**
	 * 共用資料
	 * @var array
	 */
	private $data = array();

	/**
	 * @var bool 是否執行 renice
	 */
	protected $is_renice = false;

	/**
	 * @var int 記憶體計算單位
	 */
	private $rafa = 1024;

	/**
	 * @var array 記憶體資訊
	 */
	private $memory_info;

	/**
	 * @var mixed 可用記憶體
	 */
	private $memory_free;

	/**
	 * @var mixed 記憶體總數
	 */
	private $memory_total;

	/**
	 * @var string 記憶體限制值
	 */
	private $memory_limit;

	/**
	 *  constructor.
	 */
	public function __construct()
	{
		if (!self::isAvailable()) {
			trigger_error('Threads not supported', E_USER_ERROR);
			exit;
		}
		$this->threads_limit = $this->setThreadsLimit();
		$this->memory_limit = $this->getThreadMemoryLimit();

		$this->resizeThreadsLimit();
	}

	/**
	 * 判斷是否要用共通資料
	 * @return array
	 */
	public function unsetData()
	{
		return $this->use_data = false;
	}

	/**
	 * pcntl_fork function_exists
	 * @return bool
	 */
	private static function isAvailable()
	{
		return function_exists('pcntl_fork');
	}

	/**
	 * 判斷是否有 data
	 * @return bool
	 */
	private function checkDataOk()
	{
		if (empty($this->data)) {
			return false;
		}

		if (! is_array($this->data)) {
			return false;
		}

		return true;
	}

	/**
	 * 運算單一執行緒資料限制筆數
	 *
	 * @return int|mixed
	 */
	private function makeThreadsDataLimit()
	{
		$this->data = $this->makeData();
		if (!$this->checkDataOk()) {
			trigger_error('Not have data / data not Array type', E_USER_ERROR);
			exit;
		}
		$threads_data_limit = (int)ceil(count($this->data) / $this->threads_limit);
		if ($threads_data_limit >= $this->setThreadsDataLimit()) {
			return $this->setThreadsDataLimit();
		}

		return $threads_data_limit;
	}

	/**
	 * @param $thread
	 * @return array
	 */
	private function makeThreadsData($thread)
	{
		$threads_start_data = ($thread - 1) * $this->threads_data_limit;
		return array_slice(
			$this->data,
			$threads_start_data,
			$this->threads_data_limit
		);
	}

	/**
	 * 計算 process 開幾條
	 * resize threads limit
	 */
	private function resizeThreadsLimit()
	{
		if ($this->use_data) {
			$this->threads_data_limit = $this->makeThreadsDataLimit();
			$threads_limit =  (int)ceil(count($this->data) / $this->threads_data_limit);
			$this->threads_limit = ($threads_limit > $this->threads_limit) ? $this->threads_limit : $threads_limit;
		}
	}

	/**
	 * 運行 start
	 */
	public function start()
	{
		self::__construct();

		ltnlog::doWrite('Threads limit:' . $this->threads_limit);
		for ($i = 1; $i <= $this->threads_limit; $i++) {
			$pid = @pcntl_fork();
			if ($pid == -1) {
				trigger_error('pcntl_fork() returned a status of -1. No new process was created', E_USER_ERROR);
				exit;
			}

			if ($pid) {
				ltnlog::doWrite('Open Child Process : ' . $pid);
				$this->pids[] = $pid;
				continue;
			}
			break;
		}

		if ($pid) {
			foreach($this->pids as $pid) {
				pcntl_waitpid($pid, $status = 0);
				ltnlog::doWrite('Close Child Process:' . $pid);
			}

			ltnlog::doWrite('Close Parent Process');
			return true;
		}

		if (! $this->getIsRenice()) {
			$this->renice(19);
		}

		usleep(2000);
		ini_set('memory_limit', $this->memory_limit); // 設定每條 process 記憶體大小
		$this->runnable($this->makeThreadsData($i)); // 運行 sub process 方法
		return true;
	}

	public static function ohterAlive()
	{

	}

	/**
	 * @return mixed
	 */
	public function getPid()
	{
		return $this->pid;
	}

	/**
	 * 判斷 process 是否存在 use pid
	 * @param $pid
	 * @return bool
	 */
	public function isAlive($pid)
	{
		$pid = pcntl_waitpid($pid, $status, WNOHANG);
		return ($pid === 0);

	}

	/**
	 * 停止 process
	 * @param $pid
	 * @param int $signal
	 * @param bool $wait
	 */
	public function stop($pid, $signal = SIGKILL, $wait = false)
	{
		if ($this->isAlive()) {
			posix_kill($pid, $signal);
			if ($wait) {
				pcntl_waitpid($pid, $status = 0);
			}
		}
	}

	/**
	 * 設定 process 執行優先值
	 * @param $num renice
	 */
	public function renice($num)
	{
		$this->setIsRenice(true);
		proc_nice($num);
	}

	/**
	 * 刪除 process
	 * @param int $signal
	 * @param bool $wait
	 */
	public function kill($signal = SIGKILL, $wait = false)
	{
		return $this->stop($signal, $wait);
	}

	/**
	 * handleSignal
	 * @param $signal
	 */
	protected function handleSignal($signal)
	{
		switch($signal) {
		case SIGTERM:
			exit( 0 );
			break;
		}
	}

	/**
	 * @return boolean
	 */
	public function getIsRenice()
	{
		return $this->is_renice;
	}

	/**
	 * @param boolean $is_renice
	 */
	public function setIsRenice($is_renice)
	{
		$this->is_renice = $is_renice;
	}

	/**
	 * 取得系統記憶體資訊
	 * @return array
	 */
	public function getServerMemoryInfo()
	{
		$free = shell_exec('free');
		$free = (string)trim($free);
		$free_arr = explode("\n", $free);
		$mem = explode(" ", $free_arr[1]);
		return array_values(array_filter($mem));
	}

	/**
	 * 取得系統記憶體可使用量
	 * @return string
	 */
	public function getThreadMemoryLimit()
	{
		$this->memory_info = $this->getServerMemoryInfo();
		$this->memory_free = $this->memory_info[3];
		$this->memory_total = $this->memory_info[1];
		$mem = intval($this->memory_free / $this->rafa / $this->threads_limit);

		return ($mem > 256) ? 256 . 'M' : $mem . 'M';
	}
}
