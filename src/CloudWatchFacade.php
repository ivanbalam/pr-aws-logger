<?php

namespace PrAWSLogger;

use PrAWSLogger\CloudWatchLogService;

static class CloudWatchFacade
{
    private static $track_uuid;
    private static $service;

    public static function initLogger($track_uuid = '', $instance = null, $group = null, $retencion = 90) {
        self::$service = new CloudWatchLogService();
        self::$service->initLogger($track_uuid, $instance, $group, $retencion);
        self::$track_uuid = self::$service->track_uuid;
    }

    public static function errorCurl($instance, $message, $code, $request, $system = null){
        self::$service->errorCurl($instance, $message, $code, $request, $system);
    }

    public static function warn($instance, $message, $code, $request, $system = null){
        self::$service->warn($instance, $message, $code, $request, $system);
    }

    public static function setWarning($data, $request, $system = null){
        self::$service->setWarning($data, $request, $system);
    }

    public static function setError($data, $request, $system = null) {
        self::$service->setError($data, $request, $system);
    }
}