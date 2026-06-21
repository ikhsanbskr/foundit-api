<?php

namespace App\Libraries;

use CodeIgniter\Database\Exceptions\DatabaseException;

class MatchingEngine
{
    protected $db;
    protected $imageModel;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
        $this->imageModel = new \App\Models\ItemImageModel();
    }

    /**
     * Parse text to clean keywords.
     */
    public function parseToCleanKeywords($text)
    {
        if (empty($text)) return [];
        $text = strtolower($text);
        $text = preg_replace('/[^\w\s]/', '', $text); 

        $stopwords = [
            'saya', 'kamu', 'dia', 'mereka', 'kita', 'kami', 'anda', 'yang', 'di', 'ke', 
            'dari', 'ada', 'tidak', 'tapi', 'namun', 'ini', 'itu', 'untuk', 'dengan', 
            'atau', 'dan', 'adalah', 'bahwa', 'bukan', 'kalau', 'jika', 'bisa', 'dapat'
        ];

        $words = explode(' ', $text);
        $cleanKeywords = [];
        foreach ($words as $word) {
            $word = trim($word);
            if (!in_array($word, $stopwords) && strlen($word) > 2) {
                $cleanKeywords[] = $word;
            }
        }
        return array_unique($cleanKeywords);
    }

    /**
     * Calculate text similarity (Not heavily used if we rely on DB scoring, but useful for memory scoring).
     */
    public function calculateTextSimilarity($text1, $text2)
    {
        $tokens1 = $this->parseToCleanKeywords($text1);
        $tokens2 = $this->parseToCleanKeywords($text2);
        if (empty($tokens1) || empty($tokens2)) return 0;
        $intersect = array_intersect($tokens1, $tokens2);
        $union = array_unique(array_merge($tokens1, $tokens2));
        return (count($intersect) / count($union)) * 100;
    }

    /**
     * Match a LOST item against FOUND items.
     * @return int Number of matches inserted.
     */
    public function matchLostToFound($lostItem)
    {
        // LAPIS 1: FILTER KASAR DI DATABASE (Ambil FOUND + Gambar Primary-nya)
        $builder = $this->db->table('g_item_discoveries d')
            ->select('d.*, i.image_path as found_primary_image')
            ->join('g_item_images i', 'i.item_id = d.id AND i.is_primary = 1', 'left')
            ->where('d.report_type', 'FOUND')
            ->whereIn('d.status', ['REPORTED', 'SECURED'])
            ->where('d.category_id', $lostItem['category_id']);

        $kandidatFound = $builder->get()->getResultArray();

        if (empty($kandidatFound)) {
            return 0;
        }

        return $this->processMatches($lostItem, $kandidatFound, 'LOST');
    }

    /**
     * Match a FOUND item against LOST items.
     * @return int Number of matches inserted.
     */
    public function matchFoundToLost($foundItem)
    {
        // LAPIS 1: FILTER KASAR DI DATABASE (Ambil LOST + Gambar Primary-nya)
        $builder = $this->db->table('g_item_discoveries d')
            ->select('d.*, i.image_path as lost_primary_image')
            ->join('g_item_images i', 'i.item_id = d.id AND i.is_primary = 1', 'left')
            ->where('d.report_type', 'LOST')
            ->where('d.status', 'REPORTED') // LOST items stay REPORTED until RESOLVED
            ->where('d.category_id', $foundItem['category_id']);

        $kandidatLost = $builder->get()->getResultArray();

        if (empty($kandidatLost)) {
            return 0;
        }

        return $this->processMatches($foundItem, $kandidatLost, 'FOUND');
    }

    /**
     * Internal logic for Lapis 2 & 3.
     */
    private function processMatches($sourceItem, $candidates, $sourceType)
    {
        // $sourceItem could be LOST or FOUND
        // If source is LOST, candidates are FOUND.
        // If source is FOUND, candidates are LOST.

        $sourceNameTokens = $this->parseToCleanKeywords($sourceItem['item_name']);
        $sourceDescTokens = $this->parseToCleanKeywords($sourceItem['description']);
        $sourceLocation   = strtolower(trim($sourceItem['location_found']));

        // Ambil gambar primary milik source
        $sourcePrimaryImage = $this->db->table('g_item_images')
            ->where('item_id', $sourceItem['id'])
            ->where('is_primary', 1)
            ->get()->getRowArray();
        $sourceImagePath = $sourcePrimaryImage ? $sourcePrimaryImage['image_path'] : null;

        $shortlistedCandidates = [];

        // LAPIS 2: PERHITUNGAN BOBOT TEKS DI MEMORI PHP
        foreach ($candidates as $candidate) {
            $dbScore = 0;
            $candidateLocation = strtolower(trim($candidate['location_found']));
            if ($sourceLocation === $candidateLocation) {
                $dbScore += 20;
            } elseif (!empty($sourceLocation) && str_contains($candidateLocation, $sourceLocation)) {
                $dbScore += 10; 
            }

            $candidateNameClean = strtolower($candidate['item_name']);
            $nameMatches = 0;
            foreach ($sourceNameTokens as $token) {
                if (preg_match("/\b" . preg_quote($token, '/') . "\b/i", $candidateNameClean)) {
                    $nameMatches += 5; 
                }
            }
            $dbScore += min($nameMatches, 15); 

            $candidateDescClean = strtolower($candidate['description']);
            $descMatches = 0;
            foreach ($sourceDescTokens as $token) {
                if (preg_match("/\b" . preg_quote($token, '/') . "\b/i", $candidateDescClean)) {
                    $descMatches += 3; 
                }
            }
            $dbScore += min($descMatches, 15); 

            if ($dbScore >= 0) {
                $candidate['computed_db_score'] = $dbScore;
                $shortlistedCandidates[] = $candidate;
            }
        }

        if (empty($shortlistedCandidates)) {
            return 0;
        }

        usort($shortlistedCandidates, function($a, $b) {
            if ($b['computed_db_score'] !== $a['computed_db_score']) {
                return $b['computed_db_score'] <=> $a['computed_db_score'];
            }
            return strtotime($a['event_time']) <=> strtotime($b['event_time']); 
        });

        $bestMatch = $shortlistedCandidates[0];
        $matchesInsertedCount = 0;

        // Tentukan path gambar candidate
        $candidateImagePath = null;
        if ($sourceType === 'LOST') {
            $candidateImagePath = isset($bestMatch['found_primary_image']) ? $bestMatch['found_primary_image'] : null;
        } else {
            $candidateImagePath = isset($bestMatch['lost_primary_image']) ? $bestMatch['lost_primary_image'] : null;
        }

        // LAPIS 3: DEEP VISUAL ANALYSIS
        if ($sourceImagePath && $candidateImagePath) {
            $geminiService = new \App\Libraries\GeminiService();
            $aiResult = $geminiService->compareSingleImageWithVision(
                $sourceImagePath, 
                $candidateImagePath
            );
            
            $visualScore = isset($aiResult['visual_score']) ? (int)$aiResult['visual_score'] : 0;
            $visualScore = min(50, max(0, $visualScore)); 

            $dbScore     = $bestMatch['computed_db_score'];
            $totalScore  = min(100, $dbScore + $visualScore); // Cap at 100%

            if ($totalScore >= 50) {
                $lostTicket = $sourceType === 'LOST' ? $sourceItem['ticket_number'] : $bestMatch['ticket_number'];
                $foundTicket = $sourceType === 'LOST' ? $bestMatch['ticket_number'] : $sourceItem['ticket_number'];
                
                $lostId = $sourceType === 'LOST' ? $sourceItem['id'] : $bestMatch['id'];
                $foundId = $sourceType === 'LOST' ? $bestMatch['id'] : $sourceItem['id'];

                $this->db->table('g_matches')->insert([
                    'lost_ticket'      => $lostTicket,
                    'found_ticket'     => $foundTicket,
                    'confidence_score' => $totalScore,
                    'ai_reason'        => ($aiResult['reason'] ?? 'Cocok') . " [Kalkulasi: Teks {$dbScore}/50 + Visual AI {$visualScore}/50]",
                    'timestamp'        => date('Y-m-d H:i:s')
                ]);

                // Update status (if LOST -> secure the lost and the found. If FOUND -> secure the found and the lost)
                // Actually, FOUND is already being secured by Officer. LOST will be secured.
                $this->db->table('g_item_discoveries')->where('id', $lostId)->update(['status' => 'SECURED']);
                $this->db->table('g_item_discoveries')->where('id', $foundId)->update(['status' => 'SECURED']);
                $matchesInsertedCount++;

                // Send WhatsApp notification if match found from FOUND perspective
                if ($sourceType === 'FOUND') {
                    try {
                        $reporter = $this->db->table('s_users')->select('fullname, phone_number')->where('id', $bestMatch['user_id'])->get()->getRowArray();
                        if ($reporter && !empty($reporter['phone_number'])) {
                            $waApi = new \App\Libraries\WhatsAppApi();
                            $msg = "Halo {$reporter['fullname']},\n\nSistem kami menemukan *potensi barang temuan* yang mungkin cocok dengan laporan kehilangan Anda (*{$bestMatch['item_name']}*).\n\nSegera cek di aplikasi Found It pada menu *Laporan Saya* untuk melihat detailnya!\n\n_Pesan otomatis dari Sistem Found It._";
                            $waApi->sendMessage($reporter['phone_number'], $msg);
                        }
                    } catch (\Exception $waErr) {
                        log_message('error', 'Gagal kirim WA notif reverse match: ' . $waErr->getMessage());
                    }
                }
            }
        }

        return $matchesInsertedCount;
    }
}
