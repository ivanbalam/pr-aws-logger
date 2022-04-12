<?php

namespace PrAWSLogger\Trait;

use Aws\XRay\XRayClient;

trait XrayTrait
{

    private $appName;
    private $awsCredentials = [
        'region' => 'us-east-1',
        'version' => 'latest',
        'credentials' => [
            'key' => null,
            'secret' => null
        ]
    ];

    private $hex_time;
    private $start_time;
    private $trace_id;
    private $segment_id;
    private $segment;
    private $segmentSql;
    private $end_time;
    private $status;
    private $xray_response;
    private $xray_sql_response;
    private $xray_subsegment_response;
    private $subsegment;

    public $xray;

    public function __construct(){
        $this->initXray();
    }

    private function initXray(){
        $this->appName = $_ENV['APP_NAME'];
        $this->awsCredentials['credentials']['key'] = $_ENV['AWS_SDK_KEY'];
        $this->awsCredentials['credentials']['secret'] = $_ENV['AWS_SDK_SECRET'];
        //$daemon_config = array_merge($this->awsCredentials,['endpoint' => 'http://xray.palaceresorts:2000']);
        $this->xray = new XRayClient($this->awsCredentials);
        $hex_time = dechex(time());
        $this->start_time = microtime(true);
        $this->trace_id = "1-{$hex_time}-{$this->idHexacGenerate(24)}";
        $this->segment_id = $this->idHexacGenerate();
    }

    public function getSegmentParent(){
        return [
            'trace_id' => $this->trace_id,
            'segment_id' => $this->segment_id
        ];
    }

    public function setSegmentParent($trace_id, $segment_id){
        $this->trace_id = $trace_id;
        $this->segment_id = $segment_id;
    }

    public function attachAnnotations(Array $values){
        $this->segment['annotations'] = array_merge($this->segment['annotations'], $values);
    }

    public function attachMetadata(Array $values){
        $this->segment['metadata'] = array_merge($this->segment['metadata'], $values);
    }

    public function attachException($error, $remote = false, $subsegment = false){
        $method = (isset($error->getTrace()[0]["function"])?$error->getTrace()[0]["function"]:"Undefined");
        $exception = [
            'id' => $this->idHexacGenerate(),
            'message' => $error->getMessage(),
            'type' => $error->getCode(),
            'remote' => $remote,
            'stack' => [
                'path' => $error->getFile(),
                'line' => $error->getLine(),
                'label' => $method
            ]
        ];
        if(!$subsegment){
            $this->segment['exception'] = $exception;
        }else{
            $this->subsegment['exception'] = $exception;
        }
    }

    public function initSegment($instance, $request, $segmento = 'General'){
        $instance = explode('::', $instance);
        $this->segment = [
            "name" => $_ENV['APP_NAME'],
            "id" => $this->segment_id,
            "start_time" => $this->start_time,
            "trace_id" => $this->trace_id,
            "end_time" => $this->end_time,
            "http" => [
                "request" => [
                    "method" => (!empty($request))?$request->getMethod():"UNDEFINED",
                    "url" => $request->getHeader('Host').$request->getURI(),
                    "user_agent" => $request->getUserAgent()
                ],
                "response" => [
                    "status" => $this->status
                ]
            ],
            "annotations" => [
                "controller" => $instance[0],
                "method" => $instance[1],
                "segmento" => $segmento,
                "system" => $this->appName,
            ],
            "metadata" => [
                "body" => $request->getJsonRawBody()
            ]
        ];
    }

    public function initSqlSegment($query, $name = null, $string = false){
        $this->segmentSql = [
            "name" => (empty($name))?"db-{$_ENV['APP_NAME']}":$name,
            "id" => $this->idHexacGenerate(),
            "start_time" => microtime(true),
            "end_time" => null,
            "type" => "subsegment",
            "trace_id" => $this->trace_id,
            "parent_id" => $this->segment_id,
            "sql"  => [
                "url" => $_ENV['DB_HOST'],
                "database_type" => $_ENV['DB_ADAPTER'],
                "user" => $_ENV['DB_USER'],
                "sanitized_query" => ($string)?$query:$query['sql']
            ]
        ];
    }

    public function initSubsegment($request_url, $request_method, $request_body, $name = null){
        $this->subsegment = [
            "name" => (empty($name))?$_ENV['APP_NAME']:$name,
            "id" => $this->idHexacGenerate(),
            "start_time" => microtime(true),
            "end_time" => null,
            "type" => "subsegment",
            "trace_id" => $this->trace_id,
            "parent_id" => $this->segment_id,
            "http" => [
                "request" => [
                    "method" => $request_method,
                    "url" => $request_url,
                ],
                "response" => [
                    "status" => 200
                ]
            ],
            "metadata" => [
                "body" => $request_body
            ],
            "exception" => []
        ];
    }

    public function createSqlSegment(){
        $this->segmentSql['end_time'] = microtime(true);

        $segment = json_encode($this->segmentSql);
        $this->xray_sql_response = $this->xray->putTraceSegments([
            'TraceSegmentDocuments' => [$segment]
        ]);

        return $segment;
    }

    public function createSubsegment($status){
        $this->subsegment['end_time'] = microtime(true);
        $this->subsegment['http']['response']['status'] = $status;

        $segment = json_encode($this->subsegment);
        $this->xray_subsegment_response = $this->xray->putTraceSegments([
            'TraceSegmentDocuments' => [$segment]
        ]);

        return $segment;
    }

    public function createSegment($status, $close = true)
    {
        $this->segment['end_time'] = microtime(true);
        $this->segment['http']['response']['status'] = $status;
        if(!$close){
            $this->segment['end_time'] = null;
            $this->segment['in_progress'] = true;
        }

        $segment = json_encode($this->segment);
        $this->xray_response = $this->xray->putTraceSegments([
            'TraceSegmentDocuments' => [$segment]
        ]);

        return $segment;
    }

    public function idHexacGenerate($length = 16){
        $length /= 2;
       return bin2hex(openssl_random_pseudo_bytes($length));
    }
}