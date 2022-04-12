<?php


namespace PrAWSLogger;

use PrAWSLogger\Tr\TrackLogTrait;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Maxbanton\Cwh\Handler\CloudWatch;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class CloudWatchLogService
{

    use TrackLogTrait;

    private $logFile = "";
    private $appName = "";
    private $awsCredentials = [
        'region' => 'us-east-1',
        'version' => 'latest',
        'credentials' => [
            'key' => null,
            'secret' => null
        ]
    ];

    private $logger;
    public $track_uuid;

    public function initLogger($track_uuid = '', $instance = null, $group = null, $retencion = 90) {
        $this->track_uuid = $track_uuid;
        if(empty($this->track_uuid))
            $this->track_uuid = $this->generateTrackId("{$_ENV['APP_NAME']}@palaceresorts.com");
        $instance = (!empty($instance))?$instance:(date("Ymd").'-'.$_ENV['APP_NAME']);
        $this->appName = $_ENV['APP_NAME'];
        $this->awsCredentials['credentials']['key'] = $_ENV['AWS_SDK_KEY'];
        $this->awsCredentials['credentials']['secret'] = $_ENV['AWS_SDK_SECRET'];

        $cwClient = new CloudWatchLogsClient($this->awsCredentials);
        // Log group name, will be created if none
        $cwGroupName = (!empty($group))?$group:$_ENV['AWS_CLOUDWATCH_GROUP'];
        // Log stream name, will be created if none
        $cwStreamNameInstance = $instance;
        $cwRetentionDays = $retencion;

        $cwHandlerInstanceNotice = new CloudWatch($cwClient, $cwGroupName, $cwStreamNameInstance, $cwRetentionDays, 10000,
            [ 'application' =>
                $_ENV['APP_NAME']
            ],
            Logger::INFO
        );

        $this->logger = new Logger($this->appName);
        $now = date("YmdHis");
        $syslogFormatter = new LineFormatter("{$now} | {$this->appName} | {$this->track_uuid} | %level_name% | %message% %context% %extra%",null,false,true);
        $cwHandlerInstanceNotice->setFormatter($syslogFormatter);
        $this->logger->pushHandler($cwHandlerInstanceNotice);

        if($_ENV['LOGGER_FILE']){
            $this->logFile = $_ENV['LOGGER_FILE_PATH'];
            $infoHandler = new StreamHandler(__DIR__."/".$this->logFile, Logger::INFO);
            $formatter = new LineFormatter(null, null, false, true);
            $infoHandler->setFormatter($formatter);
            $this->logger->pushHandler($infoHandler);
        }
    }

    public function errorCurl($instance, $message, $code, $request, $system = null){
        $instance = explode('::', $instance);
        $this->logger->error('message ', $this->generateData($system, $instance[0], $instance[1], $message, $code, $request));
    }

    public function warn($instance, $message, $code, $request, $system = null){
        $instance = explode('::', $instance);
        $this->logger->warning('message ', $this->generateData($system, $instance[0], $instance[1], $message, $code, $request));
    }

    public function setWarning($data, $request, $system = null){
        $this->logger->warning('message ', $this->createInfoLogger($data, $request, $system));
    }

    public function setError($data, $request, $system = null) {
        $this->logger->error('message ', $this->createInfoLogger($data, $request, $system));
    }

    private function createInfoLogger($data, $request, $system = null){
        $file = explode("\\", $data->getFile());
        $class = $file[count($file)-1];
        $method = (isset($data->getTrace()[0]["function"])?$data->getTrace()[0]["function"]:"Undefined");
        return $this->generateData($system, $class, $method, $data->getMessage(), $data->getCode(), $request);
    }

    private function generateData($system, $class, $method, $message, $code, $request ){
        return [
            'system' => (!empty($system))?$system:$_ENV['APP_NAME'],
            'class' => $class,
            'method' => $method,
            'message' => $message,
            'code' => $code,
            "track_uuid" => $this->track_uuid,
            "request_body" => $request->getJsonRawBody(true),
            "request_url" => $request->getURI(),
            "request_method" => $request->getMethod()
        ];
    }
}