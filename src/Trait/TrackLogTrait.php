<?php

namespace PrAWSLogger\Tr;

trait TrackLogTrait
{
    private $sistema = "PALACE";
    public function generateTrackId($user){
        $this->sistema = $_ENV['APP_NAME'];
        $cadena = date('Y-m-d H:i:s').$this->sistema.$user.random_bytes(4);
        return sha1($cadena);
    }
}