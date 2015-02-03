<?php

namespace CTI\MongoServiceBundle;

use CTI\MongoServiceBundle\Exception\ConnectionException;

/**
 * Mongo client offering a retry mechanism for connections
 *
 * @package CTI\MongoServiceBundle
 * @author  Georgiana Gligor <g@lolaent.com>
 */
class MongoService
{
    const MONGO_PREFIX = 'mongodb://';

    /** @var  \MongoClient */
    protected $client;

    /** @var  integer */
    protected $retries;

    /**
     * @param string $host
     * @param string $port
     * @param string $db
     * @param string $user
     * @param string $pass
     * @param string $retries
     * @param string $replicaSet
     *
     * @throws ConnectionException
     */
    public function __construct($host, $port, $db, $user, $pass, $retries, $replicaSet)
    {
        $this->retries = $retries;

        $mongoUrl = self::MONGO_PREFIX;

        if (!empty($user) && !empty($pass)) {
            $mongoUrl .= sprintf('%s:%s@', $user, $pass);
        }

        if (strlen($replicaSet)) {
            // replicaset means we are setting everything in mongo_host, so we don't care about mongo_port at this point
            $mongoUrl .= sprintf('%s/%s?replicaSet=%s', $host, $db, $replicaSet);
        } else {
            // single machine mode
            $mongoUrl .= sprintf('%s:%s/%s', $host, $port, $db);
        }

        $this->connect($mongoUrl, $retries);
    }

    /**
     * Destructor makes sure connections are closed
     */
    public function __destruct()
    {
        if ($this->client instanceof \MongoClient) {
            $this->client->close(true);
        }
        unset($this->client);
    }

    /**
     * Connects to the mongo DB, and performs retries
     *
     * @param string  $mongoUrl
     *
     * @throws ConnectionException
     */
    public function connect($mongoUrl)
    {
        $this->client = new \MongoClient($mongoUrl, array("connect" => false));
        $i = 0;
        while ($i <= $this->retries) {
            try {
                $this->client->close(true);
                $this->client->connect();
                break;
            } catch (\Exception $e) {
                $i++;
                if ($i >= $this->retries) {
                    throw new ConnectionException(sprintf('Unable to connect to Mongo after %s retries', $this->retries), null, $e);
                }
            }
        }
    }

    /**
     * @param \MongoClient $client
     *
     * @return $this
     */
    public function setClient($client)
    {
        $this->client = $client;

        return $this;
    }

    /**
     * @return \MongoClient
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param int $retries
     *
     * @return $this;
     */
    public function setRetries($retries)
    {
        $this->retries = $retries;

        return $this;
    }

    /**
     * @return int
     */
    public function getRetries()
    {
        return $this->retries;
    }
}
