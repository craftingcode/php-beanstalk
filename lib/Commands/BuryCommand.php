<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
namespace Beanstalk\Commands;
use \Beanstalk\Command;
use \Beanstalk\Connection;
use \Beanstalk\Exception;

/**
 * Bury command
 *
 * The bury command puts a job into the "buried" state. Buried jobs are put into a
 * FIFO linked list and will not be touched by the server again until a client
 * kicks them with the "kick" command.
 *
 * @author Joshua Dechant <jdechant@shapeup.com>
 */
class BuryCommand extends Command
{

    protected $_id;
    protected $_priority;

    /**
     * Constructor
     *
     * @param integer $id The job id to bury
     * @param integer $priority A new priority to assign to the job     
     */
    public function __construct($id, $priority)
    {
        $this->_id = $id;
        $this->_priority = $priority;
    }

    /**
     * Get the bury command to send to the beanstalkd server
     *
     * @return string
     */
    public function getCommand()
    {
        return sprintf('bury %d %d', $this->_id, $this->_priority);
    }

    /**
     * Parse the response for success or failure.
     *
     * @param string $response Response line, i.e, first line in response
     * @param string $data Data recieved with reponse, if any, else null
     * @param Connection $conn Connection use to send the command
     * @throws Exception When the job cannot be found
     * @throws Exception When any other error occurs
     * @return boolean True if command was successful
     */
    public function parseResponse($response, $data = null, Connection $conn = null)
    {
        if ($response === 'BURIED')
        {
            return true;
        }

        if ($response === 'NOT_FOUND')
        {
		    throw new Exception('The job does not exist or is not reserved by the client.', Exception::NOT_FOUND);
        }

	    throw new Exception('An unknown error has occured.', Exception::UNKNOWN);
    }

}
