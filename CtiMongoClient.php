<?php

namespace CTI\MongoServiceBundle;

use CTI\MongoServiceBundle\Exception\MongoException;
use CTI\MongoServiceBundle\Interfaces\LastUpdated;
use JMS\Serializer\SerializerBuilder;

class CtiMongoClient
{
    /** @var  MongoConnectionWrapper */
    protected $client;

    /** @var  string */
    protected $databaseName;

    /** @var  string */
    protected $collectionName;

    /**
     * @param MongoConnectionWrapper $mongoService
     * @param string         $mongoDb
     * @param string         $mongoColl
     */
    public function __construct(MongoConnectionWrapper $mongoService, $mongoDb, $mongoColl)
    {
        $this->client = $mongoService;
        $this->databaseName = $mongoDb;
        $this->collectionName = $mongoColl;
    }

    /**
     * @param array $query
     * @param array $fields
     *
     * @return \MongoCursor|null
     *
     * @throws MongoException
     */
    public function find(array $query = array(), array $fields = array())
    {
        $i = 0;
        $retries = $this->client->getRetries();
        $cursor = null;
        while ($i <= $retries) {
            try {
                $cursor = $this->getClient()->getClient()
                    ->selectDB($this->databaseName)
                    ->selectCollection($this->collectionName)
                    ->find($query, $fields);

                break;
            } catch (\Exception $e) {
                $i++;
                if ($i >= $this->client->getRetries()) {
                    throw new MongoException(
                        sprintf('Unable to find in Mongo after %s retries', $this->client->getRetries()), null, $e
                    );
                }
            }
        }

        return $cursor;
    }

    /**
     * @param array $query
     * @param array $fields
     *
     * @return array|null
     *
     * @throws MongoException
     */
    public function findOne(array $query = array(), array $fields = array())
    {
        $i = 0;
        $retries = $this->client->getRetries();
        $cursor = null;
        while ($i <= $retries) {
            try {
                $cursor = $this->getClient()->getClient()
                    ->selectDB($this->databaseName)
                    ->selectCollection($this->collectionName)
                    ->findOne($query, $fields);

                break;
            } catch (\Exception $e) {
                // we know this is not nice, but we agreed to do it this way only this time
                $i++;
                if ($i >= $this->client->getRetries()) {
                    throw new MongoException(
                        sprintf('Unable to findOne in Mongo after %s retries', $retries), null, $e
                    );
                }
            }
        }

        return $cursor;
    }

    /**
     * @param array $criteria
     * @param mixed $newobj
     * @param array $options
     * @param array $isoDates
     *
     * @throws MongoException
     */
    public function update(array $criteria, $newobj, array $options = array(), array $isoDates = array())
    {
        if ($newobj instanceof \stdClass) {
            $json = json_encode($newobj);
            $dataAsArray = json_decode($json);
        } elseif (is_object($newobj)) {
            try {
                $serializer = SerializerBuilder::create()->build();
                $json = $serializer->serialize($newobj, 'json');
                $dataAsArray = json_decode($json, true);
            } catch (\Exception $e) {
                throw new MongoException('The $item parameter must be an array, stdClass or a JMS serializable entity', null, $e);
            }
        } elseif (is_array($newobj)) {
            $dataAsArray = $newobj;
        } else {
            throw new MongoException('The $item parameter must be an array, stdClass or a JMS serializable entity');
        }

        if ($newobj instanceof LastUpdated) {
            $dataAsArray['lastUpdated'] = new \MongoDate($newobj->getLastUpdated()->getTimestamp());
        }

        foreach ($isoDates as $key => $value) {
            $dataAsArray[$key] = $value;
        }

        $i = 0;
        $retries = $this->client->getRetries();
        while ($i <= $retries) {
            try {
                $status = $this->client->getClient()
                    ->selectDB($this->databaseName)
                    ->selectCollection($this->collectionName)
                    ->update($criteria, $dataAsArray, $options);

                break;
            } catch (\Exception $e) {
                $i++;
                if ($i >= $this->client->getRetries()) {
                    throw new MongoException(
                        sprintf('Unable to update to Mongo after %s retries', $this->client->getRetries()), null, $e
                    );
                }

                $this->client->reconnect();
            }
        }
    }

    /**
     * @param array $criteria
     * @param array $options
     *
     * @throws MongoException
     */
    public function remove(array $criteria = array(), array $options = array())
    {
        $i = 0;
        $retries = $this->client->getRetries();
        while ($i <= $retries) {
            try {
                $status = $this->client->getClient()
                    ->selectDB($this->databaseName)
                    ->selectCollection($this->collectionName)
                    ->remove($criteria, $options);
                break;
            } catch (\Exception $e) {
                $i++;
                if ($i >= $this->client->getRetries()) {
                    throw new MongoException(
                        sprintf('Unable to remove from Mongo after %s retries', $this->client->getRetries()), null, $e
                    );
                }

                $this->client->reconnect();
            }
        }
    }

    /**
     * @param array $criteria
     *
     * @return int
     *
     * @throws MongoException
     */
    public function count(array $criteria = array())
    {
        $total = 0;
        $i = 0;
        $retries = $this->client->getRetries();
        while ($i <= $retries) {
            try {
                $total = $this->client->getClient()
                    ->selectDB($this->databaseName)
                    ->selectCollection($this->collectionName)
                    ->count($criteria);
                break;
            } catch (\Exception $e) {
                $i++;
                if ($i >= $this->client->getRetries()) {
                    throw new MongoException(
                        sprintf('Unable to count documents from Mongo after %s retries', $this->client->getRetries()),
                        null,
                        $e
                    );
                }
            }
        }

        return $total;
    }

    /**
     * Retrieve a list of distinct values for the given key from collection
     *
     * @param string $key
     * @param array  $criteria
     *
     * @return array
     *
     * @throws MongoException
     */
    public function distinct($key, array $criteria = null)
    {
        $distinctList = array();
        $i = 0;
        $retries = $this->client->getRetries();
        while ($i <= $retries) {
            try {
                $distinctList = $this->client->getClient()
                    ->selectDB($this->databaseName)
                    ->selectCollection($this->collectionName)
                    ->distinct($key, $criteria);
                break;
            } catch (\Exception $e) {
                $i++;
                if ($i >= $this->client->getRetries()) {
                    throw new MongoException(
                        sprintf(
                            'Unable to count distinct documents from Mongo after %s retries',
                            $this->client->getRetries()
                        ), null, $e
                    );
                }
            }
        }

        return $distinctList;
    }

    /**
     * @return MongoConnectionWrapper
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param MongoConnectionWrapper $client
     *
     * @return CtiMongoClient
     */
    public function setClient(MongoConnectionWrapper $client)
    {
        $this->client = $client;

        return $this;
    }

    /**
     * @return string
     */
    public function getDatabaseName()
    {
        return $this->databaseName;
    }

    /**
     * @param string $databaseName
     *
     * @return CtiMongoClient
     */
    public function setDatabaseName($databaseName)
    {
        $this->databaseName = $databaseName;

        return $this;
    }

    /**
     * @return string
     */
    public function getCollectionName()
    {
        return $this->collectionName;
    }

    /**
     * @param string $collectionName
     *
     * @return CtiMongoClient
     */
    public function setCollectionName($collectionName)
    {
        $this->collectionName = $collectionName;

        return $this;
    }
}
