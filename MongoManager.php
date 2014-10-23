<?php

namespace CTI\MongoServiceBundle;

use JMS\Serializer\SerializerBuilder;
use CTI\MongoService\Exception\MongoException;

/**
 * Class MongoManager
 *
 * @package CTI\MongoServiceBundle\Services
 * @author Alexandru Marius Cos  <alexandru.cos@cloudtroopers.ro>
 */
class MongoManager
{
    /** @var  MongoService */
    protected $client;

    /** @var  string */
    protected $database;

    /** @var  string */
    protected $collection;

    /**
     * @param MongoService $mongoService
     * @param $mongoDb
     * @param $mongoColl
     */
    public function __construct(MongoService $mongoService, $mongoDb, $mongoColl)
    {
        $this->client = $mongoService;
        $this->database = $mongoDb;
        $this->collection = $mongoColl;
    }

    /**
     * @param string $mongoColl
     *
     * @return $this
     */
    public function setCollection($mongoColl)
    {
        $this->collection = $mongoColl;

        return $this;
    }

    /**
     * @return string
     */
    public function getCollection()
    {
        return $this->collection;
    }

    /**
     * @param string $mongoDb
     *
     * @return $this
     */
    public function setDatabase($mongoDb)
    {
        $this->database = $mongoDb;

        return $this;
    }

    /**
     * @return string
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * @param \CTI\MongoServiceBundle\MongoService $mongoService
     *
     * @return $this
     */
    public function setClient($mongoService)
    {
        $this->client = $mongoService;

        return $this;
    }

    /**
     * @return \CTI\MongoServiceBundle\MongoService
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param array $criteria
     * @param array $fields
     *
     * @return \MongoCursor
     *
     * @throws MongoException
     */
    public function find($criteria = array(), $fields = array())
    {
        $i = 0;
        $retries = $this->client->getRetries();
        while ($i <= $retries) {
            try {
            $cursor = $this->getClient()->getClient()
                ->selectDB($this->database)
                ->selectCollection($this->collection)
                ->find($criteria, $fields);

            break;
            } catch (\Exception $e) {
                $i++;
                if ($i >= $this->client->getRetries()) {
                    throw new MongoException(sprintf('Unable to search in Mongo after %s retries', $this->client->getRetries()), null, $e);
                }
            }
        }

        return $cursor;
    }

    /**
     * @param array $criteria
     *
     * @return mixed
     *
     * @throws MongoException
     */
    public function findOne($criteria)
    {
        $i = 0;
        $retries = $this->client->getRetries();
        while ($i <= $retries) {
            try {
                $cursor = $this->getClient()->getClient()
                    ->selectDB($this->database)
                    ->selectCollection($this->collection)
                    ->findOne($criteria);

                break;
            } catch (\Exception $e) {
                // we know this is not nice, but we agreed to do it this way only this time
                $i++;
                if ($i >= $this->client->getRetries()) {
                    throw new MongoException(sprintf('Unable to search in Mongo after %s retries', $retries), null, $e);
                }
            }
        }

        return $cursor;
    }

    /**
     * Saves $object into mongo, either by inserting or updating
     *
     * @param array       $criteria
     * @param array|mixed $object   must either be an array or a JMS serializable entity
     *
     * @throws MongoException
     */
    public function save($criteria, $object)
    {
        if (!is_array($object)) {
            try {
                $serializer = SerializerBuilder::create()->build();
                $json = $serializer->serialize($object, 'json');
                $object = json_decode($json, true);
            } catch (\Exception $e) {
                throw new MongoException('The $object parameter must be an array or a JMS serializable entity', null, $e);
            }
        }

        $i = 0;
        $retries = $this->client->getRetries();
        while ($i <= $retries) {
            try {
                $this->client->getClient()
                    ->selectDB($this->getDatabase())
                    ->selectCollection($this->getCollection())
                    ->update($criteria, $object, array('upsert' => true));

                break;
            } catch (\Exception $e) {
                $i++;
                if ($i >= $this->client->getRetries()) {
                    throw new MongoException(sprintf('Unable to save to Mongo after %s retries', $this->client->getRetries()), null, $e);
                }
            }
        }
    }

}
