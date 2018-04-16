<?php


namespace rollun\datastore\DataStore\Traits;


use rollun\datastore\DataStore\DataStoreException;

trait NoSupportCountTrait
{
    /**
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     * @since 5.1.0
     * @throws DataStoreException
     */
    public function count()
    {
        throw new DataStoreException("Method don't support.");
    }
}