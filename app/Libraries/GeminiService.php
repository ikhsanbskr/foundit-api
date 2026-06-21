<?php

namespace App\Libraries;

class GeminiService
{
    protected $apiKey;

    public function __construct() {
        $this->apiKey = env('GEMINI_API_KEY');
    }

    public function compareSingleImageWithVision(string $lostImagePath, string $foundImagePath)
    {
        $imgLostFullPath = FCPATH . ltrim($lostImagePath, '/');
        $imgFoundFullPath = FCPATH . ltrim($foundImagePath, '/');

        if (!file_exists($imgLostFullPath) || !file_exists($imgFoundFullPath)) {
            return ['visual_score' => 0, 'reason' => 'Berkas fisik gambar utama tidak ditemukan di server.'];
        }

        // Encode masing-masing gambar ke Base64
        $imgLostBase64 = base64_encode(file_get_contents($imgLostFullPath));
        $imgFoundBase64 = base64_encode(file_get_contents($imgFoundFullPath));

        // Prompt Forensik 1-vs-1
        // $promptText = "
        //     Kamu adalah sistem AI Forensik Visual Lost and Found Kampus.
        //     Tugasmu adalah membandingkan Gambar A (Foto Utama Barang Hilang) dengan Gambar B (Foto Utama Barang Temuan).

        //     Analisis secara ketat dan skeptis:
        //     1. Apakah Gambar A dan Gambar B menunjukkan satu objek yang sama dari segi merek, model, tipe, dan warna?
        //     2. Cari kesamaan ciri unik (seperti pola lecet, stiker yang menempel, keretakan, atau modifikasi khusus). Jika jenis objek berbeda jauh, langsung beri nilai total 0.

        //     Format respons WAJIB JSON steril tanpa markdown:
        //     {
        //         \"visual_score\": 45,
        //         \"reason\": \"Tulis penjelasan forensik singkat dan padat di sini.\"
        //     }
        // ";

        // Optimized prompt and lock the scoring maximum
        $promptText = "
            Kamu adalah sistem AI Forensik Visual Lost and Found Kampus.
            Tugasmu adalah membandingkan Gambar A (Foto Utama Barang Hilang) dengan Gambar B (Foto Utama Barang Temuan).

            Analisis secara ketat dan sangat skeptis:
            1. Apakah Gambar A dan Gambar B menunjukkan objek yang sama dari segi merek, model, tipe, dan warna?
            2. Cari kesamaan ciri unik (pola lecet, stiker, keretakan, modifikasi seperti thumb grip). Jika jenis objek berbeda jauh, langsung beri nilai total 0.

            ATURAN PENILAIAN MUTLAK (MAKSIMAL 50):
            - Berikan skor 40 - 50 jika objek terbukti identik secara visual, model sama, warna sama, dan ditemukan ciri unik spesifik yang sama.
            - Berikan skor 0 - 39 jika ada perbedaan model, warna, atau terbukti objek yang berbeda.

            ATURAN OUTPUT DAN TOKEN OPTIMIZATION:
            - Jawab HANYA dengan JSON steril tanpa markdown, tanpa backtick (```json), tanpa text penjelasan di luar JSON.
            - Batasi string \"reason\" MAKSIMAL 15 KATA saja! Tulis poin intinya langsung (misal: 'Merek sama, warna putih, keduanya memiliki thumb grip cakar kucing yang identik'). Jangan menulis kalimat pembuka atau penutup yang panjang!

            Format respons WAJIB seperti ini:
            {
                \"visual_score\": 45,
                \"reason\": \"[Tulis analisis padat maksimal 15 kata di sini]\"
            }
        ";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $promptText],
                        [
                            'inlineData' => [
                                'mimeType' => 'image/jpeg',
                                'data' => $imgLostBase64
                            ]
                        ],
                        [
                            'inlineData' => [
                                'mimeType' => 'image/jpeg',
                                'data' => $imgFoundBase64
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'responseMimeType' => 'application/json'
            ]
        ];

        $client = \Config\Services::curlrequest();
        $response = $client->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $this->apiKey, [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => $payload,
            'http_errors' => false
        ]);

        $body = json_decode($response->getBody(), true);

        if (isset($body['candidates'][0]['content']['parts'][0]['text'])) {
            return json_decode($body['candidates'][0]['content']['parts'][0]['text'], true);
        }

        return ['visual_score' => 0, 'reason' => 'Gagal memproses gambar.'];
    }
}
