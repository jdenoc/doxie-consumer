<?php

namespace jdenoc\DoxieConsumer;

use Guzzle;
use Monolog;

/**
 * DOXIE API Documentation
 * @link http://help.getdoxie.com/content/doxiego/05-advanced/03-wifi/04-api/Doxie-API-Developer-Guide.pdf
 */
class DoxieConsumer {

    const URI_STATUS = '/hello.json';
    const URI_LIST = '/scans.json';
    const URI_FILE_PREFIX = '/scans';
    const URI_THUMBNAIL_PREFIX = '/thumbnails';
    const URI_DELETE = '/scans/delete.json';

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
        $this->logger->debug("Logger set");
    }

    /**
     * @return bool
     */
    public function is_available(){
        $this->are_dependencies_set();
        $request_url = $this->get_doxie_base_url().self::URI_STATUS;
        $this->logger->debug("Calling: GET ".$request_url);

        try{
            $response = $this->request_client->get($request_url)->send();
            return $response->isSuccessful();
        } catch(\Exception $e){
            $this->logger->warning("There was an error checking scanner availability\n".$e->getMessage());
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
     * @param DoxieScan $doxie_scan
     * @return string
     */
    public function generate_download_filename($doxie_scan){
        $download_filename  = date("YmdHis", strtotime($doxie_scan->modified)).'.';
        $download_filename .= pathinfo($doxie_scan->name, PATHINFO_FILENAME);
        $download_filename .= '.'.pathinfo($doxie_scan->name, PATHINFO_EXTENSION);
        return $download_filename;
    }

    /**
     * GET /scans.json
     * @return DoxieScan[]
     */
    public function list_scans(){
        $this->are_dependencies_set();
        $request_url = $this->get_doxie_base_url().self::URI_LIST;
        $this->logger->debug("Calling: GET ".$request_url);

        try{
            $response = $this->request_client->get($request_url)->send();
            $response_body = $response->getBody(true);
            $decoded_response = json_decode($response_body, true);
            if(is_null($decoded_response)){
                $decoded_response = array();
            }
        } catch(\Exception $e){
            $this->logger->error("Failed to list scans\n".$e->getMessage());
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
     * @param string $download_location
     * @return string
     */
    public function get_scan($doxie_scan, $download_location){
        $request_url = $this->get_doxie_base_url().self::URI_FILE_PREFIX.$this->pre_slash_string($doxie_scan->name);
        return $this->get_file($request_url, $download_location);
    }

    /**
     * GET /thumbnails/$filename
     * @param DoxieScan $doxie_scan
     * @param string $download_location
     * @return bool
     */
    public function get_thumbnail($doxie_scan, $download_location){
        $request_url = $this->get_doxie_base_url().self::URI_THUMBNAIL_PREFIX.$this->pre_slash_string($doxie_scan->name);
        return $this->get_file($request_url, $download_location);
    }

    /**
     * DELETE /scans/$filename
     * @param DoxieScan $doxie_scan
     * @return bool
     */
    public function delete_scan($doxie_scan){
        $this->are_dependencies_set();

        $request_url = $this->get_doxie_base_url().self::URI_FILE_PREFIX.$this->pre_slash_string($doxie_scan->name);
        $this->logger->debug("Calling: DELETE ".$request_url);

        try{
            $response = $this->request_client->delete($request_url)->send();
            return $response->isSuccessful();
        } catch(\Exception $e){
            $this->logger->error("Failed to delete scan ".$doxie_scan."\n".$e->getMessage());
            return false;
        }
    }

    /**
     * POST /scans/delete.json
     * @param DoxieScan[] $doxie_scans
     * @return bool
     */
    public function delete_scans($doxie_scans=array()){
        $this->are_dependencies_set();

        $to_delete = array();
        foreach($doxie_scans as $doxie_scan){
            $to_delete[] = $this->pre_slash_string($doxie_scan->name);
        }
        $to_delete = json_encode($to_delete);

        $request_url = $this->get_doxie_base_url().self::URI_DELETE;
        $this->logger->debug("Calling POST ".$request_url."\nPOST_DATA:".print_r($to_delete, true));
        try{
            $response = $this->request_client->post($request_url, null, $to_delete)->send();
            return $response->isSuccessful();
        } catch(\Exception $e){
            $this->logger->error("failed to delete scans\n".$e->getMessage());
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

    /**
     * throws an exception if any of the all dependencies are not set
     * @throws \InvalidArgumentException
     */
    private function are_dependencies_set(){
        if(!isset($this->logger)){
            throw new \InvalidArgumentException("logger not set. You must call set_logger or this service will not work");
        }
        if(!isset($this->request_client)){
            // if we get here, it is safe to assume that the logger has been set
            $error_msg = "request_client not set. You must call set_request_client or this service will not work";
            $this->logger->error($error_msg);
            throw new \InvalidArgumentException($error_msg);
        }
    }

    /**
     * Downloads file in URL to specified location
     * @param string $request_url
     * @param string $download_location
     * @return bool
     */
    private function get_file($request_url, $download_location){
        $this->are_dependencies_set();
        $this->logger->debug("Calling: GET ".$request_url."\nDownloading scan to: ".$download_location);

        try{
            $response = $this->request_client->get($request_url)
                ->setResponseBody($download_location)
                ->send();
            return ($response->isSuccessful() && file_exists($download_location));
        } catch(\Exception $e){
            $this->logger->error("Failed to download from ".$request_url." to ".$download_location);
            return false;
        }
    }

}