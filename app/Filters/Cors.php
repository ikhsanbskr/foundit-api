<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class Cors implements FilterInterface
{
    /**
     * Menambahkan header CORS sebelum request masuk ke Controller
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Izinkan semua origin untuk kebutuhan pengembangan (development) di hackathon
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method, Authorization");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");

        // Menangani pre-flight request (HTTP OPTIONS method) yang otomatis dikirim oleh browser/Axios
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === "OPTIONS") {
            die();
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Tidak perlu melakukan apa-apa setelah request selesai
    }
}