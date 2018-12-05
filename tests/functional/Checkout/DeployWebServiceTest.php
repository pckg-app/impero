<?php

namespace Checkout;

class DeployWebServiceTest extends \Codeception\Test\Unit
{
    /**
     * @var \FunctionalTester
     */
    protected $tester;

    protected function _before()
    {
    }

    protected function _after()
    {
    }

    // tests
    public function testSomeFeature()
    {
        /**
         * Point is, we receive content of .pckg/pckg.yaml file.
         * We read and execute file and expect specific commands to be executed.
         */

        /**
         * Make sure that htdocs, logs and ssl directories are created.
         * Make sure that https certificate is generated.
         */
    }
}