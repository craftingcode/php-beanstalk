<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace UnitTests\BeanstalkTests\CommandTests\BuryTest;

use \PHPUnit_Framework_TestCase;
use \Beanstalk\Commands\BuryCommand as BeanstalkCommandBury;
use \Beanstalk\Exception as BeanstalkException;


class TestCases extends PHPUnit_Framework_TestCase
{

    public function testGetCommand()
    {
        $command = new BeanstalkCommandBury(150, 1024);
        $this->assertEquals('bury 150 1024', $command->getCommand());

        $command = new BeanstalkCommandBury('365', '5');
        $this->assertEquals('bury 365 5', $command->getCommand());
    }

    public function testHasNoData()
    {
        $command = new BeanstalkCommandBury(1, 2);
        $this->assertFalse($command->getData());
    }

    public function testReturnsNoData()
    {
        $command = new BeanstalkCommandBury(1, 2);
        $this->assertFalse($command->returnsData());
    }

    public function testParseResponseOnSuccess()
    {
        $command = new BeanstalkCommandBury(1, 2);
        $this->assertTrue($command->parseResponse('BURIED'));
    }

    public function testParseResponseOnNotFound()
    {
        $this->setExpectedException('\\Beanstalk\\Exception', '', BeanstalkException::NOT_FOUND);

        $command = new BeanstalkCommandBury(1, 2);
        $command->parseResponse('NOT_FOUND');
    }

    public function testParseResponseOnOtherErrors()
    {
        $this->setExpectedException('\\Beanstalk\\Exception', '', BeanstalkException::UNKNOWN);

        $command = new BeanstalkCommandBury(1, 2);
        $command->parseResponse('This is wack');
    }

}
