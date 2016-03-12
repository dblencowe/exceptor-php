<?php

class Exceptor_CurlHandler
{
	private $joinTimeout, $multiHandle, $requests;

	public function __construct($joinTimeout = 10)
	{
		$this->multiHandle = curl_multi_init();
		$this->requests = [];
		$this->joinTimeout = $joinTimeout;

		register_shutdown_function([$this, 'join']);
	}

	public function __destruct()
	{
		$this->join();
	}

	public function enqueue($url, $data = null, $headers = [])
	{
		$ch = curl_init();

		$new_headers = [];
		foreach ($headers as $key => $header) {
			$new_headers[] = $key . ': ' . $header;
		}
		$new_headers[] = 'Expect: ';

		curl_setopt($ch, CURLOPT_HTTPHEADER, $new_headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_VERBOSE, true);

		if (!empty($data)) {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}

		//print_r(curl_exec($ch));
		//curl_close($ch);
		curl_multi_add_handle($this->multiHandle, $ch);
		$fd = (int) $ch;
		$this->requests[$fd] = 1;
		$this->select();

		return $fd;
	}

	public function join()
	{
		$start = time();
		do {
			$this->select();
			if (empty($this->requests)) {
				break;
			}
			usleep(1000);
		}  while ($this->joinTimeout !== 0 && time() - $start > $this->joinTimeout);
	}

	private function select()
	{
		do {
			$mrc = curl_multi_exec($this->multiHandle, $active);
		} while ($mrc == CURLM_CALL_MULTI_PERFORM);

		while ($active && $mrc == CURLM_OK) {
			if (curl_multi_select($this->multiHandle) !== -1) {
				do {
					$mrc = curl_multi_exec($this->multiHandle, $active);
				} while ($mrc == CURLM_CALL_MULTI_PERFORM);
			} else {
				return;
			}
		}

		while ($info = curl_multi_info_read($this->multiHandle)) {
			$ch = $info['handle'];
			$fd = (int) $ch;
			curl_multi_remove_handle($this->multiHandle, $ch);
			if (!isset($this->requests[$fd])) {
				return;
			}
			unset($this->requests[$fd]);
		}
	}
}