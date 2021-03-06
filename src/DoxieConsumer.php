<?php

namespace jdenoc\DoxieConsumer;

use Guzzle;
use Monolog;
use Dotenv;
use jdenoc\NetworkScanner\NetworkScanner;

/**
 * DOXIE API Documentation
 * @link http://help.getdoxie.com/content/doxiego/05-advanced/03-wifi/04-api/Doxie-API-Developer-Guide.pdf
 */
class DoxieConsumer {

    const ENV_KEY_PHYSICAL_ADDRESS = 'DOXIE_PHYSICAL_ADDRESS';
    const ENV_KEY_DOWNLOAD_LOCATION = 'DOWNLOAD_LOCATION';
    const URI_STATUS = '/hello.json';
    const URI_LIST = '/scans.json';
    const URI_FILE_PREFIX = '/scans';
    const URI_THUMBNAIL_PREFIX = '/thumbnails';
    const URI_DELETE = '/scans/delete.json';
    const DEFAULT_IP_IDENTIFIER = 'doxie';

    /**
     * @var Guzzle\Http\Client
     */
    private $request_client;

    /**
     * @var Monolog\Logger
     */
    private $logger;

    /**
     * @var NetworkScanner
     */
    private $network_scanner;

    /**
     * @var string
     */
    private $scanner_ip_address;

    public function __construct(){
        Dotenv::required(self::ENV_KEY_PHYSICAL_ADDRESS);
        Dotenv::required(self::ENV_KEY_DOWNLOAD_LOCATION);
    }

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
     * @param NetworkScanner $network_scanner
     */
    public function set_network_scanner($network_scanner){
        $this->network_scanner = $network_scanner;
    }

    /**
     * @param string $ip_address
     */
    public function set_scanner_ip($ip_address){
        $this->logger->debug("Scanner is located at: ".$ip_address);
        $this->scanner_ip_address = $ip_address;
    }

    /**
     * @return string
     */
    public function get_scanner_ip(){
        return $this->scanner_ip_address;
    }

    public function unset_scanner_ip(){
        $this->scanner_ip_address = null;
    }

    /**
     * @return bool
     */
    public function is_scanner_ip_set(){
        return !empty($this->scanner_ip_address);
    }

    /**
     * @return bool
     */
    public function is_available(){
        $this->are_dependencies_set();

        if(!$this->is_scanner_ip_set()){
            // check if doxie scanner can be found on the network
            try{
                $this->logger->debug("Is scanner available on network?");
                $network_ip = $this->network_scanner->is_physical_address_on_network($this->get_doxie_physical_address());
                if(!$network_ip){
                    return false;
                }
                $this->set_scanner_ip($network_ip);

            } catch(\Exception $e){
                $this->logger->warning("There was an error checking scanner availability\n".$e->getMessage());
                return false;
            }
        }

        $request_url = $this->get_doxie_base_url().self::URI_STATUS;
        $this->logger->debug("Calling: GET ".$request_url);

        try{
            $response = $this->request_client->get($request_url)->send();
            return $response->isSuccessful();
        } catch(\Exception $e){
            $this->logger->warning("There was an error checking scanner availability\n".$e->getMessage());
            // There was an error confirming IP address was available/accessible.
            // Lets unset it, so we can scan the local network.
            $this->unset_scanner_ip();
            return false;
        }
    }

    /**
     * @return string
     */
    public function get_doxie_physical_address(){
        $physical_address = getenv(self::ENV_KEY_PHYSICAL_ADDRESS);
        return $physical_address;
    }

    /**
     * Alias for get_doxie_physical_address()
     * @return string
     */
    public function get_doxie_mac_address(){
        return $this->get_doxie_physical_address();
    }

    /**
     * @return string
     */
    public function get_doxie_base_url(){
        $base_url = 'http://';
        if($this->is_scanner_ip_set()){
            $base_url .= $this->get_scanner_ip();
        } else {
            $base_url .= self::DEFAULT_IP_IDENTIFIER;
        }
        return rtrim($base_url, '/');
    }

    /**
     * @return string
     */
    public function get_download_location(){
        $location = getenv(self::ENV_KEY_DOWNLOAD_LOCATION);
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
            $this->logger->error("Failed to delete scans\n".$e->getMessage());
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
        if(!isset($this->network_scanner)){
            // if we get here, it is safe to assume that the logger has been set
            $error_msg = "network_scanner not set. You must call set_network_scanner or this service will not work";
            $this->logger->error($error_msg);
            throw new \InvalidArgumentException();
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