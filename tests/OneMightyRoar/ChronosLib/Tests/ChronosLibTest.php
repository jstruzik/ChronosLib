<?php

namespace OneMightyRoar\ChronosLib\Tests;

use OneMightyRoar\ChronosLib\ChronosLib;
use PHPUnit_Framework_TestCase;

/**
 * ChronosLibTest
 *
 * @package OneMightyRoar\ChronosLib\Tests
 */
class ChronosLibTest extends PHPUnit_Framework_TestCase
{

    public $options;
    public $chronos;
    // todo: create a mock Guzzle client server for testing
    public $url = 'http://example-chronos-server.com';

    public function __construct()
    {
        $this->options = array(
            'curl.options' => array(
                CURLOPT_PORT => '4400'
            )
        );
        $this->chronos = new ChronosLib($this->url, $this->options);
    }

    public function testCreateJob()
    {
        $schedule['year'] = '2013';
        $schedule['month'] = '06';
        $schedule['day'] = '19';
        $schedule['hour'] = '12';
        $schedule['min'] = '00';
        $schedule['sec'] = '00';
        $schedule['duration'] = '60';
        $schedule['repeat_onfail'] = '120';
        $response = $this->chronos->createJob('test', 'jake', 'echo "hello" > /tmp/foo.txt', $schedule, true);

        $this->assertEquals('204', $response->getStatusCode());

        $jobs = $this->chronos->listJobs()->json();
        $job_found = false;
        foreach ($jobs as $job) {
            if ($job['name'] == 'test') {
                $job_found = true;
            }
        }
        $this->assertTrue($job_found);
    }

    public function testListJobs()
    {
        $schedule['year'] = '2013';
        $schedule['month'] = '06';
        $schedule['day'] = '19';
        $schedule['hour'] = '12';
        $schedule['min'] = '00';
        $schedule['sec'] = '00';
        $schedule['duration'] = '60';
        $schedule['repeat_onfail'] = '120';
        $this->chronos->createJob('test_list', 'jake', 'echo "hello" > /tmp/foo.txt', $schedule, true);

        $response = $this->chronos->listJobs();
        $jobs = $response->json();
        $job_found = false;
        foreach ($jobs as $job) {
            if ($job['name'] == 'test_list') {
                $job_found = true;
            }
        }
        $this->assertEquals('200', $response->getStatusCode());
        $this->assertTrue($job_found);
    }

    public function testCreateDependentJob()
    {
        $schedule['year'] = '2013';
        $schedule['month'] = '06';
        $schedule['day'] = '19';
        $schedule['hour'] = '12';
        $schedule['min'] = '00';
        $schedule['sec'] = '00';
        $schedule['duration'] = '60';
        $schedule['repeat_onfail'] = '120';
        $this->chronos->createJob('test2', 'jake', 'echo "hello" > /tmp/foo.txt', $schedule, true);
        $this->chronos->createJob('test3', 'jake', 'echo "hello" > /tmp/foo.txt', $schedule, true);

        $parents = array();
        array_push($parents, 'test2');
        array_push($parents, 'test3');

        $response = $this->chronos->createDependentJob('little_test', 'jake', 'echo "hello" > /tmp/foo.txt', $parents, true);

        $this->assertEquals('204', $response->getStatusCode());

        $jobs = $this->chronos->listJobs()->json();
        $job_found = false;
        foreach ($jobs as $job) {
            if ($job['name'] == 'little_test') {
                $job_found = true;
            }
        }
        $this->assertTrue($job_found);
    }

    public function testStartJob()
    {
        $schedule['year'] = '2013';
        $schedule['month'] = '06';
        $schedule['day'] = '19';
        $schedule['hour'] = '12';
        $schedule['min'] = '00';
        $schedule['sec'] = '00';
        $schedule['duration'] = '60';
        $schedule['repeat_onfail'] = '120';
        $this->chronos->createJob('test_start', 'jake', 'echo "hello" > /tmp/foo.txt', $schedule, true);

        $response = $this->chronos->startJob('test_start');

        $this->assertEquals('204', $response->getStatusCode());

    }

    public function testGetDependencyGraph()
    {
        $response = $this->chronos->getDependencyGraph();

        $this->assertEquals('200', $response->getStatusCode());
        $this->assertContains('digraph G', $response->getMessage());

    }

    public function testBackupJobs()
    {
        $this->chronos->createJobListBackup();

        $url = parse_url($this->url);
        $backup_filename_prefix = $url['host'] . '-';
        # Doing a glob search so date/time don't differ, requires the folder to be clean
        foreach (glob($backup_filename_prefix . '*') as $backup_filename) {
            if (file_exists($backup_filename) && is_readable($backup_filename)) {
                $backup_file = file_get_contents($backup_filename);
                $jobs = json_decode($backup_file, true);
                unlink($backup_filename);
            } else {
                throw new \Exception("File doesn't exist or isn't readable");
            }
        }
        $job_found = false;
        foreach ($jobs as $job) {
            if ($job['name'] == 'test') {
                $job_found = true;
            }
        }
        $this->assertTrue($job_found);
    }

    public function testCreateJobsFromBackup()
    {
        $test_file = fopen('test_jobs.bak', 'w');
        $jobs_json = '[{"schedule":"R/2013-06-24T21:17:00.000Z/PT60S","name":"test_list","command":"echo \"hello\" >' .
            '/tmp/foo.txt","epsilon":"PT120S","successCount":0,"errorCount":0,"executor":"","executorFlags":"",' .
            '"retries":2,"owner":"jake","lastSuccess":"","lastError":"","async":true},{"schedule":"R/2013-06-24T21:'.
            '17:00.000Z/PT60S","name":"test_list_2","command":"echo \"hello\" > /tmp/foo.txt","epsilon":"PT120S","success'.
            'Count":0,"errorCount":0,"executor":"","executorFlags":"","retries":2,"owner":"jake","lastSuccess":"","'.
            'lastError":"","async":true},{"schedule":"R/2013-06-24T21:17:00.000Z/PT60S","name":"test_list_3","command":"echo'.
            ' \"hello\" > /tmp/foo.txt","epsilon":"PT120S","successCount":0,"errorCount":0,"executor":"","executorFla'.
            'gs":"","retries":2,"owner":"jake","lastSuccess":"","lastError":"","async":true},{"parents":["test2","tes'.
            't3"],"name":"test_list_4","command":"echo \"hello\" > /tmp/foo.txt","epsilon":"PT60S","successCount":0,"'.
            'errorCount":0,"executor":"","executorFlags":"","retries":2,"owner":"jake","lastSuccess":"","lastError":'.
            '"","async":true}]';
        fwrite($test_file, $jobs_json);

        $response = $this->chronos->restoreJobListFromBackup('test_jobs.bak');

        $this->assertEquals('204', $response->getStatusCode());

        $jobs = $this->chronos->listJobs()->json();
        $job_found = false;
        foreach ($jobs as $job) {
            if ($job['name'] == 'test') {
                $job_found = true;
            }
        }
        $this->assertTrue($job_found);
    }

    public function testDeleteAllJobTasks()
    {
        $schedule['year'] = '2013';
        $schedule['month'] = '06';
        $schedule['day'] = '19';
        $schedule['hour'] = '12';
        $schedule['min'] = '00';
        $schedule['sec'] = '00';
        $schedule['duration'] = '60';
        $schedule['repeat_onfail'] = '120';
        $this->chronos->createJob('test_delete_tasks', 'jake', 'echo "hello" > /tmp/foo.txt', $schedule, true);

        $response = $this->chronos->deleteAllJobTasks('test_delete_tasks');

        $this->assertEquals('204', $response->getStatusCode());
    }

    public function testDeleteJob()
    {
        $schedule['year'] = '2013';
        $schedule['month'] = '06';
        $schedule['day'] = '19';
        $schedule['hour'] = '12';
        $schedule['min'] = '00';
        $schedule['sec'] = '00';
        $schedule['duration'] = '60';
        $schedule['repeat_onfail'] = '120';
        $this->chronos->createJob('test_delete', 'jake', 'echo "hello" > /tmp/foo.txt', $schedule, true);

        $response = $this->chronos->deleteJob('test_delete');

        $this->assertEquals('204', $response->getStatusCode());
    }

    public function testDeleteAllJobs()
    {
        $response = $this->chronos->deleteAllJobs();

        $this->assertEquals('204', $response->getStatusCode());
    }
}
