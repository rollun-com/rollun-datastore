<?php

namespace rollun\test\files\FileObject;

use rollun\files\FileObject;
use rollun\test\files\FileObject\FileObjectAbstractTest;

class LockTest extends FileObjectAbstractTest
{

    /**
     *
     * @var FileObject
     */
    protected $fileObject1;

    /**
     *
     * @var FileObject
     */
    protected $fileObject2;

    public function setUp()
    {
        parent::setUp();
        $fileObject = $this->getFileObject();
        $fileObject->fwriteWithCheck("first string \n second string");
        $filename = $fileObject->getRealPath();
        unset($fileObject);
        $this->fileObject1 = new FileObject($filename);
        $this->fileObject2 = new FileObject($filename);
    }

    public function tearDown()
    {
        $this->fileObject1->unlock();
        $this->fileObject2->unlock();
        unset($this->fileObject1);
        unset($this->fileObject2);
    }

    public function testObjects()
    {
        $this->assertEquals($this->fileObject1, $this->fileObject2);
    }

    public function testLockLock()
    {
        //$lockMode LOCK_SH or LOCK_EX
        $this->fileObject1->lock(LOCK_SH);
        $this->assertTrue($this->fileObject1->lock(LOCK_SH));
        $this->fileObject1->unlock();
        $this->fileObject1->lock(LOCK_SH);
        $this->assertTrue($this->fileObject1->lock(LOCK_EX));
        $this->fileObject1->unlock();
        $this->fileObject1->lock(LOCK_EX);
        $this->assertTrue($this->fileObject1->lock(LOCK_SH));
        $this->fileObject1->unlock();
        $this->fileObject1->lock(LOCK_EX);
        $this->assertTrue($this->fileObject1->lock(LOCK_EX));
        $this->fileObject1->unlock();
    }

    public function testLock12()
    {
        //$lockMode LOCK_SH or LOCK_EX
        $this->fileObject1->lock(LOCK_SH);
        $this->assertTrue($this->fileObject2->lock(LOCK_SH));
        $this->expectException(\RuntimeException::class);
        $this->fileObject2->lock(LOCK_EX);
    }

    public function testLockRead()
    {
        //$lockMode LOCK_SH or LOCK_EX
        $this->assertTrue($this->fileObject1->flock(LOCK_EX));
        $this->fileObject2->rewind();
        $this->fileObject2->next();
        $actual = $this->fileObject2->current();
        $this->assertFalse($actual);
        $actual = $this->fileObject2->key();
        $this->fileObject2->next();
        $actual = $this->fileObject2->current();
        $this->assertFalse($actual);
        $this->fileObject2->rewind();
        $this->fileObject2->current();
        $this->expectException(\RuntimeException::class);
        $this->fileObject2->fgets();
    }

}
