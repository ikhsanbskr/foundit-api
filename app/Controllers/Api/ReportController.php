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

            // Validasi sederhana
            if (!$categoryId || !$categoryDetailId || !$locationFound || !$description || !$itemName) {
                return $this->failValidationErrors('Data wajib (Kategori, Nama, Lokasi, Deskripsi) belum lengkap.');
            }

            // 1. Simpan ke tabel penemuan
            $discoveryId = $this->discoveryModel->insert([
                'user_id' => $userId,
                'category_id' => $categoryId,
                'category_detail_id' => $categoryDetailId,
                'bank_id' => $bankId,
                'report_type' => 'FOUND',
                'status' => 'REPORTED',
                'item_name' => $itemName,
                'location_found' => $locationFound,
                'description' => $description,
                'verification_description' => $verificationDesc,
                'bounty_amount' => $bountyAmount,
                'account_number' => $accountNumber
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

                        // Kompresi dan konversi
                        $imageService = \Config\Services::image()
                            ->withFile($file->getTempName())
                            ->resize(1200, 1200, true, 'height')
                            ->convert(IMAGETYPE_WEBP);
                        
                        $imageService->save($uploadPath . $newName);

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

            return $this->respondCreated([
                'status' => 201,
                'message' => 'Laporan temuan berhasil dikirim dan disebarkan!',
                'data' => ['id' => $discoveryId]
            ]);

        } catch (\Exception $e) {
            return $this->failServerError('Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    public function getFoundReports()
    {
        try {
            // Kita join g_item_discoveries dengan s_users dan gm_category
            $db = \Config\Database::connect();
            $builder = $db->table('g_item_discoveries d');
            $builder->select('d.id, d.item_name, d.description, d.status, d.created_at, d.location_found, u.username, u.fullname, c.category_name, d.user_id');
            $builder->join('s_users u', 'u.id = d.user_id', 'left');
            $builder->join('gm_category c', 'c.id = d.category_id', 'left');
            $builder->where('d.report_type', 'FOUND');
            $builder->orderBy('d.created_at', 'DESC');
            
            $reports = $builder->get()->getResultArray();

            // Format data dan ambil images
            $formattedReports = [];
            foreach ($reports as $report) {
                // Get Images
                $images = $this->imageModel->where('item_id', $report['id'])->findAll();
                $imageUrls = [];
                foreach ($images as $img) {
                    $imageUrls[] = base_url($img['image_path']);
                }

                // Batasi nama maksimal 2 kata
                $fullname = $report['fullname'] ?? 'N/A';
                $nameWords = explode(' ', trim($fullname));
                if (count($nameWords) > 2) {
                    $fullname = $nameWords[0] . ' ' . $nameWords[1];
                }

                $formattedReports[] = [
                    'id' => $report['id'],
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
}
