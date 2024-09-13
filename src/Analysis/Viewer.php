<?php

/**
 * This file is part of vsr extend analysis
 * @author Vitor Reis <vitor@d5w.com.br>
 */

namespace VSR\Extend\Analysis;

use VSR\Extend\Analysis;

class Viewer
{
    public static function execute()
    {
        switch (isset($_GET['d5whub-extend-analysis']) ? $_GET['d5whub-extend-analysis'] : null) {
            case 'file':
                if (!isset($_REQUEST['file'])) {
                    http_response_code(400);
                    exit;
                }
                static::outputFile($_REQUEST['file']);
                exit;

            case 'viewer-data':
                static::outputViewerData();
                exit;

            case 'request-data':
                static::outputRequestData();
                exit;

            default:
                self::outputFile('index.html', [
                    "base" => explode(
                        '?',
                        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
                        . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"
                    )[0]
                ]);
                exit;
        }
    }

    private static function cache($hash)
    {
        $etag = "W/\"$hash\"";

        # Cache
        $cache = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? $_SERVER['HTTP_IF_NONE_MATCH'] : '';
        if ($cache === $etag) {
            http_response_code(304);
            exit;
        }

        header("ETag: $etag");
        # header('Cache-Control: public, max-age=31536000');
    }

    private static function gzip($data)
    {
        if (!function_exists('gzencode')) {
            return $data;
        }

        if (empty($_SERVER['HTTP_ACCEPT_ENCODING']) || strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') === false) {
            return $data;
        }

        $data = gzencode($data);
        header('Content-Encoding: gzip');
        header('Content-Length: ' . strlen($data));
        return $data;
    }

    private static function outputJson($data)
    {
        $data = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        static::cache(md5($data));

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Length: ' . strlen($data));
        echo static::gzip($data);
        exit;
    }

    private static function outputFile($file, $replace = null)
    {
        $dir = realpath(__DIR__ . '/Viewer');
        $file = realpath("$dir/$file");

        if (substr($file, 0, strlen($dir)) !== $dir) {
            http_response_code(403);
            exit;
        }

        if (!file_exists($file)) {
            http_response_code(404);
            exit;
        }

        static::cache(md5_file($file));

        $mimeType = [
            'html' => 'text/html',
            'vue' => 'text/html',
            'js' => 'application/javascript'
        ];
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mimeType = isset($mimeType[$ext]) ? $mimeType[$ext] : 'text/plain';
        $content = file_get_contents($file);

        if ($replace) {
            foreach ($replace as $key => $value) {
                $content = preg_replace("/\{\{\s*$key\s*}}/", strval($value), $content);
            }
        }

        header("Content-Type: $mimeType; charset=utf-8");
        header('Content-Length: ' . strlen($content));
        echo static::gzip($content);
        exit;
    }

    private static function outputViewerData()
    {
        static::outputJson(Analysis::getDriver()->getViewerData($_REQUEST));
        exit;
    }

    private static function outputRequestData()
    {
        static::outputJson(Analysis::getDriver()->getRequestData(
            isset($_REQUEST['id']) ? $_REQUEST['id'] : false
        ));
        exit;
    }
}
