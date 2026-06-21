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
                
                $matchingEngine = new \App\Libraries\MatchingEngine();
                $matchCount = $matchingEngine->matchLostToFound($newLostItem);

                // Send WhatsApp notification if matches are found
                if ($matchCount > 0) {
                    try {
                        $reporter = $db->table('s_users')->select('fullname, phone_number')->where('id', $newLostItem['user_id'])->get()->getRowArray();
                        if ($reporter && !empty($reporter['phone_number'])) {
                            $waApi = new \App\Libraries\WhatsAppApi();
                            $msg = "Halo {$reporter['fullname']},\n\nSistem kami menemukan *{$matchCount} potensi barang temuan* yang mungkin cocok dengan laporan kehilangan Anda (*{$newLostItem['item_name']}*).\n\nSegera cek di aplikasi Found It pada menu *Laporan Saya* untuk melihat detailnya!\n\n_Pesan otomatis dari Sistem Found It._";
                            $waApi->sendMessage($reporter['phone_number'], $msg);
                        }
                    } catch (\Exception $waErr) {
                        log_message('error', 'Gagal kirim WA notif match baru: ' . $waErr->getMessage());
                    }
                }
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
            $builder->select('d.id, d.ticket_number, d.item_name, d.description, d.status, d.created_at, d.location_found, u.username, u.fullname, c.category_name, cd.detail_name as category_detail_name, d.user_id, d.report_type');
            $builder->join('s_users u', 'u.id = d.user_id', 'left');
            $builder->join('gm_category c', 'c.id = d.category_id', 'left');
            $builder->join('gm_category_detail cd', 'cd.id = d.category_detail_id', 'left');
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
                    'category_detail' => strtoupper($report['category_detail_name'] ?? 'LAINNYA'),
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
            $builder->select('d.id, d.ticket_number, d.item_name, d.description, d.status, d.created_at, d.event_time, d.bounty_amount, d.verification_description, d.location_found, u.username, u.fullname, c.category_name, cd.detail_name as category_detail_name, d.user_id, d.report_type');
            $builder->join('s_users u', 'u.id = d.user_id', 'left');
            $builder->join('gm_category c', 'c.id = d.category_id', 'left');
            $builder->join('gm_category_detail cd', 'cd.id = d.category_detail_id', 'left');
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
                    'type' => $report['report_type'] === 'FOUND' ? 'TEMUAN' : 'KEHILANGAN',
                    'headerDark' => $report['user_id'] % 2 == 0,
                    'avatar' => '/profile/user_image.png',
                    'initials' => strtoupper(substr($report['username'] ?? 'U', 0, 2)),
                    'username' => ($report['username'] ?? 'UNKNOWN') . ' • ' . $fullname,
                    'meta' => date('d M Y // H:i', strtotime($report['created_at'])),
                    'location' => strtoupper($report['location_found']),
                    'status' => strtoupper($report['status']),
                    'tagDark' => $report['user_id'] % 2 == 1,
                    'category' => strtoupper($report['category_name']),
                    'category_detail' => strtoupper($report['category_detail_name'] ?? 'LAINNYA'),
                    'title' => $report['item_name'],
                    'description' => $report['description'],
                    'event_time' => $report['event_time'] ? date('d M Y H:i', strtotime($report['event_time'])) : null,
                    'bounty_amount' => $report['bounty_amount'],
                    'verification_description' => $report['verification_description'],
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

    public function getReportByTicket($ticketNumber)
    {
        try {
            $user = $this->getUserFromToken();
            if (!$user) return $this->failUnauthorized('Silakan login terlebih dahulu untuk klaim barang.');

            $db = \Config\Database::connect();
            $report = $db->table('g_item_discoveries')
                         ->where('ticket_number', $ticketNumber)
                         ->where('report_type', 'LOST')
                         ->get()->getRowArray();
                         
            if (!$report) return $this->failNotFound('Tiket laporan kehilangan tidak ditemukan.');

            if ($report['user_id'] != $user['id']) {
                return $this->failForbidden('Tiket laporan tersebut bukan milik Anda.');
            }

            $images = $this->imageModel->where('item_id', $report['id'])->findAll();
            $report['images'] = [];
            foreach ($images as $img) {
                $report['images'][] = [
                    'id' => $img['id'],
                    'url' => '/' . ltrim($img['image_path'], '/')
                ];
            }

            return $this->respond(['status' => 200, 'data' => $report]);
        } catch (\Exception $e) {
            return $this->failServerError('Gagal mencari tiket: ' . $e->getMessage());
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

    public function getMatches($ticketNumber)
    {
        try {
            $db = \Config\Database::connect();
            $matches = $db->table('g_matches')
                ->where('lost_ticket', $ticketNumber)
                ->orWhere('found_ticket', $ticketNumber)
                ->orderBy('confidence_score', 'DESC')
                ->get()->getResultArray();

            $formattedMatches = [];
            foreach ($matches as $match) {
                $otherTicket = $match['lost_ticket'] === $ticketNumber ? $match['found_ticket'] : $match['lost_ticket'];
                
                $item = $db->table('g_item_discoveries')
                    ->where('ticket_number', $otherTicket)
                    ->get()->getRowArray();
                
                if ($item) {
                    $images = $this->imageModel->where('item_id', $item['id'])->findAll();
                    $imageUrls = [];
                    foreach ($images as $img) {
                        $imageUrls[] = '/' . ltrim($img['image_path'], '/');
                    }
                    
                    $formattedMatches[] = [
                        'match_id' => $match['id'],
                        'confidence_score' => $match['confidence_score'],
                        'ai_reason' => $match['ai_reason'],
                        'timestamp' => $match['timestamp'],
                        'ticket_number' => $item['ticket_number'],
                        'item_name' => $item['item_name'],
                        'description' => $item['description'],
                        'location_found' => $item['location_found'],
                        'event_time' => $item['event_time'],
                        'images' => $imageUrls
                    ];
                }
            }

            return $this->respond([
                'status' => 200,
                'message' => 'Success',
                'data' => $formattedMatches
            ]);

        } catch (\Exception $e) {
            return $this->failServerError('Gagal memuat matches: ' . $e->getMessage());
        }
    }
}
