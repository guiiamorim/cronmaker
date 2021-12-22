<?php

namespace guiiamorim\CronMaker\Connection;

use guiiamorim\CronMaker\Exception\BusyConnectionException;
use guiiamorim\CronMaker\Exception\ClosedPipeException;
use guiiamorim\CronMaker\Exception\InvalidProcessException;
use guiiamorim\CronMaker\Exception\NoPipesFoundException;

class Connector
{
    protected static $process;
    protected static $pipes;

    /**
     * @return mixed
     */
    public static function getProcess(): mixed
    {
        return self::$process;
    }

    /**
     * @return mixed
     */
    public static function getPipes(): mixed
    {
        return self::$pipes;
    }

    /**
     * @throws NoPipesFoundException
     * @throws ClosedPipeException
     */
    public static function write(string $input)
    {
        if (empty(self::$pipes))
            throw new NoPipesFoundException("Nenhum pipe aberto.");

        if (!is_resource(self::$pipes[0]))
            throw new ClosedPipeException("Pipe STDIN fechado.");

        fwrite(self::$pipes[0], $input);
        fclose(self::$pipes[0]);
    }

    /**
     * @throws ClosedPipeException
     * @throws NoPipesFoundException
     */
    public static function read(): bool|string
    {
        if (empty(self::$pipes))
            throw new NoPipesFoundException("Nenhum pipe aberto.");

        if (!is_resource(self::$pipes[1]))
            throw new ClosedPipeException("Pipe STDOUT fechado.");

        return stream_get_contents(self::$pipes[1]);
    }

    /**
     * @throws InvalidProcessException
     * @throws BusyConnectionException
     */
    public static function startProcess($command, string $cwd = null, array $env = null, array $otherOptions = null, bool $ignoreErrors = false)
    {
        if (!empty(self::$process))
            throw new BusyConnectionException("Um processo já está em andamento.");

        self::$process = proc_open(
            $command,
            [
                ["pipe", "r"],
                ["pipe", "w"],
                ["pipe", "w"]
            ],
            self::$pipes,
            $cwd,
            $env,
            $otherOptions
        );

        stream_set_blocking(self::$pipes[2], false);

        $error = self::checkError();
        if ($error !== false and !$ignoreErrors) {
            self::freeProcess();
            throw new InvalidProcessException($error);
        }
    }

    public static function checkError(): bool|string
    {
        if (is_resource(self::$pipes[2])) return stream_get_contents(self::$pipes[2]) ?: false;

        return false;
    }

    public static function freeProcess()
    {
        foreach (self::$pipes as $pipe) {
            if (is_resource($pipe))
                fclose($pipe);
        }
        proc_close(self::$process);
        self::$process = null;
        self::$pipes = null;
    }
}