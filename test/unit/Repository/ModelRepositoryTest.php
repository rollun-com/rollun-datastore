<?php

namespace rollun\test\unit\Repository;


use PHPUnit\Framework\TestCase;
use rollun\datastore\DataStore\DataStoreAbstract;
use rollun\datastore\DataStore\Memory;
use rollun\repository\Interfaces\FieldResolverInterface;
use rollun\repository\Interfaces\ModelInterface;
use rollun\repository\Interfaces\ModelRepositoryInterface;
use rollun\repository\BaseFieldResolver;
use rollun\repository\ModelRepository;
use rollun\repository\ModelAbstract;
use Xiag\Rql\Parser\Node\Query\ScalarOperator\EqNode;
use Xiag\Rql\Parser\Query;

class ModelRepositoryTest extends TestCase
{
    protected function getItem()
    {
        return [
            'id' => 1,
            'field' => 'test',
        ];
    }

    protected function createModelInterface($data = [])
    {
        return new class($data) implements ModelInterface {
            public $id;
            public $field;
            public $hello;

            public function __construct($attributes)
            {
                foreach ($attributes as $key => $attribute) {
                    $this->{$key} = $attribute;
                }
            }

            public function toArray()
            {
                return ['id' => $this->id, 'field' => $this->field];
            }
        };
    }

    public function testCreate()
    {
        $dataStore = new Memory();
        $model = $this->createModelInterface();
        $repository = new ModelRepository($dataStore, $model);

        $item = $this->createModelInterface($this->getItem());;
        $repository->save($item);

        $this->assertSame($this->getItem(), $dataStore->read(1));
    }

    public function testUpdate()
    {
        $dataStore = new Memory();
        $model = $this->createModelInterface();
        $repository = new ModelRepository($dataStore, $model);

        $old = $this->createModelInterface($this->getItem());
        $repository->save($old);
        $newData = [
            'id' => 1,
            'field' => 'hello',
        ];
        $new = new class($newData) extends ModelAbstract {};

        $repository->save($new);
        $this->assertSame($newData, $dataStore->read(1));
    }

    public function testRead()
    {
        $dataStore = new Memory();
        $dataStore->create($this->getItem());
        $model = $this->createModelInterface();
        $repository = new ModelRepository($dataStore, $model);

        $result = $repository->findById(1);

        $this->assertIsObject($result);
    }

    public function testQuery()
    {
        $dataStore = new Memory();
        $dataStore->create($this->getItem());
        $model = $this->createModelInterface();
        $repository = new ModelRepository($dataStore, $model);

        $query = new Query();
        $query->setQuery(new EqNode('field', 'test'));
        $results = $repository->find($query);

        $this->assertEquals(1, count($results));
    }

    public function testDelete()
    {
        $dataStore = new Memory();
        $dataStore->create($this->getItem());
        $model = $this->createModelInterface();
        $repository = new ModelRepository($dataStore, $model);

        $repository->removeById(1);

        $this->assertEmpty($dataStore->read(1));
    }

    public function testReadModelAbstract()
    {
        $dataStore = new Memory();
        $dataStore->create($this->getItem());
        $model = new class() extends ModelAbstract {};
        $repository = new ModelRepository($dataStore, $model);

        $result = $repository->findById(1);

        $this->assertIsObject($result);
        $this->assertInstanceOf(ModelAbstract::class, $result);
    }

    public function testRepositoryWithResolver()
    {
        $dataStore = new Memory();
        $dataStore->create($this->getItem());
        $model = new class() extends ModelAbstract {};
        $resolver = new class () implements FieldResolverInterface{
            public function resolve(array $data): array
            {
                return ['id' => $data['id'], 'hello' => $data['field']];
            }
        };
        $repository = new ModelRepository($dataStore, $model, $resolver);

        $result = $repository->findById(1);

        $this->assertEquals($result->hello, $this->getItem()['field']);
    }
}