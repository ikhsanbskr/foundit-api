<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\ItemDiscoveryModel;
use App\Models\ItemImageModel;
use CodeIgniter\API\ResponseTrait;

class ReportController extends BaseController
{
    use ResponseTrait;

    protected $discoveryModel;
    protected $imageModel;

    public function __construct()
    {
        $this->discoveryModel = new ItemDiscoveryModel();
        $this->imageModel = new ItemImageModel();
    }

    private function getUserIdFromToken()
    {
        $header = $this->request->getHeaderLine('Authorization');
        if (!$header) return 1; // Default fallback for development
        
        // Header is "Bearer <token>"
        $token = trim(str_replace('Bearer', '', $header));
        if (!$token) return 1;

        $decoded = base64_decode($token);
        if (!$decoded) return 1;

        // format is ID-Role-Salt
        $parts = explode('-', $decoded);
        if (count($parts) >= 1 && is_numeric($parts[0])) {
            return (int)$parts[0];
        }

        return 1;
    }

    public function submitFound()
    {
        try {
            $userId = $this->getUserIdFromToken();

            // Ambil data form
            $categoryId = $this->request->getPost('category_id');
            $categoryDetailId = $this->request->getPost('category_detail_id');
            $itemName = $this->request->getPost('item_name');
            $locationFound = $this->request->getPost('location_found');
            $description = $this->request->getPost('description');
            $verificationDesc = $this->request->getPost('verification_description');

            // Optional data
            $bountyAmount = $this->request->getPost('bounty_amount') ?: 0;
            $bankId = $this->request->getPost('bank_id') ?: null;
            $accountNumber = $this->request->getPost('account_number') ?: null;
            $eventTime = $this->request->getPost('event_time') ?: null;
            $reportType = $this->request->getPost('report_type') ?: 'FOUND';
            $status = $this->request->getPost('status') ?: 'REPORTED';

            // Validasi sederhana
            if (!$categoryId || !$categoryDetailId || !$locationFound || !$description || !$itemName) {
                return $this->failValidationErrors('Data wajib (Kategori, Nama, Lokasi, Deskripsi) belum lengkap.');
            }

            // Generate ticket number
            $ticketNumber = 'TKT-' . strtoupper(bin2hex(random_bytes(4)));

            // 1. Simpan ke tabel penemuan
            $discoveryId = $this->discoveryModel->insert([
                'user_id' => $userId,
                'category_id' => $categoryId,
                'category_detail_id' => $categoryDetailId,
                'bank_id' => $bankId,
                'report_type' => $reportType,
                'status' => $status,
                'item_name' => $itemName,
                'location_found' => $locationFound,
                'description' => $description,
                'verification_description' => $verificationDesc,
                'bounty_amount' => $bountyAmount,
                'account_number' => $accountNumber,
                'event_time' => $eventTime,
                'ticket_number' => $ticketNumber
            ]);

            if (!$discoveryId) {
                return $this->failServerError('Gagal menyimpan laporan.');
            }

            // 2. Proses upload dan convert gambar ke WebP
            $files = $this->request->getFiles();

            if (isset($files['images'])) {
                $images = $files['images'];
                // Pastikan formatnya array
                if (!is_array($images)) {
                    $images = [$images];
                }

                $isPrimary = true; // Gambar pertama dijadikan primary

                foreach ($images as $file) {
                    if ($file->isValid() && !$file->hasMoved()) {
                        // Generate nama unik untuk WebP
                        $newName = $file->getRandomName() . '.webp';
                        $uploadPath = FCPATH . 'uploads/items/';
                        
                        // Pastikan folder ada
                        if (!is_dir($uploadPath)) {
                            mkdir($uploadPath, 0777, true);
                        }

                        // Kompresi dan konversi (Resolusi diturunkan agar lebih cepat)
                        $imageService = \Config\Services::image()
                            ->withFile($file->getTempName())
                            ->resize(800, 800, true, 'height')
                            ->convert(IMAGETYPE_WEBP);
                        
                        // Quality WebP diturunkan ke 65 (default 90 sangat berat)
                        $imageService->save($uploadPath . $newName, 65);

                        // Simpan ke DB
                        $this->imageModel->insert([
                            'item_id' => $discoveryId,
                            'image_path' => 'uploads/items/' . $newName,
                            'is_primary' => $isPrimary
                        ]);

                        $isPrimary = false; // Hanya file pertama yang true
                    }
                }
            }

            // 3. JALANKAN MATCHING ENGINE
            $matchCount = 0;
            if ($reportType === 'LOST') {
                $db = \Config\Database::connect();
                $newLostItem = $db->table('g_item_discoveries')->where('id', $discoveryId)->get()->getRowArray();
                $matchCount = $this->executeThreePhaseMatching($newLostItem);
            }

            return $this->respondCreated([
                'status' => 201,
                'message' => 'Laporan berhasil dikirim dan disebarkan!',
                'data' => [
                    'id' => $discoveryId,
                    'ticket_number' => $ticketNumber,
                    'matches_found' => $matchCount
                ]
            ]);

        } catch (\Exception $e) {
            return $this->failServerError('Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    public function getFoundReports()
    {
        try {
            $db = \Config\Database::connect();
            $builder = $db->table('g_item_discoveries d');
            $builder->select('d.id, d.ticket_number, d.item_name, d.description, d.status, d.created_at, d.location_found, u.username, u.fullname, c.category_name, d.user_id, d.report_type');
            $builder->join('s_users u', 'u.id = d.user_id', 'left');
            $builder->join('gm_category c', 'c.id = d.category_id', 'left');
            $builder->where('d.report_type', 'FOUND');

            // Apply Filters
            $keyword = $this->request->getGet('keyword');
            $category = $this->request->getGet('category');
            $dateStart = $this->request->getGet('date_start');
            $dateEnd = $this->request->getGet('date_end');
            $status = $this->request->getGet('status');

            if (!empty($keyword)) {
                $builder->groupStart();
                $builder->like('d.item_name', $keyword);
                $builder->orLike('d.description', $keyword);
                $builder->orLike('d.location_found', $keyword);
                $builder->groupEnd();
            }
            if (!empty($category)) {
                $builder->where('d.category_id', $category);
            }
            if (!empty($dateStart)) {
                $builder->where('DATE(d.created_at) >=', $dateStart);
            }
            if (!empty($dateEnd)) {
                $builder->where('DATE(d.created_at) <=', $dateEnd);
            }
            if (!empty($status)) {
                $builder->where('d.status', strtoupper($status));
            }

            $builder->orderBy('d.created_at', 'DESC');
            
            $reports = $builder->get()->getResultArray();

            // Format data dan ambil images
            $formattedReports = [];
            foreach ($reports as $report) {
                // Get Images
                $images = $this->imageModel->where('item_id', $report['id'])->findAll();
                $imageUrls = [];
                foreach ($images as $img) {
                    $imageUrls[] = '/' . ltrim($img['image_path'], '/');
                }

                // Batasi nama maksimal 2 kata
                $fullname = $report['fullname'] ?? 'N/A';
                $nameWords = explode(' ', trim($fullname));
                if (count($nameWords) > 2) {
                    $fullname = $nameWords[0] . ' ' . $nameWords[1];
                }

                $formattedReports[] = [
                    'id' => $report['id'],
                    'ticket_number' => $report['ticket_number'] ?? null,
                    'headerDark' => $report['user_id'] % 2 == 0, // Random styling
                    'avatar' => '/profile/user_image.png', // Menggunakan gambar lokal
                    'initials' => strtoupper(substr($report['username'] ?? 'U', 0, 2)),
                    'username' => ($report['username'] ?? 'UNKNOWN') . ' • ' . $fullname,
                    'meta' => date('d M Y H:i', strtotime($report['created_at'])),
                    'location' => strtoupper($report['location_found']),
                    'status' => strtoupper($report['status']),
                    'tagDark' => $report['user_id'] % 2 == 1, // Random styling
                    'category' => strtoupper($report['category_name']),
                    'title' => strtoupper($report['item_name']),
                    'description' => $report['description'],
                    'images' => $imageUrls
                ];
            }

            return $this->respond([
                'status' => 200,
                'message' => 'Success',
                'data' => $formattedReports
            ]);

        } catch (\Exception $e) {
            return $this->failServerError('Gagal memuat feed: ' . $e->getMessage());
        }
    }

    public function getMyPosts()
    {
        try {
            $header = $this->request->getHeaderLine('Authorization');
            $token = null;

            if (!empty($header)) {
                if (preg_match('/Bearer\s(\S+)/', $header, $matches)) {
                    $token = $matches[1];
                }
            }

            if (!$token) {
                return $this->failUnauthorized('Akses ditolak: Token tidak ditemukan.');
            }

            $userModel = new \App\Models\UserModel();
            $user = $userModel->where('api_token', $token)->first();

            if (!$user) {
                return $this->failUnauthorized('Akses ditolak: Token tidak valid.');
            }

            $db = \Config\Database::connect();
            $builder = $db->table('g_item_discoveries d');
            $builder->select('d.id, d.ticket_number, d.item_name, d.description, d.status, d.created_at, d.location_found, u.username, u.fullname, c.category_name, d.user_id, d.report_type');
            $builder->join('s_users u', 'u.id = d.user_id', 'left');
            $builder->join('gm_category c', 'c.id = d.category_id', 'left');
            $builder->where('d.user_id', $user['id']);

            // Apply Filters
            $keyword = $this->request->getGet('keyword');
            $category = $this->request->getGet('category');
            $dateStart = $this->request->getGet('date_start');
            $dateEnd = $this->request->getGet('date_end');
            $status = $this->request->getGet('status');

            if (!empty($keyword)) {
                $builder->groupStart();
                $builder->like('d.item_name', $keyword);
                $builder->orLike('d.description', $keyword);
                $builder->orLike('d.location_found', $keyword);
                $builder->groupEnd();
            }
            if (!empty($category)) {
                $builder->where('d.category_id', $category);
            }
            if (!empty($dateStart)) {
                $builder->where('DATE(d.created_at) >=', $dateStart);
            }
            if (!empty($dateEnd)) {
                $builder->where('DATE(d.created_at) <=', $dateEnd);
            }
            if (!empty($status)) {
                $builder->where('d.status', strtoupper($status));
            }

            $builder->orderBy('d.created_at', 'DESC');
            
            $reports = $builder->get()->getResultArray();

            $formattedReports = [];
            foreach ($reports as $report) {
                $images = $this->imageModel->where('item_id', $report['id'])->findAll();
                $imageUrls = [];
                foreach ($images as $img) {
                    $imageUrls[] = '/' . ltrim($img['image_path'], '/');
                }

                $fullname = $report['fullname'] ?? 'N/A';
                $nameWords = explode(' ', trim($fullname));
                if (count($nameWords) > 2) {
                    $fullname = $nameWords[0] . ' ' . $nameWords[1];
                }

                $formattedReports[] = [
                    'id' => $report['id'],
                    'ticket_number' => $report['ticket_number'] ?? null,
                    'type' => $report['report_type'],
                    'headerDark' => $report['user_id'] % 2 == 0,
                    'avatar' => '/profile/user_image.png',
                    'initials' => strtoupper(substr($report['username'] ?? 'U', 0, 2)),
                    'username' => ($report['username'] ?? 'UNKNOWN') . ' • ' . $fullname,
                    'meta' => date('d M Y // H:i', strtotime($report['created_at'])),
                    'location' => strtoupper($report['location_found']),
                    'status' => strtoupper($report['status']),
                    'tagDark' => $report['user_id'] % 2 == 1,
                    'category' => strtoupper($report['category_name']),
                    'title' => $report['item_name'],
                    'description' => $report['description'],
                    'images' => $imageUrls
                ];
            }

            return $this->respond([
                'status' => 200,
                'message' => 'Success',
                'data' => $formattedReports
            ]);

        } catch (\Exception $e) {
            return $this->failServerError('Gagal memuat postingan saya: ' . $e->getMessage());
        }
    }

    private function getUserFromToken()
    {
        $header = $this->request->getHeaderLine('Authorization');
        if (preg_match('/Bearer\s(\S+)/', $header, $matches)) {
            $userModel = new \App\Models\UserModel();
            return $userModel->where('api_token', $matches[1])->first();
        }
        return null;
    }

    public function getReport($id)
    {
        try {
            $db = \Config\Database::connect();
            $report = $db->table('g_item_discoveries')->where('id', $id)->get()->getRowArray();
            if (!$report) return $this->failNotFound('Laporan tidak ditemukan');

            $images = $this->imageModel->where('item_id', $id)->findAll();
            $report['images'] = [];
            foreach ($images as $img) {
                $report['images'][] = [
                    'id' => $img['id'],
                    'url' => '/' . ltrim($img['image_path'], '/')
                ];
            }

            return $this->respond(['status' => 200, 'data' => $report]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    public function deletePost($id)
    {
        try {
            $user = $this->getUserFromToken();
            if (!$user) return $this->failUnauthorized('Akses ditolak.');

            $db = \Config\Database::connect();
            $report = $db->table('g_item_discoveries')->where('id', $id)->get()->getRowArray();

            if (!$report) return $this->failNotFound('Laporan tidak ditemukan');
            if ($report['user_id'] != $user['id'] && $user['role'] !== 'admin') {
                return $this->failForbidden('Anda tidak berhak menghapus laporan ini.');
            }
            if (in_array(strtoupper($report['status']), ['SECURED', 'RESOLVED'])) {
                return $this->failForbidden('Laporan yang sudah SECURED atau RESOLVED tidak dapat dihapus.');
            }

            // Hapus gambar fisik
            $images = $this->imageModel->where('item_id', $id)->findAll();
            foreach ($images as $img) {
                $path = FCPATH . ltrim($img['image_path'], '/');
                if (file_exists($path)) @unlink($path);
            }

            $db->table('g_item_discoveries')->where('id', $id)->delete();
            return $this->respondDeleted(['status' => 200, 'message' => 'Laporan berhasil dihapus']);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    public function updateReport($id)
    {
        try {
            $user = $this->getUserFromToken();
            if (!$user) return $this->failUnauthorized('Akses ditolak.');

            $db = \Config\Database::connect();
            $report = $db->table('g_item_discoveries')->where('id', $id)->get()->getRowArray();

            if (!$report) return $this->failNotFound('Laporan tidak ditemukan');
            if ($report['user_id'] != $user['id'] && $user['role'] !== 'admin') {
                return $this->failForbidden('Anda tidak berhak mengedit laporan ini.');
            }
            if (in_array(strtoupper($report['status']), ['SECURED', 'RESOLVED'])) {
                return $this->failForbidden('Laporan yang sudah SECURED atau RESOLVED tidak dapat diedit.');
            }

            // Update text fields
            $updateData = [
                'category_id' => $this->request->getPost('category_id'),
                'category_detail_id' => $this->request->getPost('category_detail_id'),
                'item_name' => $this->request->getPost('item_name'),
                'location_found' => $this->request->getPost('location_found'),
                'description' => $this->request->getPost('description'),
                'verification_description' => $this->request->getPost('verification_description'),
                'bounty_amount' => $this->request->getPost('bounty_amount'),
                'bank_id' => $this->request->getPost('bank_id'),
                'account_number' => $this->request->getPost('account_number'),
                'event_time' => $this->request->getPost('event_time'),
            ];

            // Remove empty fields
            $updateData = array_filter($updateData, function($val) { return $val !== null && $val !== ''; });
            if (!empty($updateData)) {
                $db->table('g_item_discoveries')->where('id', $id)->update($updateData);
            }

            // Handle kept images
            $keptImages = $this->request->getPost('kept_images'); // Array of image IDs to keep
            if (!is_array($keptImages)) $keptImages = [];

            // Delete images not in kept_images
            $existingImages = $this->imageModel->where('item_id', $id)->findAll();
            foreach ($existingImages as $img) {
                if (!in_array($img['id'], $keptImages)) {
                    $path = FCPATH . ltrim($img['image_path'], '/');
                    if (file_exists($path)) @unlink($path);
                    $this->imageModel->delete($img['id']);
                }
            }

            // Handle new uploaded images
            $files = $this->request->getFiles();
            if (isset($files['images'])) {
                $images = $files['images'];
                if (!is_array($images)) $images = [$images];

                $isPrimary = empty($keptImages); // If no kept images, first new one is primary

                foreach ($images as $file) {
                    if ($file->isValid() && !$file->hasMoved()) {
                        $newName = $file->getRandomName() . '.webp';
                        $uploadPath = FCPATH . 'uploads/items/';
                        if (!is_dir($uploadPath)) mkdir($uploadPath, 0777, true);

                        $imageService = \Config\Services::image()
                            ->withFile($file->getTempName())
                            ->resize(800, 800, true, 'height')
                            ->convert(IMAGETYPE_WEBP);
                        $imageService->save($uploadPath . $newName, 65);

                        $this->imageModel->insert([
                            'item_id' => $id,
                            'image_path' => 'uploads/items/' . $newName,
                            'is_primary' => $isPrimary
                        ]);
                        $isPrimary = false;
                    }
                }
            }

            return $this->respond(['status' => 200, 'message' => 'Laporan berhasil diperbarui']);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    private function executeThreePhaseMatching($lostItem)
    {
        $db = \Config\Database::connect();
        
        // LAPIS 1: FILTER KASAR DI DATABASE (Ambil FOUND + Gambar Primary-nya)
        $kandidatFound = $db->table('g_item_discoveries d')
            ->select('d.*, i.image_path as found_primary_image')
            ->join('g_item_images i', 'i.item_id = d.id AND i.is_primary = 1')
            ->where('d.report_type', 'FOUND')
            ->whereIn('d.status', ['REPORTED', 'SECURED'])
            ->where('d.category_id', $lostItem['category_id'])
            ->where('d.category_detail_id', $lostItem['category_detail_id'])
            ->where('d.event_time >=', $lostItem['event_time']) 
            ->get()->getResultArray();

        if (empty($kandidatFound)) {
            return 0;
        }

        // Ambil gambar primary milik laporan LOST
        $lostPrimaryImage = $db->table('g_item_images')
            ->where('item_id', $lostItem['id'])
            ->where('is_primary', 1)
            ->get()->getRowArray();

        $lostImagePath = $lostPrimaryImage ? $lostPrimaryImage['image_path'] : null;

        $lostNameTokens = $this->parseToCleanKeywords($lostItem['item_name']);
        $lostDescTokens = $this->parseToCleanKeywords($lostItem['description']);
        $lostLocation   = strtolower(trim($lostItem['location_found']));

        $shortlistedCandidates = [];

        // LAPIS 2: PERHITUNGAN BOBOT TEKS DI MEMORI PHP
        foreach ($kandidatFound as $foundItem) {
            $dbScore = 0;
            $foundLocation = strtolower(trim($foundItem['location_found']));
            if ($lostLocation === $foundLocation) {
                $dbScore += 20;
            } elseif (!empty($lostLocation) && str_contains($foundLocation, $lostLocation)) {
                $dbScore += 10; 
            }

            $foundNameClean = strtolower($foundItem['item_name']);
            $nameMatches = 0;
            foreach ($lostNameTokens as $token) {
                if (preg_match("/\b" . preg_quote($token, '/') . "\b/i", $foundNameClean)) {
                    $nameMatches += 5; 
                }
            }
            $dbScore += min($nameMatches, 15); 

            $foundDescClean = strtolower($foundItem['description']);
            $descMatches = 0;
            foreach ($lostDescTokens as $token) {
                if (preg_match("/\b" . preg_quote($token, '/') . "\b/i", $foundDescClean)) {
                    $descMatches += 3; 
                }
            }
            $dbScore += min($descMatches, 15); 

            if ($dbScore >= 15) {
                $foundItem['computed_db_score'] = $dbScore;
                $shortlistedCandidates[] = $foundItem;
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

        // LAPIS 3: DEEP VISUAL ANALYSIS
        if ($lostImagePath && isset($bestMatch['found_primary_image'])) {
            $geminiService = new \App\Libraries\GeminiService();
            $aiResult = $geminiService->compareSingleImageWithVision(
                $lostImagePath, 
                $bestMatch['found_primary_image']
            );
            
            $visualScore = isset($aiResult['visual_score']) ? (int)$aiResult['visual_score'] : 0;
            $dbScore     = $bestMatch['computed_db_score'];
            $totalScore  = $dbScore + $visualScore;

            if ($totalScore >= 80) {
                $db->table('g_matches')->insert([
                    'lost_ticket'      => $lostItem['ticket_number'],
                    'found_ticket'     => $bestMatch['ticket_number'],
                    'confidence_score' => $totalScore,
                    'ai_reason'        => ($aiResult['reason'] ?? 'Cocok') . " [Kalkulasi: Teks {$dbScore}/50 + Visual AI {$visualScore}/50]",
                    'timestamp'        => date('Y-m-d H:i:s')
                ]);

                $db->table('g_item_discoveries')->where('id', $lostItem['id'])->update(['status' => 'SECURED']);
                $db->table('g_item_discoveries')->where('id', $bestMatch['id'])->update(['status' => 'SECURED']);
                $matchesInsertedCount++;
            }
        }

        return $matchesInsertedCount;
    }

    private function parseToCleanKeywords($text)
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
        return array_slice(array_unique($cleanKeywords), 0, 5);
    }
}
