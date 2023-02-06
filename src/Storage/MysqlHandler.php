<?php

namespace guiiamorim\CronMaker\Storage;

use guiiamorim\CronMaker\Job;

class MysqlHandler extends \PDO implements StorageHandler
{
    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null)
    {
        parent::__construct($dsn, $username, $password, $options);
    }

    public function find(string $uuid = null): mixed
    {
        if (!$uuid) {
            return $this->query("SELECT * FROM cronjob")
                ->fetchAll(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, Job::class, ['uuid', 'name', 'command', 'user']);
        }
        $stmt = $this->prepare("SELECT * FROM cronjob WHERE uuid = :uuid");
        $stmt->execute([':uuid' => $uuid]);

        return $stmt->fetchObject(Job::class, ['uuid', 'name', 'command', 'user']);
    }

    public function save(Job $job): bool
    {
        $stmt = "";
        try {
            $fields = array_keys(array_filter($job->__serialize(), fn($v) => $v !== '' && $v !== null));
            $values = array_map(fn($v) => ":{$v}", $fields);
            $params = [];
            foreach ($fields as $field) {
                $params[$field] = is_bool($job->$field) ? intval($job->$field) : $job->$field;
            }
            $update = array_map(fn($f, $v) => "{$f} = {$v}", $fields, $values);

            $fields = implode(", ", $fields);
            $values = implode(", ", $values);
            $update = implode(", ", $update);

            $stmt = $this->prepare("INSERT INTO cronjob ({$fields}) VALUES ({$values}) ON DUPLICATE KEY UPDATE {$update}");
            $stmt->execute($params);

            return (bool) $stmt->rowCount();
        } catch (\Exception $e) {
            echo $e;
            echo $stmt->debugDumpParams();
            return false;
        }
    }

    public function delete(Job $job): bool
    {
        try {
            $stmt = $this->prepare("DELETE FROM cronjob WHERE uuid = :uuid");
            $stmt->execute([':uuid' => $job->uuid]);

            return (bool) $stmt->rowCount();
        } catch (\Exception $e) {
            return false;
        }
    }
}