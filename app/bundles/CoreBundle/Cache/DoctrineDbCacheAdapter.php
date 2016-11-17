<?php

namespace Mautic\CoreBundle\Cache;

use Doctrine\DBAL\Driver\Connection;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\AdapterInterface;

class DoctrineDbCacheAdapter extends AbstractAdapter implements AdapterInterface
{
    protected $connection;

    public function __construct(Connection $connection, $namespace, $defaultLifetime)
    {
        $this->connection = $connection;

        parent::__construct($namespace, $defaultLifetime);
    }

    protected function doFetch(array $ids)
    {
        // TODO: Implement doFetch() method.
    }

    protected function doHave($id)
    {
        // TODO: Implement doHave() method.
    }

    protected function doClear($namespace)
    {
        // TODO: Implement doClear() method.
    }

    protected function doDelete(array $ids)
    {
        // TODO: Implement doDelete() method.
    }

    protected function doSave(array $values, $lifetime)
    {
        // TODO: Implement doSave() method.
    }
}
