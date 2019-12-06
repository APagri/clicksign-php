<?php

namespace Clicksign;

abstract class ClientBase
{
    protected $url = "https://api.clicksign.com/";
    protected $accessToken = null;
    protected $timeout = 240;
    protected $version = "v1";
    protected $proxy = null;

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;
    }

    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    public function setVersion($version)
    {
        $this->version = $version;
    }

    public function setProxy($proxy)
    {
        $this->proxy = $proxy;
    }

    protected function doRequest($url, $method, $data, $contentType = null)
    {
        $c = curl_init();

        $header = array("Accept: application/json");

        if(isset($contentType))
        {
            array_push($header, "Content-type: $contentType");
        }

        $url = $this->url . $this->version . $url . "?access_token=" . $this->accessToken;

        switch($method)
        {
            case "GET":
                curl_setopt($c, CURLOPT_HTTPGET, true);
                if($data)
                {
                    $url .= "&" . http_build_query($data);
                }
                break;

            case "POST":
                curl_setopt($c, CURLOPT_POST, true);
                if($data)
                {
                    curl_setopt($c, CURLOPT_POSTFIELDS, $data);
                }
                break;

            case "DELETE":
                curl_setopt($c, CURLOPT_CUSTOMREQUEST, $method);
                if ($data)
                {
                    curl_setopt($c, CURLOPT_POST, true);
                    curl_setopt($c, CURLOPT_POSTFIELDS, $data);
                }
                break;
        }

        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_USERAGENT, "Clicksign/PHP");
        curl_setopt($c, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($c, CURLOPT_HEADER, true);
        curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($c, CURLOPT_HTTPHEADER, $header);
        curl_setopt($c, CURLOPT_URL, $url);
        curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);

        $response = curl_exec($c);
        
        $header_size = curl_getinfo($c, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        curl_close($c);

        return [$header,$body];
    }

    public function request($url, $method, $data, $expectedHttpCode, $contentType = null)
    {
        $response = $this->doRequest($url, $method, $data, $contentType);
        return $this->parseResponse($url, $response, $expectedHttpCode);
    }

    public function getFile($url)
    {
        return $this->doRequest($url, "GET", array(), "application/zip, application/octet-stream");
    }

    public function parseResponse($url, $response, $expectedHttpCode)
    {
        $header = false;
        $content = array();
        $status = 200;

        foreach(explode("\r\n", $response) as $line)
        {
            if (strpos($line, "HTTP/1.1") === 0)
            {
                $lineParts = explode(" ", $line);
                $status = intval($lineParts[1]);
                $header = true;
            }
            else if ($line == "")
            {
                $header = false;
            }
            else if ($header)
            {
                $line = explode(": ", $line);
                if($line[0] == "Status")
                {
                    $status = intval(substr($line[1], 0, 3));
                }
            }
            else
            {
                $content[] = $line;
            }
        }

        if($status !== $expectedHttpCode)
        {
            throw new ClicksignException("Expected status [$expectedHttpCode], actual status [$status], URL [$url]", ClicksignException::INVALID_HTTP_CODE);
        }

        $object = json_decode(implode("\n", $content));

        return $object;
    }
}
