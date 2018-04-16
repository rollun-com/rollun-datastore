<?php


namespace rollun\datastore\DataStore\Traits;


use rollun\datastore\DataStore\DataStoreException;

trait NoSupportGetIdentifier
{
    /**
     * Return primary key identifier
     *
     * Return "id" by default
     *
     * @see DEF_ID
     * @return string "id" by default
     * @throws DataStoreException
     */
    public function getIdentifier()
    {
        throw new DataStoreException("Method don't support.");
    }
}