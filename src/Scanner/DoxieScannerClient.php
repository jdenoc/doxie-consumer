<?php

namespace Jdenoc\DoxieConsumer\Scanner;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\Response as ResponseStatusCode;

class DoxieScannerClient {

    public const string URI_DELETE = '/scans/delete.json';
    public const string URI_LIST = '/scans.json';
    public const string URI_RECENT = '/scans/recent.json';
    public const string URI_SCAN_PREFIX = '/scans';
    public const string URI_STATUS = '/hello.json';
    public const string URI_THUMBNAIL_PREFIX = '/thumbnails';

    private $httpClient;
    private $outputCli;

    public function __construct(HttpClientInterface $httpClient, OutputInterface $output = null) {
        $this->httpClient = $httpClient;
        $this->outputCli = $output;
    }

    /**
     * GET /hello.json
     */
    public function isAvailable(): bool {
        try {
            $this->output('debug', 'Calling:GET '.self::URI_STATUS);
            $response = $this->httpClient->request('GET', self::URI_STATUS);
            return ResponseStatusCode::HTTP_OK === $response->getStatusCode();
        } catch (\Exception) {
            $this->output('debug', 'scanner unavailable');
            return false;
        }
    }

    /**
     * GET /scans.json
     */
    public function listAllScans(): array {
        return $this->listScans(self::URI_LIST);
    }

    /**
     * GET /scans/recent.json
     */
    public function listRecentScans(): array {
        return $this->listScans(self::URI_RECENT);
    }

    private function listScans(string $requestUri): array {
        try {
            $this->output('debug', "Calling:GET ".$requestUri);
            $response = $this->httpClient->request('GET', $requestUri);
            if(in_array($response->getStatusCode(), [ResponseStatusCode::HTTP_OK, ResponseStatusCode::HTTP_NO_CONTENT])) {
                $scans_in_response = $response->toArray();
            } else {
                $scans_in_response = [];
            }
        } catch (TransportException $e) {
            $this->output('warning', "Connection error occurred:".$e->getMessage());
            return [];
        } catch (\Exception $e) {
            $this->output('error', "Failed to list scans; Exception:".$e->getMessage());
            return [];
        }

        $scans = [];
        foreach($scans_in_response as $scan) {
            $scans[] = new DoxieScan($scan);
        }
        return $scans;
    }

    /**
     * GET /scans/$filename
     */
    public function getScan(DoxieScan $scan, string $downloadDirectory): bool {
        $download_file = rtrim($downloadDirectory, '/').'/'.$this->generateDownloadFilename($scan);
        $request_url = self::URI_SCAN_PREFIX.'/'.$scan->name;
        return $this->downloadFile($request_url, $download_file);
    }

    /**
     * GET /thumbnails/$filename
     */
    public function getThumbnail(DoxieScan $scan): bool {
        $download_file = rtrim(sys_get_temp_dir(), '/').'/thumbnail.'.basename($scan->name);
        $request_url = self::URI_THUMBNAIL_PREFIX.'/'.$scan->name;
        return $this->downloadFile($request_url, $download_file);
    }

    private function downloadFile(string $requestUri, string $downloadLocation): bool {
        try {
            $this->output('debug', "Calling:GET $requestUri; Downloading to:".$downloadLocation);
            $response = $this->httpClient->request('GET', $requestUri);

            $file_handler = fopen($downloadLocation, 'w');
            foreach ($this->httpClient->stream($response) as $chunk) {
                fwrite($file_handler, $chunk->getContent());
            }
            fclose($file_handler);

            return ResponseStatusCode::HTTP_OK === $response->getStatusCode() && file_exists($downloadLocation);
        } catch (\Exception $e) {
            $this->output('error', "Failed to download from ".$requestUri." to ".$downloadLocation.'; Exception:'.$e->getMessage());
            return false;
        }
    }

    /**
     * DELETE /scans/$filename
     */
    public function deleteScan(DoxieScan $scan): bool {
        $request_url = self::URI_SCAN_PREFIX.'/'.$scan->name;
        try {
            $this->output('debug', "Calling:DELETE $request_url");
            $response = $this->httpClient->request('DELETE', $request_url);
            return ResponseStatusCode::HTTP_NO_CONTENT === $response->getStatusCode();
        } catch (\Exception $e) {
            $this->output('error', "Failed to delete scan ".$scan."; Exception:".$e->getMessage());
            return false;
        }
    }

    /**
     * POST /scans/delete.json
     */
    public function deleteScans(array $scans = []): bool {
        $scans_to_delete = [];
        foreach ($scans as $scan) {
            $scans_to_delete[] = '/'.$scan->name;
        }

        $this->output('debug', "Calling:POST ".self::URI_DELETE."; DATA:".json_encode($scans_to_delete));
        try {
            $response = $this->httpClient->request('POST', self::URI_DELETE, ['json' => $scans_to_delete]);
            return ResponseStatusCode::HTTP_NO_CONTENT === $response->getStatusCode();
        } catch (\Exception $e) {
            $this->output('error', "Failed to delete scans ".json_encode($scans_to_delete)."; Exception:".$e->getMessage());
            return false;
        }
    }

    private function generateDownloadFilename(DoxieScan $scan): string {
        $download_filename  = date("YmdHis", strtotime($scan->modified)).'.';
        $download_filename .= pathinfo($scan->name, PATHINFO_FILENAME);
        $download_filename .= '.'.pathinfo($scan->name, PATHINFO_EXTENSION);
        return $download_filename;
    }

    private function output(string $level, string $message): void {
        if(is_null($this->outputCli)) {
            return;
        }
        switch ($level) {
            case 'debug':
                $this->outputCli->debug($message);
                break;
            case 'info':
                $this->outputCli->info($message);
                break;
            case 'warning':
                $this->outputCli->warning($message);
                break;
            case 'error':
                $this->outputCli->error($message);
                break;
            default:
                $this->outputCli->writeln($message);
                break;
        }
    }

}
