<?php

namespace guiiamorim\CronMaker\Storage;

use guiiamorim\CronMaker\Job;

interface StorageHandler
{
    public function find(string $uuid): mixed;

    public function save(Job $job): bool;

    public function delete(Job $job): bool;
}