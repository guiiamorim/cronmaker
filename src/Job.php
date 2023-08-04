<?php

namespace guiiamorim\CronMaker;

use Ramsey\Uuid\Rfc4122\UuidV4;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class Job
{
    const JOB_ACTIVE = 1;
    const JOB_INACTIVE = 0;

    public function __construct(
        private string                    $action,
        private string|array              $command,
        private string                    $name,
        private string                    $user,
        private string|int                $minutes = "*",
        private string|int                $hours = "*",
        private string|int                $day = "*",
        private string|int                $month = "*",
        private string|int                $weekday = "*",
        private \DateTime|string|null     $dateTime = null,
        private bool                      $oneTimeOnly = false,
        private int                       $status = self::JOB_ACTIVE,
        private UuidInterface|string|null $uuid = null,
        private ?string                   $atId = null,
    )
    {
        $this->parseDate();

        $this->generateUuid();
    }

    public static function createFromGlobals(): Job
    {
        $params = $_POST ?: $_GET ?: json_decode(file_get_contents('php://input'), true);
        $params = array_filter($params, fn($p) => (!empty($p) && $p !== "null") || $p == "0");

        foreach ($params as $param => $value) {
            if (!property_exists(self::class, $param))
                unset($params[$param]);

            if ($param === "dateTime")
                $params[$param] = !empty($value) ? \DateTime::createFromFormat('m-d H:i', $value) : null;
        }

        return new self(...$params);
    }

    public function __get(string $name)
    {
        if (property_exists($this, $name))
            return $this->$name;
        elseif ($name === "fileName")
            return $this->uuid . "_" . $this->name . ".cron";

        return null;
    }

    /**
     * @param int $status
     */
    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    /**
     * @param string $atId
     */
    public function setAtId(string $atId): void
    {
        $this->atId = $atId;
    }

    /**
     * @throws \Exception
     */
    private function parseDate(): void
    {
        if (is_string($this->dateTime)) {
            $this->dateTime = new \DateTime($this->dateTime);
        }

        if ($this->dateTime) {
            [
                $this->month,
                $this->day,
                $this->hours,
                $this->minutes
            ] = explode(",", $this->dateTime->format("m,d,H,i"));
        } elseif ($notValid = ['*', '0', '00'] and !in_array($this->month, $notValid) and !in_array($this->day, $notValid)) {
            $this->dateTime = \DateTime::createFromFormat(
                'm-d H:i',
                str_replace(
                    "*",
                    "00",
                    $this->month . "-" . $this->day . " " . $this->hours . ":" . $this->minutes
                )
            );
        }
    }

    public function __serialize(): array
    {
        $properties = get_object_vars($this);
        $properties['dateTime'] = $properties['dateTime']?->format('m-d H:i');

        return $properties;
    }

    /**
     * @throws \Exception
     */
    public function __unserialize(array $data): void
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key))
                if ($key === "dateTime")
                    $this->$key = !empty($value) ? \DateTime::createFromFormat('m-d H:i', $value) : null;
                else
                    $this->$key = $value;
        }
    }

    private function generateUuid(): void
    {
        if (empty($this->uuid))
            $this->uuid = Uuid::uuid4();
        elseif (is_string($this->uuid))
            $this->uuid = Uuid::isValid($this->uuid) ? Uuid::fromString($this->uuid) : Uuid::uuid4();
    }

    public function __toString(): string
    {
        return sprintf(
            "%s %s %s %s %s %s",
            $this->minutes,
            $this->hours,
            $this->day,
            $this->month,
            $this->weekday,
            $this->command
        );
    }

    public function toJson(): bool|string
    {
        return json_encode($this->__serialize());
    }
}