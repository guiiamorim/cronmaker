<?php

namespace guiiamorim\CronMaker;

use guiiamorim\CronMaker\Connection\Connector;
use guiiamorim\CronMaker\Storage\StorageHandler;

class CronMaker
{
    public function __construct(
        private StorageHandler $handler
    ){}

    /**
     * @param Job $job
     * @return bool
     * @throws Exception\BusyConnectionException
     * @throws Exception\ClosedPipeException
     * @throws Exception\InvalidProcessException
     * @throws Exception\NoPipesFoundException
     */
    public function addJob(Job $job): bool
    {
        if ($this->handler->save($job))
            return $this->syncJobs();

        return false;
    }

    /**
     * @param Job $job
     * @return bool
     * @throws Exception\BusyConnectionException
     * @throws Exception\ClosedPipeException
     * @throws Exception\InvalidProcessException
     * @throws Exception\NoPipesFoundException
     */
    public function deleteJob(Job $job): bool
    {
        try {
            $this->handler->delete($job);
            return $this->syncJobs();
        } catch (Exception\FileNotFoundException $e) {
            return $this->syncJobs();
        }
    }

    /**
     * @param string|array|null $id
     * @return mixed
     */
    public function getJobs(string|array $id = null): mixed
    {
        if (is_array($id))
            return array_filter($this->handler->find(), fn($job) => in_array($job->uuid, $id));

        return $this->handler->find($id);
    }

    /**
     * @return bool
     * @throws Exception\BusyConnectionException
     * @throws Exception\ClosedPipeException
     * @throws Exception\InvalidProcessException
     * @throws Exception\NoPipesFoundException
     */
    public function syncJobs(): bool
    {
        $jobs = $this->handler->find();
        $activeJobs = array_filter($jobs, fn($job) => $job->status === Job::JOB_ACTIVE && !$job->oneTimeOnly);
        $atJobs = array_filter($jobs, fn($job) => $job->status === Job::JOB_ACTIVE && $job->oneTimeOnly);
        $atInactiveJobs = array_filter($jobs, fn($job) => $job->status === Job::JOB_INACTIVE && $job->oneTimeOnly);

        $cronResult = true;
        if (empty($activeJobs)) {
            $this->clearCrontab();
            $cronResult = false;
        } else {
            Connector::startProcess("crontab -u www-data -");
            $crontab = implode(PHP_EOL, $activeJobs) . PHP_EOL;
            Connector::write($crontab);
            $cronResult = Connector::checkError();
            Connector::freeProcess();
        }

        $atResult = false;
        foreach ($atJobs as $atJob) {
            Connector::startProcess("at -t {$atJob->dateTime->format('YmdHi.ss')}", ignoreErrors: true);
            Connector::write($atJob->command);
            $result = Connector::checkError();
            Connector::freeProcess();
            $atId = preg_replace(
                '/job /',
                '',
                preg_replace(
                    '/(.*\n)(job \d+)(.*)(\n?)/',
                    '$2',
                    $result
                )
            );

            if (!$atId) {
                $atResult = true;
                continue;
            }

            $atJob->atId = $atId;
            $this->handler->save($atJob);
        }

        $today = new \DateTime();
        foreach ($atInactiveJobs as $atInactiveJob) {
            if ($atInactiveJob->dateTime > $today && !empty($atInactiveJob->atId)) {
                Connector::startProcess("atrm {$atInactiveJob->atId}");
                Connector::freeProcess();
            }
        }

        return !($cronResult || $atResult);
    }

    public function clearCrontab()
    {
        Connector::startProcess("crontab -u www-data -r", ignoreErrors: true);
        Connector::freeProcess();
    }
}