<?php

namespace PhlyRestfully;

use Zend\EventManager\EventManagerAwareInterface;

interface ResourceInterface extends EventManagerAwareInterface
{
    /**
     * Create a record in the resource
     * 
     * @param  array|object $data 
     * @return array|object
     */
    public function create($data);

    /**
     * Update (replace) an existing record
     * 
     * @param  string|int $id 
     * @param  array|object $data 
     * @return array|object
     */
    public function update($id, $data);

    /**
     * Partial update of an existing record
     * 
     * @param  string|int $id 
     * @param  array|object $data 
     * @return array|object
     */
    public function patch($id, $data);

    /**
     * Delete an existing record
     * 
     * @param  string|int $id 
     * @return bool
     */
    public function delete($id);

    /**
     * Fetch an existing record
     * 
     * @param  string|int $id 
     * @return false|array|object
     */
    public function fetch($id);

    /**
     * Fetch a collection of records
     * 
     * @return \Zend\Paginator\Paginator
     */
    public function fetchAll();
}
