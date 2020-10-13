<?php

namespace Cego\Traits;

use ZMQ;
use ZMQSocket;
use ZMQContext;
use ZMQSocketException;
use Cego\DelegationProxyConnection;

/**
 * Trait SendsZMQHeartbeats
 *
 * @property string $name
 */
trait SendsZMQHeartbeats
{
    /**
     * Holds the ZMQ socket once initialized
     *
     * @var ZMQSocket $socket
     */
    protected static $socket = null;

    /**
     * Holds the current heartbeat count (Default is 0)
     *
     * @var int $count
     */
    protected $count = 0;

    /**
     * Tells the recipient to reset its heartbeat count
     *
     * @var bool $resetCounter
     */
    protected $resetCounter = true;

    /**
     * Sends a heartbeat over a ZMQSocket
     */
    public function sendZMQHeartbeat()
    {
        if (self::$socket == null) {
            self::$socket = $this->initializeSocket();
        }

        // Connect to socket af send heartbeat message
        DelegationProxyConnection::from($this->name)
            ->connectTo(self::$socket)
            ->sendMessage('magnet_activate', 'heartbeat', [
                'message'    => sprintf('Service [%s] - Heartbeat', $this->name),
                'count'      => $this->count,
                'reset'      => $this->resetCounter,
                'identifier' => $this->name
            ]);

        /**
         * Makes sure that only the first heartbeat, after service has been
         * started, will tell the recipient to reset the heartbeat count
         */
        if ($this->resetCounter) {
            $this->resetCounter = false;
        }

        // We limit the max integer size to the equivalent of a signed 32bit integer (2.147.483.648)
        $maxInt = pow(2, 32) / 2;

        // Increment the heartbeat count and make sure we rollover when we reach our max integer size
        $this->count++;

        if ($this->count > $maxInt) {
            $this->count = 0;
            $this->resetCounter = true;
        }
    }

    /**
     * Initializes the ZMQ socket to use for heartbeats
     *
     * @return ZMQSocket
     *
     * @throws ZMQSocketException
     */
    protected function initializeSocket()
    {
        $persistentId = env('DELEGATION_PROXY_PERSISTENT_ID', 'delegation_proxy');

        return new ZMQSocket(new ZMQContext, ZMQ::SOCKET_PUB, $persistentId);
    }
}
