<?php

class Exceptor_HandleError
{
	var $logger = "PHP";
	var $apiVersion = 1;
	var $oldErrorHandler;
	var $oldExceptionHandler;
	var $callOldHandlers = true;

	var $fullDSN = null;
	var $loggingServer = null;
	var $endpoint = null;
	var $publicKey = null;
	var $secretKey = null;
	var $project = null;

	var $hostName = null;
	var $currentUrl = null;
	var $sessionData = null;
	var $cookieData = null;
	var $trace = null;
	var $message = null;

	var $curlHandler = null;

	public function __construct($dsn = null)
	{
		$this->fullDSN = $dsn;
		if (empty($this->fullDSN)) {
			$this->fullDSN = getenv('EXCEPTOR_DSN');
		}

		if (empty($this->fullDSN) or !$parsedDSN = self::parseDSN($this->fullDSN)) {
			throw new InvalidArgumentException('Invalid DSN supplied for Exceptor');
		}

		$this->registerHandlers();

		$this->loggingServer = $parsedDSN['host'];
		$this->publicKey = $parsedDSN['user'];
		$this->privateKey = $parsedDSN['pass'];

		$this->endpoint = sprintf('%s://%s%s', $parsedDSN['scheme'], $parsedDSN['host'], $parsedDSN['path']);
	}

	public function handleException($e, $isError = false)
	{
		// Init the cUrl handler
		$this->curlHandler = new Exceptor_CurlHandler();

		$this->hostName = php_uname('n');
		$this->currentUrl = self::getCurrentUrl();
		$this->sessionData = isset($_SESSION) ?: null;
		$this->cookieData = $_COOKIE;

		$this->trace = $e->getTrace();
		$this->message = $e->getMessage();

		$this->curlHandler->enqueue($this->endpoint,
			self::compileData(),
			['Authorization' => self::createBasicAuthHeader()]
		);

		var_dump($isError, $this->callOldHandlers, $this->oldExceptionHandler);
		if (!$isError && $this->callOldHandlers && $this->oldExceptionHandler) {
			call_user_func($this->oldExceptionHandler, $e);
		}
	}

	public function handleError($code, $message, $file = '', $line = 0, $context = array())
	{
		$e = new ErrorException($message, 0, $code, $file, $line);
		$this->handleException($e, true, $context);

		if ($this->callOldHandlers) {
			if ($this->oldErrorHandler !== null) {
				return call_user_func($this->oldErrorHandler, $code, $message, $file, $line, $context);
			} else {
				return false;
			}
		}
	}

	public function handleFatalError()
	{
		if (null === $lastError = error_get_last()) {
			return;
		}

		if ($lastError['type']) {
			$e = new ErrorException(
				@$lastError['message'], @$lastError['type'], @$lastError['type'],
				@$lastError['file'], @$lastError['line']
			);
			$this->handleException($e, true);
		}
	}

	private function compileData()
	{
		$formattedData = [
			'project_id' => $this->project,
			'url' => implode('', $this->currentUrl),
			'session_data' => json_encode($this->sessionData),
			'cookie_data' => json_encode($this->cookieData),
			'handler' => $this->logger,
			'trace' => json_encode($this->trace),
			'message' => $this->message,
		];

		return $formattedData;
	}

	private function parseDSN($dsn)
	{
		$dsn = parse_url($dsn);
		$dsn['path'] = explode('/', $dsn['path']);
		$this->project = array_pop($dsn['path']);
		$dsn['path'] = implode('/', $dsn['path']);
		return $dsn;
	}

	private function getCurrentUrl()
	{
		if (php_sapi_name() == "cli") {
			return "CLI";
		}

		$urlParts = [];
		$urlParts['protocol'] = strpos(strtolower($_SERVER['SERVER_PROTOCOL']),'https')
		            === FALSE ? 'http://' : 'https://';
		$urlParts['host'] = $_SERVER['HTTP_HOST'];
		$urlParts['script'] = $_SERVER['SCRIPT_NAME'];
		$urlParts['params'] = $_SERVER['QUERY_STRING'];

		return $urlParts;
	}

	private function registerHandlers()
	{
		$this->oldExceptionHandler = set_exception_handler(array($this, 'handleException'));
		$this->oldErrorHandler = set_error_handler(array($this, 'handleError'), E_ALL);
	}

	private function createBasicAuthHeader()
	{
		return "Basic " . base64_encode($this->publicKey . ':' . $this->privateKey);
	}
}