<?php

/**
 * Date: 2016/7/4
 * Time: 13:19
 * $@author Zheyu
 */
class Applepns extends Abstractpns
{
    /**
     * 控制 測試模式或正式模式
     * @var bool
     */
    private $use_dev = false;

    /**
     * @var APNS 驗證檔案
     */
    private $certificate_file;

    /**
     * @var Notification $not
     */
    protected $not;

    /**
     * @var 推播用資料
     */
    protected $queues;

    /**
     * @var bool APNS callback
     */
    private $callback = array();

    /**
     * @return boolean
     */
    public function setCallback($fun)
    {
        return $this->callback = $fun;
    }

    /**
     * @param boolean $callback
     */
    public function getCallback()
    {
        return $this->callback;
    }


    /**
     * 運行推播
     * @return bool
     */
    public function send()
    {
        if (empty($this->queues)) {
            trigger_error('not have queues,Plz use set_device method', E_USER_NOTICE);
            return false;
        }

        $clinet = $this->makeSocketClient();

        if(! $clinet) {
            trigger_error("ERROR $error: $errorStr", E_USER_NOTICE);
            fclose($clinet);
            sleep(1);
            unset($clinet);
            $this->send();
            return false;
        }

        foreach ($this->queues as $key => $que) {

            $payload = $this->not->getContent($que);
            // Build the binary notification
            $msg = chr(0)
                . pack('n', 32) //token length
                . pack('H*', $que['device']) //token
                . pack('n', strlen($payload)) //length of payload
                . $payload;

            $result = @fwrite($clinet, $msg /*, strlen($msg) */);
            if (! $result) {
                fclose($clinet);
                sleep(1);
                unset($clinet);
                $this->send();
                return false;
            }
            
            $this->sendCallback($que);
            unset($this->queues[$key]);
        }

        fclose($clinet);
        return true;
    }

    /**
     * 執行 callback method
     * 送出推播我執行用的方法
     *
     * @param $arg
     * @return bool
     */
    public function sendCallback($arg)
    {
        $callback = $this->getCallback();
        if (empty($callback)) {
            return false;
        }

        if (empty($arg)) {
            return false;
        }

        return $callback[0]->$callback[1]($arg);
    }

    /**
     * 建立 socket 通道
     * @param null $stream_context
     * @return resource
     */
    private function makeSocketClient($stream_context = null)
    {
        if (empty($stream_context)) {
            $stream_context = stream_context_create();
        }

        stream_context_set_option($stream_context, 'ssl', 'local_cert', $this->getCertificateFile());
        stream_context_set_option($stream_context, 'ssl', 'passphrase', 'ltn-push');
        $clinet = stream_socket_client(
            $this->getPushUrl(),
            $error,
            $errorStr,
            60,
            STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT,
            $stream_context
        );


        stream_set_blocking($clinet, 0);
        stream_set_write_buffer($clinet, 0);

        return $clinet;
    }

    /**
     * @return array|void
     */
    public function sendFeedBack()
    {
        $stream_context = stream_context_create();
        stream_context_set_option(
            $stream_context,
            'ssl',
            'local_cert',
            $this->getCertificateFile()
        );

        $apns = stream_socket_client(
            $this->getFeedbacekUrl(),
            $error,
            $errorStr,
            60,
            STREAM_CLIENT_CONNECT,
            $stream_context
        );

        if(! $apns) {
            trigger_error("sendFeedBack method ERROR $error: $errorStr", E_USER_NOTICE);
            fclose($apns);
            unset($apns);
            return array();
        }

        $feedback_tokens = array();
        while(! feof($apns)) {
            $data = fread($apns, 38);
            if(strlen($data)) {
                $feedback_tokens[] = unpack("N1timestamp/n1length/H*devtoken", $data);
                continue;
            }
        }

        fclose($apns);
        return $feedback_tokens;
    }


    /**
     * 注入 $use_dev
     * @param mixed $use_dev 控制是否測試模式
     */
    public function setUseDev($use_dev)
    {
        $this->use_dev = $use_dev;
    }

    /**
     * 取得驗證檔案位置
     * @return string
     */
    private function getCertificateFile()
    {
        return ($this->use_dev) ?
            _JSONPATH . '/api/apns_dev.pem' :
            _JSONPATH . '/api/apns_pro.pem';
    }

    /**
     * 注入驗證檔案
     * @param string $certificate_file
     */
    public function setCertificateFile($certificate_file)
    {
        if (! file_exists($certificate_file)) {
            trigger_error('Error: apns_dev.pem is not exist', E_USER_ERROR);
            exit;
        }

        $this->certificate_file = $certificate_file;
    }

    /**
     * 取得 PNS 位置
     * @return string
     */
    private function getPushUrl()
    {
        $apns_host = ($this->use_dev) ?
            'gateway.sandbox.push.apple.com' :
            'gateway.push.apple.com';

        return 'ssl://' . $apns_host . ':2195';
    }

    /**
     * apple 提供 feedback 位置
     * @return string
     */
    private function getFeedbacekUrl()
    {
        $apns_host = ($this->use_dev) ?
            'feedback.sandbox.push.apple.com' :
            'feedback.push.apple.com';

        return 'ssl://' . $apns_host . ':2196';
    }

}
