<?php

namespace CTI\MongoServiceBundle;

use CTI\MongoServiceBundle\Interfaces\LastUpdated;
use JMS\Serializer\SerializerBuilder;
use CTI\MongoServiceBundle\Exception\MongoException;
use Tc\Crud\CrudInterface;

/**
 * Manages data which uses MongoDB as persistent storage.
 *
 * @package CTI\MongoServiceBundle
 * @author  Alexandru Marius Cos <alexandru.cos@cloudtroopers.ro>
 * @author  Georgiana Gligor     <g@lolaent.com>
 */
class MongoManager implements CrudInterface
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
    public function read(array $criteria = array(), $fields = array())
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
     * TODO fix after implementing CrudInterface
     *
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
     * Saves $item into mongo, either by inserting or updating
     *
     * @param array|mixed  $item     must either be an array or a JMS serializable entity
     * @param array        $criteria update criteria
     * @param \MongoDate[] $isoDates extra fields, to be saved in mongo as ISODates
     *
     * @throws MongoException
     */
    public function upsert($item = NULL, array $criteria = array(), array $isoDates = array())
    {
        if (!is_string($item)) {
            try {
                $serializer = SerializerBuilder::create()->build();
                $json = $serializer->serialize($item, 'json');
                $dataAsArray = json_decode($json, true);
            } catch (\Exception $e) {
                throw new MongoException('The $item parameter must be an array or a JMS serializable entity', null, $e);
            }
        } else {
            $dataAsArray = json_decode($item);
        }

        foreach ($isoDates as $key => $value) {
            $dataAsArray[$key] = $value;
        }

        if ($item instanceof LastUpdated) {
            $dataAsArray['lastUpdated'] = new \MongoDate($item->getLastUpdated()->getTimestamp());
        }

        $i = 0;
        $retries = $this->client->getRetries();
        while ($i <= $retries) {
            try {
                $this->client->getClient()
                    ->selectDB($this->getDatabase())
                    ->selectCollection($this->getCollection())
                    ->update($criteria, $dataAsArray, array('upsert' => true));

                break;
            } catch (\Exception $e) {
                $i++;
                if ($i >= $this->client->getRetries()) {
                    throw new MongoException(sprintf('Unable to save to Mongo after %s retries', $this->client->getRetries()), null, $e);
                }

                $this->client->reconnect();
            }
        }
    }

    /**
     * Insert new or update existing item, if one exists, only using specific fields
     *
     * @param object $item           Model to insert/update
     * @param array  $criteria       Used for search
     * @param array  $isoDates       Allows adding extra fields (key => value), stored as ISO dates in mongo
     * @param array  $fields         Allows selection of which fields to use from $item HINT: use JMS SerializedName
     * @param array  $fieldsOnInsert Allows selection of which fields to be updating only when inserting, and not when updating HINT: use JMS SerializedName
     *
     * @throws MongoException
     */
    public function upsertPartial
    (
        $item = NULL,
        array $criteria = array(),
        array $isoDates = array(),
        array $fields = array(),
        array $fieldsOnInsert = array()
    )
    {
        {
            if (!is_string($item)) {
                try {
                    $serializer = SerializerBuilder::create()->build();
                    $json = $serializer->serialize($item, 'json');
                    $dataAsArray = json_decode($json, true);
                } catch (\Exception $e) {
                    throw new MongoException('The $item parameter must be an array or a JMS serializable entity', null, $e);
                }
            } else {
                $dataAsArray = json_decode($item);
            }

            if (!empty($fields)) {
                $upsertItem = array();
                $upsertItem['$set'] = array();
                foreach ($fields as $field) {
                    if (!isset($dataAsArray[$field])) {
                        continue;
                    }

                    $upsertItem['$set'][$field] = $dataAsArray[$field];
                }
            }

            if (!empty($fieldsOnInsert)) {
                $upsertItem['$setOnInsert'] = array();
                foreach ($fieldsOnInsert as $fieldOnInsert) {
                    if (!isset($dataAsArray[$fieldOnInsert])) {
                        continue;
                    }

                    $upsertItem['$setOnInsert'][$fieldOnInsert] = $dataAsArray[$fieldOnInsert];
                }
            }

            foreach ($isoDates as $key => $value) {
                $upsertItem['$set'][$key] = $value;
            }

            if ($item instanceof LastUpdated) {
                $upsertItem['$set']['lastUpdated'] = new \MongoDate($item->getLastUpdated()->getTimestamp());
            }

            $i = 0;
            $retries = $this->client->getRetries();
            while ($i <= $retries) {
                try {
                    $this->client->getClient()
                        ->selectDB($this->getDatabase())
                        ->selectCollection($this->getCollection())
                        ->update($criteria, $upsertItem, array('upsert' => true));

                    break;
                } catch (\Exception $e) {
                    $i++;
                    if ($i >= $this->client->getRetries()) {
                        throw new MongoException(sprintf('Unable to save to Mongo after %s retries', $this->client->getRetries()), null, $e);
                    }

                    $this->client->reconnect();
                }
            }
        }
    }

    /**
     * TODO add implementation
     *
     * @param object $item
     * @param array  $criteria
     */
    public function create($item = NULL, array $criteria = array()) {}

    /**
     * TODO add implementation
     *
     * @param array $item
     * @param array $criteria
     *
     * @throws MongoException
     */
    public function update($item, array $criteria = array())
    {
        if (!is_array($item)) {
            throw new MongoException(sprintf('$item parameter must be an array'));
        }

        $i = 0;
        $retries = $this->client->getRetries();
        while ($i <= $retries) {
            try {
                $this->client->getClient()
                    ->selectDB($this->getDatabase())
                    ->selectCollection($this->getCollection())
                    ->update($criteria, $item);

                break;
            } catch (\Exception $e) {
                $i++;
                if ($i >= $this->client->getRetries()) {
                    throw new MongoException(sprintf('Unable to save to Mongo after %s retries', $this->client->getRetries()), null, $e);
                }

                $this->client->reconnect();
            }
        }
    }

    /**
     * TODO add implementation
     *
     * @param       $item
     * @param array $criteria
     *
     * @throws MongoException
     */
    public function updateMultiple($item, array $criteria = array())
    {
        if (!is_array($item)) {
            throw new MongoException(sprintf('$item parameter must be an array'));
        }

        $i = 0;
        $retries = $this->client->getRetries();
        while ($i <= $retries) {
            try {
                $this->client->getClient()
                    ->selectDB($this->getDatabase())
                    ->selectCollection($this->getCollection())
                    ->update($criteria, $item, array('multiple' => true));

                break;
            } catch (\Exception $e) {
                $i++;
                if ($i >= $this->client->getRetries()) {
                    throw new MongoException(sprintf('Unable to save to Mongo after %s retries', $this->client->getRetries()), null, $e);
                }

                $this->client->reconnect();
            }
        }
    }

    /**
     * @param object $item
     *
     * @throws MongoException
     */
    public function delete($item)
    {
        // grab the id to be removed
        $id = $item->getId();

        $i = 0;
        $retries = $this->client->getRetries();
        while ($i <= $retries) {
            try {
                $this->client->getClient()
                    ->selectDB($this->getDatabase())
                    ->selectCollection($this->getCollection())
                    ->remove(array('_id' => $id), array('justOne' => true));
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
     * @param array $options
     *
     * @throws MongoException
     */
    public function deleteMultiple(array $criteria = array(), array $options = array())
    {
        $i = 0;
        $retries = $this->client->getRetries();
        while ($i <= $retries) {
            try {
                $status = $this->client->getClient()
                    ->selectDB($this->getDatabase())
                    ->selectCollection($this->getCollection())
                    ->remove($criteria, $options);
                break;
            } catch (\Exception $e) {
                $i++;
                if ($i >= $this->client->getRetries()) {
                    throw new MongoException(sprintf('Unable to remove from Mongo after %s retries', $this->client->getRetries()), null, $e);
                }

                $this->client->reconnect();
            }
        }
    }

    /**
     * Count number of documents in collection
     *
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
                    ->selectDB($this->getDatabase())
                    ->selectCollection($this->getCollection())
                    ->count($criteria);
                break;
            } catch (\Exception $e) {
                $i++;
                if ($i >= $this->client->getRetries()) {
                    throw new MongoException(sprintf('Unable to count documents from Mongo after %s retries', $this->client->getRetries()), null, $e);
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
    public function distinct($key, array $criteria = array())
    {
        $distinctList = array();
        $i = 0;
        $retries = $this->client->getRetries();
        while ($i <= $retries) {
            try {
                $distinctList = $this->client->getClient()
                    ->selectDB($this->getDatabase())
                    ->selectCollection($this->getCollection())
                    ->distinct($key, $criteria);
                break;
            } catch (\Exception $e) {
                $i++;
                if ($i >= $this->client->getRetries()) {
                    throw new MongoException(sprintf('Unable to count documents from Mongo after %s retries', $this->client->getRetries()), null, $e);
                }
            }
        }

        return $distinctList;
    }
    
}
