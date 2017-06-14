<?php

namespace Huasituo\Es\Test;

use Huasituo\Es\EsQuery;
use PHPUnit\Framework\TestCase;

class EsQueryTest extends TestCase
{
    /**
     * Test getInstance method.
     *
     * @return void
     * @author Seven Du <shiweidu@outlook.com>
     */
    public function testStaticGetInstance()
    {
        $abstract = 'testEsQuery';
        $this->assertEquals($abstract, EsQuery::getInstance($abstract));
    }

    /**
     * Test static run method.
     *
     * @return void
     * @author Seven Du <shiweidu@outlook.com>
     */
    public function testStaticRun()
    {
        $arguments = [];
        $this->assertEquals(
            count($arguments),
            count(EsQuery::run(new EsQueryTestClass, $arguments))
        );
    }
}

class EsQueryTestClass {
    public function run(array $arguments)
    {
        return $arguments;
    }
}
