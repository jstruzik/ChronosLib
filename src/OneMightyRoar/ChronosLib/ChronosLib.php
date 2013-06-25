<?php
/**
 * One Mighty Roar
 *
 * @copyright   2013 One Mighty Roar
 * @link        http://onemightyroar.com
 */

namespace OneMightyRoar\ChronosLib;

use Guzzle\Service\Client;

/**
 * ChronosLib
 *
 * A PHP library to interact with a Chronos API
 *
 * @package OneMightyRoar\ChronosLib
 */
class ChronosLib
{

    /**
     * Class properties
     */

    /**
     * Our Guzzle client class
     *
     * @var string
     * @access protected
     */
    protected $client;

    /**
     * Class methods
     */

    /**
     * Constructor
     *
     * @see Guzzle\Service\Client::__construct()
     * @param string $chronos_url
     * @param array $options
     * @access public
     */
    public function __construct($chronos_url, array $options)
    {
        $this->client = new Client($chronos_url, $options);
    }

    /**
     * Create a new job in Chronos
     *
     * See test case for preparing $schedule array
     *
     * @param string $title
     * @param string $owner
     * @param string $command
     * @param array $schedule
     * @param string $async
     * @access public
     * @return Guzzle\Http\Message\Response
     */
    public function createJob($title, $owner, $command, array $schedule, $async)
    {
        $json_request['name'] = $title;
        $json_request['owner'] = $owner;
        $json_request['command'] = $command;
        $json_request['async'] = $async;
        $json_request['epsilon'] = 'PT' . $schedule['repeat_onfail'] . 'S';
        $json_request['schedule'] = 'R' . $schedule['repeat'] . '/' . $schedule['year'] . '-' . $schedule['month']
        . '-' . $schedule['day'] . 'T' . $schedule['hour'] . ':' . $schedule['min'] . ':' . $schedule['sec'] . 'Z/PT'
        . $schedule['duration'] . 'S';

        $request = $this->client->post('scheduler/iso8601');
        $request->setBody(json_encode($json_request), 'application/json');
        $response = $request->send();

        return $response;
    }

    /**
     * Create a new dependent job in Chronos
     *
     * Job will inherit schedule properties from parent jobs
     *
     * @param string $title
     * @param string $owner
     * @param string $command
     * @param array $parents
     * @param string $async
     * @access public
     * @return Guzzle\Http\Message\Response
     */
    public function createDependentJob($title, $owner, $command, array $parents, $async)
    {
        $json_request['name'] = $title;
        $json_request['owner'] = $owner;
        $json_request['command'] = $command;
        $json_request['async'] = $async;
        $json_request['parents'] = $parents;

        $request = $this->client->post('scheduler/dependency');
        $request->setBody(json_encode($json_request), 'application/json');
        $response = $request->send();

        return $response;
    }

    /**
     * Delete a specific job in Chronos
     *
     * @param string $job_name
     * @access public
     * @return Guzzle\Http\Message\Response
     */
    public function deleteJob($job_name)
    {
        $request = $this->client->delete('scheduler/job/' . $job_name);
        $response = $request->send();

        return $response;
    }

    /**
     * Delete all jobs in Chronos
     *
     * @access public
     * @return Guzzle\Http\Message\Response
     */
    public function deleteAllJobs()
    {
        $request = $this->client->delete('scheduler/jobs');
        $response = $request->send();

        return $response;
    }

    /**
     * Delete all tasks for a specific job
     *
     * @param string $job_name
     * @access public
     * @return Guzzle\Http\Message\Response
     */
    public function deleteAllJobTasks($job_name)
    {
        $request = $this->client->delete('/scheduler/task/kill/' . $job_name);
        $response = $request->send();

        return $response;
    }

    /**
     * Force start a specific job in Chronos
     *
     * @param string $job_name
     * @access public
     * @return Guzzle\Http\Message\Response
     */
    public function startJob($job_name)
    {
        $request = $this->client->put('scheduler/job/' . $job_name);
        $response = $request->send();

        return $response;
    }

    /**
     * Get the dependency graph for Chronos jobs
     *
     * The response will be in the form of a dot file
     *
     * @access public
     * @return Guzzle\Http\Message\Response
     */
    public function getDependencyGraph()
    {
        $request = $this->client->get('scheduler/graph/dot');
        $response = $request->send();

        return $response;
    }

    /**
     * Report if a job has completed or not
     *
     * @param string $task_id
     * @param int $status_code
     * @access public
     * @return Guzzle\Http\Message\Response
     */
    public function reportTaskCompletion($task_id, $status_code)
    {
        $json_request['statusCode'] = $status_code;
        $request = $this->put('scheduler/task/' . $task_id);
        $request->setBody(json_encode($json_request), 'application/json');
        $response = $request->send();

        return $response;
    }

    /**
     * Gets a list of all current jobs
     *
     * Response will be in JSON
     *
     * @access public
     * @return Guzzle\Http\Message\Response
     */
    public function listJobs()
    {
        $request = $this->client->get('scheduler/jobs');
        $response = $request->send();

        return $response;
    }

    /**
     * Backup all current jobs to a file
     *
     * @access public
     */
    public function createJobListBackup()
    {
        date_default_timezone_set('UTC');
        $url = parse_url($this->client->getBaseUrl());
        $backup_file = fopen($url['host'] . '-' . date('h-i-s') . '.bak', 'w');
        if (!$backup_file) {
            throw new \Exception('File could not be opened');
        }

        $jobs = $this->listJobs()->getBody();
        fwrite($backup_file, $jobs);
        fclose($backup_file);
    }

    /**
     * Restore jobs from a backup file
     *
     * @param string $backup_filename
     * @access public
     * @return Guzzle\Http\Message\Response
     */
    public function restoreJobListFromBackup($backup_filename)
    {
        if (file_exists($backup_filename) && is_readable($backup_filename)) {
            $backup_file = file_get_contents($backup_filename);
            $jobs = json_decode($backup_file, true);
        } else {
            throw new \Exception("File doesn't exist or isn't readable");
        }

        if (!empty($jobs)) {
            foreach ($jobs as $job) {
                if (isset($job['parents'])) {
                    $request = $this->client->post('scheduler/dependency');
                } else {
                    $request = $this->client->post('scheduler/iso8601');
                }
                $request->setBody(json_encode($job), 'application/json');
                $response = $request->send();
            }
        } else {
            throw new \Exception("File does not contain content.");
        }

        return $response;
    }
}
