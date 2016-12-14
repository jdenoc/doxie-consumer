<?php

require_once __DIR__ .'/../vendor/autoload.php';
require_once __DIR__.'/DoxieScan.php';

/**
 * DOXIE API Documentation
 * @link http://help.getdoxie.com/content/doxiego/05-advanced/03-wifi/04-api/Doxie-API-Developer-Guide.pdf
 */
class DoxieConsumer {

    const METHOD_GET = "GET";
    const METHOD_POST = "POST";
    const METHOD_DELETE = "DELETE";

    /**
     * @var Guzzle\Http\Client
     */
    private $request_client;

    /**
     * @var Monolog\Logger
     */
    private $logger;

    /**
     * @param Guzzle\Http\Client $request_client
     */
    public function set_request_client($request_client){
        $this->request_client = $request_client;
    }

    /**
     * @param Monolog\Logger $logger
     */
    public function set_logger($logger){
        $this->logger = $logger;
    }

    /**
     * @return bool
     */
    public function is_available(){
        $this->are_dependancies_set();
        $request_url = $this->get_doxie_base_url().'/hello.json';
        $this->logger->info("Calling: GET ".$request_url);

        try{
            $response = $this->request_client->get($request_url)->send();
            return $response->isSuccessful();
        } catch(Exception $e){
            $this->logger->addInfo("There was a request timeout\n".$e);
            return false;
        }
    }

    /**
     * @return string
     */
    public function get_doxie_base_url(){
        $base_url = getenv("DOXIE_BASE_URL");
        return rtrim($base_url, '/');
    }

    /**
     * @return string
     */
    public function get_download_location(){
        $location = getenv("DOWNLOAD_LOCATION");
        return rtrim($location, '/');
    }

    /**
     * GET /scans.json
     * @return DoxieScan[]
     */
    public function list_scans(){
        $this->are_dependancies_set();
        $request_url = $this->get_doxie_base_url().".json";
        $this->logger->info("Calling: GET ".$request_url);

        try{
            $response = $this->request_client->get($request_url)->send();
            $response_body = $response->getBody(true);
            $decoded_response = json_decode($response_body, true);
            if(is_null($decoded_response)){
                $decoded_response = array();
            }
        } catch(Exception $e){
            $this->logger->error($e);
            $decoded_response = array();
        }

        $scans = array();
        foreach($decoded_response as $scan){
            $scans[] = new DoxieScan($scan);
        }
        return $scans;
    }

    /**
     * GET /scans/$filename
     * @param DoxieScan $doxie_scan
     * @return string
     */
    public function get_scan($doxie_scan){
        $this->are_dependancies_set();
        $download_filename = $this->get_download_location().DIRECTORY_SEPARATOR;
        $download_filename .= pathinfo($doxie_scan->name, PATHINFO_FILENAME).'.';
        $download_filename .= date("YmdHis", strtotime($doxie_scan->modified));
        $download_filename .= '.'.pathinfo($doxie_scan->name, PATHINFO_EXTENSION);

        $request_url = $this->get_doxie_base_url().$this->pre_slash_string($doxie_scan->name);
        $this->logger->info("Calling: GET ".$request_url);
        $this->logger->info("Downloading file to: ".$download_filename);

        try{
            $response = $this->request_client->get($request_url)
                ->setResponseBody($download_filename)
                ->send();
            return ($response->isSuccessful() && file_exists($download_filename));
        } catch(Exception $e){
            $this->logger->error("Failed to download file\n".$e);
            return false;
        }
    }

    /**
     * DELETE /scans/$filename
     * @param DoxieScan $doxie_scan
     * @return bool
     */
    public function delete_scan($doxie_scan){
        $this->are_dependancies_set();

        $request_url = $this->get_doxie_base_url().$this->pre_slash_string($doxie_scan->name);
        $this->logger->info("Calling: DELETE ".$request_url);

        try{
            $response = $this->request_client->delete($request_url)->send();
            return $response->isSuccessful();
        } catch(Exception $e){
            return false;
        }
    }

    /**
     * POST /scans/delete.json
     * @param DoxieScan[] $doxie_scans
     * @return bool
     */
    public function delete_scans($doxie_scans=array()){
        $this->are_dependancies_set();

        $to_delete = array();
        foreach($doxie_scans as $doxie_scan){
            $to_delete[] = $this->pre_slash_string($doxie_scan->name);
        }
        $to_delete = json_encode($to_delete);

        $request_url = $this->get_doxie_base_url().'/delete.json';
        $this->logger->info("Calling POST ".$request_url."\nPOST_DATA:".print_r($to_delete, true));
        try{
            $response = $this->request_client->post($request_url, null, $to_delete)->send();
            return $response->isSuccessful();
        } catch(Exception $e){
            return false;
        }
    }

    /**
     * @param string $doxie_file
     * @return string
     */
    private function pre_slash_string($doxie_file){
        if(substr($doxie_file, 0, 1) != '/'){
            $doxie_file = '/'.$doxie_file;
        }
        return $doxie_file;
    }

    private function are_dependancies_set(){
        if(!isset($this->request_client)){
            throw new InvalidArgumentException("request_client not set. You must call set_request_client or this service will not work");
        } else if(!isset($this->logger)){
            throw new InvalidArgumentException("logger not set. You must call set_logger or this service will not work");
        }
    }

}