<?php

namespace PrAWSLogger;

use AWSLogger\CloudWatchLogService;

trait LoggerCloudWatchTrait
{

    public $track_uuid;
    private $service;

    public function initLogger($track_uuid = '', $instance = null, $group = null, $retencion = 90) {
        $this->service = new CloudWatchLogService();
        $this->service->initLogger($track_uuid, $instance, $group, $retencion);
        $this->track_uuid = $this->service->track_uuid;
    }

    public function errorCurl($instance, $message, $code, $request, $system = null){
        $this->service->errorCurl($instance, $message, $code, $request, $system);
    }

    public function warn($instance, $message, $code, $request, $system = null){
        $this->service->warn($instance, $message, $code, $request, $system);
    }

    public function setWarning($data, $request, $system = null){
        $this->service->setWarning($data, $request, $system);
    }

    public function setError($data, $request, $system = null) {
        $this->service->setError($data, $request, $system);
    }
}
