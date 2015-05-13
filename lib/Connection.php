<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
namespace Beanstalk;

use \Beanstalk\Connections\Stream;
use \Beanstalk\Exception;
use \Beanstalk\Commands\PutCommand;
use \Beanstalk\Commands\UseCommand;
use \Beanstalk\Commands\WatchCommand;
use \Beanstalk\Commands\IgnoreCommand;
use \Beanstalk\Commands\ReserveCommand;
use \Beanstalk\Commands\DeleteCommand;
use \Beanstalk\Commands\TouchCommand;
use \Beanstalk\Commands\PauseTubeCommand;
use \Beanstalk\Commands\ListTubesCommand;
use \Beanstalk\Commands\StatsTubeCommand;
use \Beanstalk\Commands\StatsJobCommand;
use \Beanstalk\Commands\ReleaseCommand;
use \Beanstalk\Commands\StatsCommand;
use \Beanstalk\Commands\PeekCommand;
use \Beanstalk\Commands\KickCommand;
use \Beanstalk\Commands\BuryCommand;

/**
 * Beanstalkd connection
 *
 * @author Joshua Dechant <jdechant@shapeup.com>
 */
class Connection
{

    protected $_address;
    protected $_stream;
    protected $_timeout;

    /**
     * Constructor; establishes connection stream
     *
     * @param string $address Beanstalkd server address in the format "host:port"
     * @param BeanstalkConnectionStream $stream Stream to use for connection
     * @param float $timeout Connection timeout in milliseconds
     * @throws Exception When a connection cannot be established
     */
    public function __construct($address, Stream $stream, $timeout = 500)
    {
        $this->_address = $address;
        $this->_stream = $stream;
        $this->setTimeout($timeout);
        $this->connect();
    }

    /**
     * Connect to the beanstalkd server
     *
     * @throws Exception When a connection cannot be established
     * @return boolean
     */
    public function connect()
    {
        list($host, $port) = explode(':', $this->getServer());
        if ($this->_stream->open($host, $port, $this->getTimeout()) === false)
        {
            throw new Exception(sprintf('Cannot connect to server %s', $this->getServer()), Exception::SERVER_OFFLINE);
        }

        return true;
    }

    /**
     * Get the connect's stream
     *
     * @return BeanstalkConnectionStream
     */
    public function getStream()
    {
        return $this->_stream;
    }

    /**
     * Get the Beanstalkd server address
     *
     * @return string Beanstalkd server address in the format "host:port"
     */
    public function getServer()
    {
        return $this->_address;
    }

    /**
     * Get the connection timeout
     *
     * @return float Connection timeout
     */
    public function getTimeout()
    {
        return $this->_timeout;
    }

    /**
     * Set the connection timeout
     *
     * @param float $timeout Connection timeout in milliseconds
     */
    public function setTimeout($timeout)
    {
        $this->_timeout = (float)$timeout;
    }

    /**
     * Has the connection timed out?
     *
     * @return boolean
     */
    public function isTimedOut()
    {
        return $this->_stream->isTimedOut();
    }

    /**
     * Close the connection
     */
    public function close()
    {
        $this->_stream->close();
    }

    /**
     * The "put" command is for any process that wants to insert a job into the queue
     *
     * @param mixed $message Description
     * @param integer $priority Job priority.
     *        Jobs with smaller priority values will be scheduled before jobs with larger priorities.
     *        The most urgent priority is 0; the least urgent priority is 4,294,967,295.
     * @param integer $delay Number of seconds to wait before putting the job in the ready queue.
     *        The job will be in the "delayed" state during this time.
     * @param integer $ttr Time to run. The number of seconds to allow a worker to run this job.
     *        This time is counted from the moment a worker reserves this job.
     *        If the worker does not delete, release, or bury the job within
     *        <ttr> seconds, the job will time out and the server will release the job.
     *        The minimum ttr is 1. If the client sends 0, the server will silently
     *        increase the ttr to 1.
     */
    public function put($message, $priority = 65536, $delay = 0, $ttr = 120)
    {
        return $this->_dispatch(new PutCommand($message, $priority, $delay, $ttr));
    }

    /**
     * Use command
     *
     * The "use" command is for producers. Subsequent put commands will put jobs into
     * the tube specified by this command. If no use command has been issued, jobs
     * will be put into the tube named "default".
     *
     * @param string $tube The tube to use. If the tube does not exist, it will be created.
     */
    public function useTube($tube)
    {
        return $this->_dispatch(new UseCommand($tube));
    }

    /**
     * Watch command
     *
     * The "watch" command adds the named tube to the watch list for the current
     * connection. A reserve command will take a job from any of the tubes in the
     * watch list. For each new connection, the watch list initially consists of one
     * tube, named "default".
     *
     * @param string $tube Tube to add to the watch list. If the tube doesn't exist, it will be created
     */
    public function watchTube($tube)
    {
        return $this->_dispatch(new WatchCommand($tube));
    }

    /**
     * Ignore command
     *
     * The "ignore" command is for consumers. It removes the named tube from the
     * watch list for the current connection.
     *
     * @param string $tube Tube to remove from the watch list
     */
    public function ignoreTube($tube)
    {
        return $this->_dispatch(new IgnoreCommand($tube));
    }

    /**
     * Reserve command
     *
     * This will return a newly-reserved job. If no job is available to be reserved,
     * beanstalkd will wait to send a response until one becomes available. Once a
     * job is reserved for the client, the client has limited time to run (TTR) the
     * job before the job times out. When the job times out, the server will put the
     * job back into the ready queue. Both the TTR and the actual time left can be
     * found in response to the stats-job command.
     *
     * A timeout value of 0 will cause the server to immediately return either a
     * response or TIMED_OUT.  A positive value of timeout will limit the amount of
     * time the client will block on the reserve request until a job becomes
     * available.
     *
     * @param integer $timeout Wait timeout in seconds
     */
    public function reserve($timeout = null)
    {
        return $this->_dispatch(new ReserveCommand($timeout));
    }

    /**
     * Delete command
     *
     * The delete command removes a job from the server entirely. It is normally used
     * by the client when the job has successfully run to completion. A client can
     * delete jobs that it has reserved, ready jobs, and jobs that are buried.
     *
     * @param integer $id The job id to delete
     * @throws Exception
     * @return boolean
     */
    public function delete($id)
    {
        return $this->_dispatch(new DeleteCommand($id));
    }

    /**
     * Touch command
     *
     * The "touch" command allows a worker to request more time to work on a job.
     * This is useful for jobs that potentially take a long time, but you still want
     * the benefits of a TTR pulling a job away from an unresponsive worker.  A worker
     * may periodically tell the server that it's still alive and processing a job
     * (e.g. it may do this on DEADLINE_SOON).
     *
     * @param integer $id The job id to touch
     * @throws Exception
     * @return boolean
     */
    public function touch($id)
    {
        return $this->_dispatch(new TouchCommand($id));
    }

    /**
     * Release command
     *
     * The release command puts a reserved job back into the ready queue (and marks
     * its state as "ready") to be run by any client. It is normally used when the job
     * fails because of a transitory error.
     *
     * @param integer $id The job id to release
     * @param integer $priority A new priority to assign to the job
     * @param integer $delay Number of seconds to wait before putting the job in the ready queue. The job will be in the "delayed" state during this time
     */
    public function release($id, $priority, $delay)
    {
        return $this->_dispatch(new ReleaseCommand($id, $priority, $delay));
    }

    /**
     * Bury command
     *
     * The bury command puts a job into the "buried" state. Buried jobs are put into a
     * FIFO linked list and will not be touched by the server again until a client
     * kicks them with the "kick" command.
     *
     * @param integer $id The job id to bury
     * @param integer $priority A new priority to assign to the job     
     */
    public function bury($id, $priority)
    {
        return $this->_dispatch(new BuryCommand($id, $priority));
    }

    /**
     * Kick command
     *
     * The kick command applies only to the currently used tube. It moves jobs into
     * the ready queue. If there are any buried jobs, it will only kick buried jobs.
     * Otherwise it will kick delayed jobs
     *
     * @param integer $bound Upper bound on the number of jobs to kick. The server will kick no more than $bound jobs.
     * @return integer The number of jobs actually kicked
     */
    public function kick($bound)
    {
        return $this->_dispatch(new KickCommand($bound));
    }

    /**
     * Return job $id
     *
     * @param integer $id Id of job to return
     * @throws Exception When job cannot be found
     * @return BeanstalkJob
     */
    public function peek($id)
    {
        return $this->_dispatch(new PeekCommand($id));
    }

    /**
     * Return the next ready job
     *
     * @throws Exception When no jobs in ready state
     * @return BeanstalkJob
     */
    public function peekReady()
    {
        return $this->_dispatch(new PeekCommand('ready'));
    }

    /**
     * Return the delayed job with the shortest delay left
     *
     * @throws Exception When no jobs in delayed state
     * @return BeanstalkJob
     */
    public function peekDelayed()
    {
        return $this->_dispatch(new PeekCommand('delayed'));
    }

    /**
     * Return the next job in the list of buried jobs
     *
     * @throws Exception When no jobs in buried state
     * @return BeanstalkJob
     */
    public function peekBuried()
    {
        return $this->_dispatch(new PeekCommand('buried'));
    }

    /**
     * The stats command gives statistical information about the system as a whole.
     */
    public function stats()
    {
        return $this->_dispatch(new StatsCommand());
    }

    /**
     * The stats-job command gives statistical information about the specified job if it exists.
     *
     * @param integer $id The job id to get stats on
     * @throws Exception When the job does not exist
     * @return BeanstalkStats
     */
    public function statsJob($id)
    {
        return $this->_dispatch(new StatsJobCommand($id));
    }

    /**
     * The stats-tube command gives statistical information about the specified tube if it exists.
     *
     * @param string $tube is a name at most 200 bytes. Stats will be returned for this tube.
     * @throws Exception When the tube does not exist
     * @return BeanstalkStats
     */
    public function statsTube($tube)
    {
        return $this->_dispatch(new StatsTubeCommand($tube));
    }

    /**
     * The list-tubes command returns a list of all existing tubes
     */
    public function listTubes()
    {
        return $this->_dispatch(new ListTubesCommand());
    }

    /**
     * The pause-tube command can delay any new job being reserved for a given time
     *
     * @param string $tube The tube to pause
     * @param integer $delay Number of seconds to wait before reserving any more jobs from the queue
     * @throws Exception
     * @return boolean
     */
    public function pauseTube($tube, $delay)
    {
        $this->_dispatch(new PauseTubeCommand($tube, $delay));
        return true;
    }

    /**
     * Generic validation for all responses from beanstalkd
     *
     * @param string $response
     * @return boolean true when response is valid
     * @throws Exception When response is invalid
     */
    public function validateResponse($response)
    {
        if ($response === false)
        {
            throw new Exception(
                'Error reading data from the server.',
                Exception::SERVER_READ
            );
        }

        if ($response === 'BAD_FORMAT')
        {
            throw new Exception(
                'The client sent a command line that was not well-formed. ' .
                'This can happen if the line does not end with \r\n, if non-numeric ' .
                'characters occur where an integer is expected, if the wrong number of ' .
                'arguments are present, or if the command line is mal-formed in any other way.',
                Exception::BAD_FORMAT
            );
        }

        if ($response === 'OUT_OF_MEMORY')
        {
            throw new Exception(
                'The server cannot allocate enough memory for the job. The client should try again later.',
                Exception::OUT_OF_MEMORY
            );
        }

        if ($response === 'UNKNOWN_COMMAND')
        {
            throw new Exception(
                'The client sent a command that the server does not know.', Exception::UNKNOWN_COMMAND
            );
        }

        if ($response === 'INTERNAL_ERROR')
        {
            throw new Exception(
                'This indicates a bug in the server. It should never happen. ' .
                'If it does happen, please report it at http://groups.google.com/group/beanstalk-talk.',
                Exception::INTERNAL_ERROR
            );
        }

        return true;
    }

    /**
     * Send a command to beanstalkd and return the result
     *
     * @param Command $command
     * @return mixed Result of Command::parseResponse()
     * @throws Exception
     */
    protected function _dispatch(Command $command)
    {
        // re-connect if we have timed out
        if ($this->isTimedOut() === true)
        {
            $this->close();
            $this->connect();
        }

        // construct message
        $message = $command->getCommand() . "\r\n";

        if (($data = $command->getData()) !== false)
        {
            $message .= $data . "\r\n";
        }

        // write to stream
        if ($this->_stream->write($message) === false)
        {
            throw new Exception(
                'Error writing data to the server.',
                Exception::SERVER_WRITE
            );
        }

        // read response from stream
        $response = $this->_stream->readLine();

        // validate against common errors
        $this->validateResponse($response);

        $data = null;

        if ($command->returnsData())
        {
            $bytes = preg_replace('/^.*\b(\d+)$/', '$1', $response);
            $data = $this->_stream->read($bytes);
        }

        return $command->parseResponse($response, $data, $this);
    }

}
