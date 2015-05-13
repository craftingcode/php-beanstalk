<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
namespace Beanstalk\Commands;
use \Beanstalk\Command;
use \Beanstalk\Connection;
use \Beanstalk\Stats;
use \Beanstalk\Exception;

/**
 * The stats-job command gives statistical information about the specified job if it exists
 *
 * Returned stats available:
 *   - id:              The job id
 *   - tube:            The name of the tube that contains this job
 *   - state:           One of "ready" or "delayed" or "reserved" or "buried"
 *   - pri:             The priority value set by the put, release, or bury commands.
 *   - age:             The time in seconds since the put command that created this job.
 *   - time-left:       The number of seconds left until the server puts this job
 *                      into the ready queue. This number is only meaningful if the job is
 *                      reserved or delayed. If the job is reserved and this amount of time
 *                      elapses before its state changes, it is considered to have timed out.
 *   - reserves:        The number of times this job has been reserved.
 *   - timeouts:        The number of times this job has timed out during a reservation.
 *   - releases:        The number of times a client has released this job from a reservation.
 *   - buries           The number of times this job has been buried.
 *   - kicks:           The number of times this job has been kicked.
 *
 * @author Joshua Dechant <jdechant@shapeup.com>
 */
class StatsJobCommand extends Command
{

    protected $_id;

    /**
     * Constructor
     *
     * @param integer $id Job id
     */
    public function __construct($id)
    {
        $this->_id = $id;
    }

    /**
     * Get the command to send to the beanstalkd server
     *
     * @return string
     */
    public function getCommand()
    {
        return sprintf('stats-job %d', $this->_id);
    }

    /**
     * Does the command return data?
     *
     * @return boolean
     */
    public function returnsData()
    {
        return true;
    }

    /**
     * Parse the response for success or failure.
     *
     * @param string $response Response line, i.e, first line in response
     * @param string $data Data recieved with reponse, if any, else null
     * @param Connection $conn Connection use to send the command
     * @throws Exception When the job does not exist
     * @throws Exception When any other error occurs
     * @return Stats
     */
    public function parseResponse($response, $data = null, Connection $conn = null)
    {
		if (preg_match('/^OK (\d+)$/', $response, $matches))
        {
            return new Stats($data);
        }

        if ($response === 'NOT_FOUND')
        {
            throw new Exception('The job does not exist.', Exception::NOT_FOUND);
        }

	    throw new Exception('An unknown error has occured.', Exception::UNKNOWN);
    }

}
