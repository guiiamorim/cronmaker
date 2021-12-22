<?php

namespace guiiamorim\CronMaker\Storage;

use guiiamorim\CronMaker\Exception\FileNotFoundException;
use guiiamorim\CronMaker\Job;

class FilesystemHandler extends \DirectoryIterator
{
    public function __construct($directory)
    {
        parent::__construct($directory);
    }

    public function getPath(): string
    {
        return parent::getPath() . "/";
    }

    public function find(string $uuid = null)
    {
        $jobs = [];
        $this->rewind();
        while ($this->valid()) {
            if ($this->isFile()) {
                if ($uuid && str_contains($this->getFilename(), $uuid) && $this->getExtension() === 'cron')
                    return unserialize(file_get_contents($this->getPathname()));

                $jobs[] = unserialize(file_get_contents($this->getPathname()));
            }
            $this->next();
        }

        return $jobs;
    }

    public function save(Job $job): bool
    {
        $result = file_put_contents($this->getPath() . $job->fileName, serialize($job));

        return $result !== false;
    }

    /**
     * @throws FileNotFoundException
     */
    public function delete(Job $job): bool
    {
        if (!file_exists($this->getPath() . $job->fileName))
            throw new FileNotFoundException("Arquivo {$job->fileName} não encontrado");

        return unlink($this->getPath() . $job->fileName);
    }
}