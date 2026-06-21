<?php

namespace App\Libraries;

class WhatsAppApi
{
    /**
     * URL API Node.js WhatsApp Service (default: localhost:3000)
     */
    protected $apiUrl = 'http://localhost:3000/send-message';

    /**
     * Send a WhatsApp message using the local Node.js microservice.
     * 
     * @param string $number Phone number of the recipient.
     * @param string $message The message body.
     * @return array Response from the API.
     */
    public function sendMessage($number, $message)
    {
        // Jika nomor kosong, abaikan
        if (empty($number)) {
            return [
                'status' => 'error',
                'message' => 'No phone number provided.'
            ];
        }

        $client = \Config\Services::curlrequest();

        try {
            $response = $client->post($this->apiUrl, [
                'json' => [
                    'number' => $number,
                    'message' => $message
                ],
                'headers' => [
                    'Accept' => 'application/json'
                ],
                // Timeout singkat agar tidak memblokir aplikasi jika service mati
                'timeout' => 5 
            ]);

            return json_decode($response->getBody(), true);
            
        } catch (\Exception $e) {
            log_message('error', '[WhatsAppApi] Gagal mengirim pesan ke ' . $number . '. Error: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to connect to WhatsApp service.',
                'details' => $e->getMessage()
            ];
        }
    }
}
