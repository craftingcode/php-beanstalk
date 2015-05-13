<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace UnitTests\BeanstalkTests\CommandTests\WatchTest;

use \PHPUnit_Framework_TestCase;
use \Beanstalk\Commands\WatchCommand as BeanstalkCommandWatch;
use \Beanstalk\Exception as BeanstalkException;

class TestCases extends PHPUnit_Framework_TestCase
{

    public function testGetCommand()
    {
        $command = new BeanstalkCommandWatch('tube-name');
        $this->assertEquals('watch tube-name', $command->getCommand());

        $command = new BeanstalkCommandWatch('tube3r');
        $this->assertEquals('watch tube3r', $command->getCommand());
    }

    public function testCannotSetTubeNameLongerThan200Bytes()
    {
        $this->setExpectedException('\\Beanstalk\\Exception', '', BeanstalkException::TUBE_NAME_TOO_LONG);

        $tube = 'a-long-name-for-a-tube-that-is-completely-ridiculous-and-probably-would-not-happen-in-reality-but-we-have-to-test-for-it-anyways-just-in-case-someone-gets-the-crazy-idea-to-create-a-tube-name-like-this';
        $command = new BeanstalkCommandWatch($tube);
    }

    public function testHasNoData()
    {
        $command = new BeanstalkCommandWatch('tube');
        $this->assertFalse($command->getData());
    }

    public function testReturnsNoData()
    {
        $command = new BeanstalkCommandWatch('tube');
        $this->assertFalse($command->returnsData());
    }

    public function testParseResponseOnSuccess()
    {
        $command = new BeanstalkCommandWatch('tube');
        $this->assertEquals(2, $command->parseResponse('WATCHING 2'));

        $command = new BeanstalkCommandWatch('tube2-name');
        $this->assertEquals(3, $command->parseResponse('WATCHING 3'));
    }

    public function testParseResponseOnSuccessReturnInteger()
    {
        $command = new BeanstalkCommandWatch('tube');
        $this->assertInternalType('integer', $command->parseResponse('WATCHING 3'));
    }

    public function testParseResponseOnOtherErrors()
    {
        $this->setExpectedException('\\Beanstalk\\Exception', '', BeanstalkException::UNKNOWN);

        $command = new BeanstalkCommandWatch('tube');
        $command->parseResponse('This is wack');
    }

}
