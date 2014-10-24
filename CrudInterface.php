<?php

namespace CTI\MongoServiceBundle;

/**
 * Interface specifying CRUD operations
 *
 * @package CTI\MongoServiceBundle
 * @author  Georgiana Gligor <g@lolaent.com>
 */
interface CrudInterface
{

    /**
     * Creates new item in persistent storage.
     *
     * @param object $item
     * @param array  $criteria
     */
    public function create($item, array $criteria = array());

    /**
     * Extracts several items from persistent storage, according to given $criteria.
     *
     * @param array $criteria
     * @param array $fields
     *
     * @return mixed
     */
    public function read(array $criteria = array(), $fields = array());

    /**
     * Updates existing item in persistent storage.
     *
     * @param object $item
     * @param array  $criteria
     */
    public function update($item, array $criteria = array());

    /**
     * Removes $item from persistent storage.
     * It is enough if the identifier field of the object is populated
     *
     * @param object $item
     */
    public function delete($item);

    /**
     * Creates new item if it does not exist, or updates it in persistent storage.
     *
     * @param object $item
     * @param array  $criteria
     */
    public function upsert($item, array $criteria = array());

}
