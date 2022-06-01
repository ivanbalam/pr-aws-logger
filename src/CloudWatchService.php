<?php


namespace PrAWSLogger;

use PrAWSLogger\Tr\TrackLogTrait;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Maxbanton\Cwh\Handler\CloudWatch;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class CloudWatchService
{

    use TrackLogTrait;

    private $logFile = "";
    private $appName = "";
    private $awsCredentials = [
        'region' => 'us-east-1',
        'version' => 'latest'
    ];

    private $logger;
    private $group;
    private $stream;
    private $track_uuid;
    private $instance;
    private $request = [
        'url' => '',
        'method' => '',
        'body' => []
    ];

    public function __construct($track_uuid = null)
    {
        if(empty($track_uuid))
            $track_uuid = $this->generateTrackId("{$_ENV['APP_NAME']}@palaceresorts.com");

        $this->stream = date("Ymd").'-'.$_ENV['APP_NAME'];
        $this->group = $_ENV['AWS_CLOUDWATCH_GROUP'];
        $this->track_uuid = $track_uuid;
    }

    public function getTrackUuid(){
        return $this->track_uuid;
    }

    public function setGroup($group){
        $this->group = $group;
    }

    public function setDefaultGroup(){
        $this->group = $_ENV['AWS_CLOUDWATCH_GROUP'];
    }

    public function setDefaultStream(){
        $this->stream = date("Ymd").'-'.$_ENV['APP_NAME'];
    }

    public function setDefaultGroupStream(){
        $this->setDefaultGroup();
        $this->setDefaultStream();
    }

    public function setStream($stream){
        $this->stream = $stream;
    }

    public function setInstance($instance){
        $this->instance = $instance;
    }

    public function setRequest($request){
        $this->request['url'] = $request->getURI();
        $this->request['method'] = $request->getMethod();
        $this->request['body'] = $request->getJsonRawBody(true);
    }

    public function setRequestBody($data){
        $this->request['body'] = $data;
    }

    private function initLogger($retencion = 90) {
        $this->appName = $_ENV['APP_NAME'];

        if($_ENV['AWS_USE_CREDENTIALS']==true){
            $this->awsCredentials['credentials']= [
                'key' => $_ENV['AWS_SDK_KEY'],
                'secret' => $_ENV['AWS_SDK_SECRET']
            ];
        }

        $cwClient = new CloudWatchLogsClient($this->awsCredentials);
        // Log group name, will be created if none
        $cwGroupName = $this->group;
        // Log stream name, will be created if none
        $cwStreamNameInstance = $this->stream;
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
            $infoHandler = new StreamHandler($this->logFile, Logger::INFO);
            $formatter = new LineFormatter(null, null, false, true);
            $infoHandler->setFormatter($formatter);
            $this->logger->pushHandler($infoHandler);
        }
    }

    private function reset(){
        $this->logger = null;
    }

    public function errorCurl($message, $code = 0, $system = null){
        $this->initLogger();
        $instance = explode('::', $this->instance);
        $this->logger->error('message ', $this->generateData($system, $instance[0], $instance[1], $message, $code));
        $this->reset();
    }

    public function warn($message, $code = 0, $system = null){
        $this->initLogger();
        $instance = explode('::', $this->instance);
        $this->logger->warning('message ', $this->generateData($system, $instance[0], $instance[1], $message, $code));
        $this->reset();
    }

    public function setWarning($data, $system = null){
        $this->initLogger();
        $this->logger->warning('message ', $this->createInfoLogger($data, $system));
        $this->reset();
    }

    public function info($message, $code = 0, $system = null){
        $this->initLogger();
        $instance = explode('::', $this->instance);
        $this->logger->info('message ', $this->generateData($system, $instance[0], $instance[1], $message, $code));
        $this->reset();
    }

    public function setError($data, $system = null) {
        $this->initLogger();
        $this->logger->error('message ', $this->createInfoLogger($data, $system));
        $this->reset();
    }

    private function createInfoLogger($data, $system = null){
        $file = explode("\\", $data->getFile());
        $class = $file[count($file)-1];
        $method = (isset($data->getTrace()[0]["function"])?$data->getTrace()[0]["function"]:"Undefined");
        return $this->generateData($system, $class, $method, $data->getMessage(), $data->getCode());
    }

    private function generateData($system, $class, $method, $message, $code){
        return [
            'system' => (!empty($system))?$system:$_ENV['APP_NAME'],
            'class' => $class,
            'method' => $method,
            'message' => $message,
            'code' => $code,
            "track_uuid" => $this->track_uuid,
            "request_body" => $this->request['body'],
            "request_url" => $this->request['url'],
            "request_method" => $this->request['method']
        ];
    }
}