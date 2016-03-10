<?php
/**
 * Base class for data processing.
 *
 * @package exceptor
 */
abstract class Exceptor_Processor
{
    public function __construct(Exceptor_Client $client)
    {
        $this->client = $client;
    }

    /**
     * Process and sanitize data, modifying the existing value if necessary.
     *
     * @param array $data   Array of log data
     */
    abstract public function process(&$data);
}
