create table cronjob
(
    uuid        varchar(255)                    not null
        primary key,
    name        varchar(255)                    not null,
    user        varchar(255) default 'www-data' not null,
    minutes     varchar(255) default '*'        null,
    hours       varchar(255) default '*'        null,
    day         varchar(255) default '*'        null,
    month       varchar(255) default '*'        null,
    weekday     varchar(255) default '*'        null,
    dateTime    varchar(255)                    null,
    oneTimeOnly tinyint      default 0          null,
    status      tinyint      default 1          null,
    atId        varchar(255)                    null,
    command     varchar(255)                    not null,
    action      varchar(150)                    null,
    constraint cronjob_uuid_uindex
        unique (uuid)
)
    engine = InnoDB;

