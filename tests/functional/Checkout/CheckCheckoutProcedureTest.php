<?php

namespace Checkout;

class CheckCheckoutProcedureTest extends \Codeception\Test\Unit
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
         * Web service
         * Checkout procedure
         * Storage service
         * Database service
         * Config service
         * Prepare procedure
         * Cron service
         */
        $this->assertEquals('a', 'a', 'Doesnt equal');
    }
}