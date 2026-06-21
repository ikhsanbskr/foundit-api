<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\Database\Config;

class OfficerController extends ResourceController
{
    protected $db;

    public function __construct()
    {
        $this->db = Config::connect();
    }

    // Auth Middleware to ensure only officers can access
    private function checkOfficerAuth()
    {
        $header = $this->request->getHeaderLine('Authorization');
        $token = null;

        if (!empty($header)) {
            if (preg_match('/Bearer\s(\S+)/', $header, $matches)) {
                $token = $matches[1];
            }
        }

        if (!$token) {
            return false;
        }

        $userModel = new \App\Models\UserModel();
        $user = $userModel->where('api_token', $token)->first();

        if (!$user) {
            return false;
        }
        
        return $user;
    }

    public function getDashboardStats()
    {
        $user = $this->checkOfficerAuth();
        if (!$user) {
            return $this->failUnauthorized('Akses ditolak: Token tidak valid.');
        }

        try {
            $builder = $this->db->table('g_item_discoveries');
            
            // Total Active Found items (REPORTED or SECURED)
            $activeFound = $builder->where('report_type', 'FOUND')
                                   ->whereIn('status', ['REPORTED', 'SECURED'])
                                   ->countAllResults();
                                   
            // Total Active Lost items (REPORTED)
            $builder->resetQuery();
            $activeLost = $builder->where('report_type', 'LOST')
                                  ->whereIn('status', ['REPORTED'])
                                  ->countAllResults();
            
            // Pending Verification (REPORTED Found items waiting to be SECURED)
            $builder->resetQuery();
            $pendingVerify = $builder->where('report_type', 'FOUND')
                                     ->where('status', 'REPORTED')
                                     ->countAllResults();
                                     
            // Resolved today
            $builder->resetQuery();
            $resolvedToday = $builder->where('status', 'RESOLVED')
                                     ->where('DATE(created_at)', date('Y-m-d'))
                                     ->countAllResults();

            // Recent activity
            $recent = $this->db->table('g_item_discoveries d')
                              ->select('d.id, d.ticket_number, d.report_type, d.item_name, d.status, d.created_at, u.fullname')
                              ->join('s_users u', 'u.id = d.user_id', 'left')
                              ->orderBy('d.created_at', 'DESC')
                              ->limit(5)
                              ->get()->getResultArray();
                              
            $formattedRecent = [];
            foreach ($recent as $r) {
                // Calculate time ago
                $timeAgo = $this->timeElapsedString($r['created_at']);
                
                $formattedRecent[] = [
                    'id' => $r['ticket_number'] ?? $r['id'],
                    'type' => $r['report_type'] === 'FOUND' ? 'TEMUAN' : 'KEHILANGAN',
                    'title' => strtoupper($r['item_name']),
                    'reporter' => $r['fullname'] ? explode(' ', $r['fullname'])[0] : 'Unknown',
                    'status' => strtoupper($r['status']),
                    'time' => $timeAgo
                ];
            }

            // Overdue items
            $overdueRaw = $this->db->table('g_item_discoveries d')
                                  ->select('d.id, d.ticket_number, d.item_name, d.created_at, u.fullname, u.phone_number')
                                  ->join('s_users u', 'u.id = d.user_id', 'left')
                                  ->where('d.report_type', 'FOUND')
                                  ->where('d.status', 'REPORTED')
                                  ->where('d.created_at <', date('Y-m-d H:i:s', strtotime('-3 days')))
                                  ->orderBy('d.created_at', 'ASC')
                                  ->get()->getResultArray();

            $overdueItems = [];
            foreach ($overdueRaw as $o) {
                $daysDiff = floor((time() - strtotime($o['created_at'])) / (60 * 60 * 24));
                $badge = '> 3 Hari';
                if ($daysDiff > 14) $badge = '> 14 Hari';
                elseif ($daysDiff > 7) $badge = '> 7 Hari';
                
                // Format phone number to WhatsApp format
                $phone = $o['phone_number'];
                if ($phone) {
                    $phone = preg_replace('/[^0-9]/', '', $phone);
                    if (str_starts_with($phone, '0')) {
                        $phone = '62' . substr($phone, 1);
                    }
                }

                $overdueItems[] = [
                    'id' => $o['ticket_number'] ?? $o['id'],
                    'title' => strtoupper($o['item_name']),
                    'reporter' => $o['fullname'] ?? 'Unknown',
                    'phone' => $phone,
                    'days_overdue' => $daysDiff,
                    'badge' => $badge
                ];
            }

            return $this->respond([
                'status' => 200,
                'message' => 'Success',
                'data' => [
                    'stats' => [
                        'activeFound' => $activeFound,
                        'activeLost' => $activeLost,
                        'pendingVerify' => $pendingVerify,
                        'resolvedToday' => $resolvedToday
                    ],
                    'recentActivity' => $formattedRecent,
                    'overdueItems' => $overdueItems
                ]
            ]);
        } catch (\Exception $e) {
            return $this->failServerError('Gagal memuat dashboard: ' . $e->getMessage());
        }
    }
    
    public function getInventory()
    {
        $user = $this->checkOfficerAuth();
        if (!$user) {
            return $this->failUnauthorized('Akses ditolak: Token tidak valid.');
        }

        try {
            $builder = $this->db->table('g_item_discoveries d');
            $builder->select('d.id, d.ticket_number, d.item_name, d.description, d.status, d.created_at, d.location_found, d.verification_description, d.bounty_amount, d.event_time, u.username, u.fullname, u.phone_number, c.category_name, cd.detail_name as category_detail_name, d.report_type');
            $builder->join('s_users u', 'u.id = d.user_id', 'left');
            $builder->join('gm_category c', 'c.id = d.category_id', 'left');
            $builder->join('gm_category_detail cd', 'cd.id = d.category_detail_id', 'left');

            // Apply Filters if any
            $keyword = $this->request->getGet('keyword');
            $type = $this->request->getGet('type'); // FOUND or LOST
            $status = $this->request->getGet('status');

            if (!empty($keyword)) {
                $builder->groupStart();
                $builder->like('d.item_name', $keyword);
                $builder->orLike('d.ticket_number', $keyword);
                $builder->orLike('u.fullname', $keyword);
                $builder->groupEnd();
            }
            if (!empty($type)) {
                $builder->where('d.report_type', strtoupper($type));
            }
            if (!empty($status)) {
                $builder->where('d.status', strtoupper($status));
            }

            $builder->orderBy('d.created_at', 'DESC');
            $reports = $builder->get()->getResultArray();

            $formattedReports = [];
            foreach ($reports as $report) {
                // Get all images
                $imagesData = $this->db->table('g_item_images')
                                  ->where('item_id', $report['id'])
                                  ->orderBy('is_primary', 'DESC')
                                  ->get()->getResultArray();
                $images = [];
                foreach ($imagesData as $img) {
                    $images[] = '/' . ltrim($img['image_path'], '/');
                }

                $formattedReports[] = [
                    'id' => $report['id'],
                    'ticket_number' => $report['ticket_number'] ?? null,
                    'type' => $report['report_type'] === 'FOUND' ? 'TEMUAN' : 'KEHILANGAN',
                    'reporter' => $report['fullname'] ?? $report['username'],
                    'reporter_display' => ($report['fullname'] ?? $report['username']) . ' • ' . $report['username'],
                    'username' => $report['username'],
                    'phone_number' => $report['phone_number'],
                    'date' => date('d M Y H:i', strtotime($report['created_at'])),
                    'event_time' => $report['event_time'] ? date('d M Y H:i', strtotime($report['event_time'])) : null,
                    'location' => strtoupper($report['location_found']),
                    'status' => strtoupper($report['status']),
                    'category' => strtoupper($report['category_name']),
                    'category_detail' => strtoupper($report['category_detail_name'] ?? '-'),
                    'title' => strtoupper($report['item_name']),
                    'description' => $report['description'],
                    'verification_description' => $report['verification_description'],
                    'bounty_amount' => $report['bounty_amount'],
                    'image' => count($images) > 0 ? $images[0] : null,
                    'images' => $images
                ];
            }

            return $this->respond([
                'status' => 200,
                'message' => 'Success',
                'data' => $formattedReports
            ]);

        } catch (\Exception $e) {
            return $this->failServerError('Gagal memuat inventory: ' . $e->getMessage());
        }
    }

    public function compareClaim()
    {
        $user = $this->checkOfficerAuth();
        if (!$user) {
            return $this->failUnauthorized('Akses ditolak: Token tidak valid.');
        }

        $foundTicket = $this->request->getGet('found_ticket');
        $lostTicket = $this->request->getGet('lost_ticket');

        if (empty($foundTicket) || empty($lostTicket)) {
            return $this->fail('Parameter ticket tidak lengkap.');
        }

        try {
            $foundItem = $this->db->table('g_item_discoveries d')
                                  ->select('d.*, u.fullname as reporter_name, u.username as reporter_username, u.phone_number, u.email, c.category_name, cd.detail_name, bt.bank_name')
                                  ->join('s_users u', 'u.id = d.user_id', 'left')
                                  ->join('gm_category c', 'c.id = d.category_id', 'left')
                                  ->join('gm_category_detail cd', 'cd.id = d.category_detail_id', 'left')
                                  ->join('gm_bank_type bt', 'bt.id = d.bank_id', 'left')
                                  ->where('d.ticket_number', $foundTicket)
                                  ->where('d.report_type', 'FOUND')
                                  ->get()->getRowArray();

            $lostItem = $this->db->table('g_item_discoveries d')
                                 ->select('d.*, u.fullname as reporter_name, u.username as reporter_username, u.phone_number, u.email, c.category_name, cd.detail_name, bt.bank_name')
                                 ->join('s_users u', 'u.id = d.user_id', 'left')
                                 ->join('gm_category c', 'c.id = d.category_id', 'left')
                                 ->join('gm_category_detail cd', 'cd.id = d.category_detail_id', 'left')
                                 ->join('gm_bank_type bt', 'bt.id = d.bank_id', 'left')
                                 ->where('d.ticket_number', $lostTicket)
                                 ->where('d.report_type', 'LOST')
                                 ->get()->getRowArray();

            if (!$foundItem) return $this->failNotFound('Barang temuan dengan tiket tersebut tidak ditemukan.');
            if (!$lostItem) return $this->failNotFound('Laporan kehilangan dengan tiket tersebut tidak ditemukan.');

            // Get images
            $foundImagesData = $this->db->table('g_item_images')->where('item_id', $foundItem['id'])->orderBy('is_primary', 'DESC')->get()->getResultArray();
            $lostImagesData = $this->db->table('g_item_images')->where('item_id', $lostItem['id'])->orderBy('is_primary', 'DESC')->get()->getResultArray();

            $foundImages = [];
            foreach ($foundImagesData as $img) {
                $foundImages[] = '/' . ltrim($img['image_path'], '/');
            }
            $lostImages = [];
            foreach ($lostImagesData as $img) {
                $lostImages[] = '/' . ltrim($img['image_path'], '/');
            }

            $foundItem['images'] = $foundImages;
            $lostItem['images'] = $lostImages;
            $foundItem['image'] = count($foundImages) > 0 ? $foundImages[0] : null;
            $lostItem['image'] = count($lostImages) > 0 ? $lostImages[0] : null;
            
            // Format some fields for frontend convenience
            $foundItem['date'] = date('d M Y H:i', strtotime($foundItem['created_at']));
            $lostItem['date'] = date('d M Y H:i', strtotime($lostItem['created_at']));
            if($foundItem['event_time']) $foundItem['event_time'] = date('d M Y H:i', strtotime($foundItem['event_time']));
            if($lostItem['event_time']) $lostItem['event_time'] = date('d M Y H:i', strtotime($lostItem['event_time']));
            $foundItem['reporter_display'] = ($foundItem['reporter_name'] ?? $foundItem['reporter_username']) . ' • ' . $foundItem['reporter_username'];
            $lostItem['reporter_display'] = ($lostItem['reporter_name'] ?? $lostItem['reporter_username']) . ' • ' . $lostItem['reporter_username'];

            // Get AI match data if exists
            $matchData = $this->db->table('g_matches')
                                  ->groupStart()
                                      ->where('found_ticket', $foundTicket)
                                      ->where('lost_ticket', $lostTicket)
                                  ->groupEnd()
                                  ->orGroupStart()
                                      ->where('found_ticket', $lostTicket)
                                      ->where('lost_ticket', $foundTicket)
                                  ->groupEnd()
                                  ->orderBy('id', 'DESC')
                                  ->get()->getRowArray();

            return $this->respond([
                'status' => 200,
                'message' => 'Success',
                'data' => [
                    'found' => $foundItem,
                    'lost' => $lostItem,
                    'ai_match' => $matchData ? [
                        'score' => $matchData['confidence_score'],
                        'reason' => $matchData['ai_reason']
                    ] : null
                ]
            ]);
        } catch (\Exception $e) {
            return $this->failServerError('Gagal melakukan komparasi: ' . $e->getMessage());
        }
    }

    public function resolveClaim()
    {
        $user = $this->checkOfficerAuth();
        if (!$user) {
            return $this->failUnauthorized('Akses ditolak: Token tidak valid.');
        }

        $foundTicket = $this->request->getJsonVar('found_ticket');
        $lostTicket = $this->request->getJsonVar('lost_ticket');

        if (empty($foundTicket) || empty($lostTicket)) {
            return $this->fail('Parameter ticket tidak lengkap.');
        }

        try {
            $this->db->transStart();

            $this->db->table('g_item_discoveries')->where('ticket_number', $foundTicket)->update(['status' => 'RESOLVED']);
            $this->db->table('g_item_discoveries')->where('ticket_number', $lostTicket)->update(['status' => 'RESOLVED']);

            // Insert to matches if not exists
            $existingMatch = $this->db->table('g_matches')
                                      ->where('found_ticket', $foundTicket)
                                      ->where('lost_ticket', $lostTicket)
                                      ->countAllResults();
            if ($existingMatch === 0) {
                $this->db->table('g_matches')->insert([
                    'found_ticket' => $foundTicket,
                    'lost_ticket' => $lostTicket,
                    'confidence_score' => 100, // Manual verify is 100%
                    'ai_reason' => 'Verifikasi manual oleh petugas ' . $user['fullname'],
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            }

            $this->db->transComplete();

            if ($this->db->transStatus() === FALSE) {
                return $this->failServerError('Transaksi database gagal.');
            }

            // --- Send WhatsApp Notifications ---
            try {
                // Get Found Item Details (Finder)
                $foundItem = $this->db->table('g_item_discoveries d')
                    ->select('d.item_name, u.phone_number, u.fullname, d.account_number, bt.bank_name')
                    ->join('s_users u', 'u.id = d.user_id', 'left')
                    ->join('gm_bank_type bt', 'bt.id = d.bank_id', 'left')
                    ->where('d.ticket_number', $foundTicket)
                    ->get()->getRowArray();

                // Get Lost Item Details (Owner)
                $lostItem = $this->db->table('g_item_discoveries d')
                    ->select('d.item_name, d.bounty_amount, u.phone_number, u.fullname')
                    ->join('s_users u', 'u.id = d.user_id', 'left')
                    ->where('d.ticket_number', $lostTicket)
                    ->get()->getRowArray();

                if ($foundItem && $lostItem) {
                    $waApi = new \App\Libraries\WhatsAppApi();
                    $bounty = (float) $lostItem['bounty_amount'];

                    // Message for Finder
                    if (!empty($foundItem['phone_number'])) {
                        $msgFinder = "Halo {$foundItem['fullname']},\n\nTerima kasih! Barang temuan Anda *{$foundItem['item_name']}* telah berhasil dikembalikan ke pemilik aslinya ({$lostItem['fullname']}).\n";
                        if ($bounty > 0) {
                            $msgFinder .= "\nAnda mendapatkan imbalan sebesar *Rp " . number_format($bounty, 0, ',', '.') . "* dari pemilik barang. Pemilik akan mengirimkannya ke rekening Anda.\nAnda juga dapat menghubungi pemilik di nomor WA: https://wa.me/" . preg_replace('/^0/', '62', preg_replace('/\D/', '', $lostItem['phone_number']));
                        }
                        $msgFinder .= "\n\n_Pesan otomatis dari Sistem FoundIT._";
                        $waApi->sendMessage($foundItem['phone_number'], $msgFinder);
                    }

                    // Message for Owner
                    if (!empty($lostItem['phone_number'])) {
                        $msgOwner = "Halo {$lostItem['fullname']},\n\n Laporan kehilangan Anda untuk barang *{$lostItem['item_name']}* telah diselesaikan dan barang telah dikembalikan kepada Anda.\n";
                        if ($bounty > 0) {
                            $msgOwner .= "\nSesuai janji imbalan Anda (Rp " . number_format($bounty, 0, ',', '.') . "), mohon segera kirimkan imbalan tersebut ke rekening penemu:\nBank: *{$foundItem['bank_name']}*\nNo. Rekening: *{$foundItem['account_number']}*\nAtas Nama: *{$foundItem['fullname']}*\n\nAnda dapat menghubungi penemu di nomor WA: https://wa.me/" . preg_replace('/^0/', '62', preg_replace('/\D/', '', $foundItem['phone_number']));
                        }
                        $msgOwner .= "\n\n_Pesan otomatis dari Sistem FoundIT._";
                        $waApi->sendMessage($lostItem['phone_number'], $msgOwner);
                    }
                }
            } catch (\Exception $waErr) {
                log_message('error', 'Gagal mengirim WA notifikasi resolveClaim: ' . $waErr->getMessage());
            }
            // -----------------------------------

            return $this->respond(['status' => 200, 'message' => 'Klaim berhasil diselesaikan dan barang dikembalikan.']);
        } catch (\Exception $e) {
            return $this->failServerError('Gagal menyelesaikan klaim: ' . $e->getMessage());
        }
    }
    
    public function updateStatus($id)
    {
        $user = $this->checkOfficerAuth();
        if (!$user) {
            return $this->failUnauthorized('Akses ditolak: Token tidak valid.');
        }
        
        $status = $this->request->getJsonVar('status');
        if (empty($status) || !in_array(strtoupper($status), ['REPORTED', 'SECURED', 'RESOLVED'])) {
            return $this->fail('Status tidak valid.');
        }

        try {
            $this->db->table('g_item_discoveries')
                     ->where('id', $id)
                     ->update(['status' => strtoupper($status)]);
                     
            return $this->respond([
                'status' => 200,
                'message' => 'Status berhasil diubah menjadi ' . strtoupper($status)
            ]);
        } catch (\Exception $e) {
            return $this->failServerError('Gagal update status: ' . $e->getMessage());
        }
    }

    public function secureItem($id)
    {
        $user = $this->checkOfficerAuth();
        if (!$user) {
            return $this->failUnauthorized('Akses ditolak: Token tidak valid.');
        }

        try {
            $foundItem = $this->db->table('g_item_discoveries')->where('id', $id)->get()->getRowArray();
            if (!$foundItem) {
                return $this->failNotFound('Barang temuan tidak ditemukan.');
            }

            if ($foundItem['report_type'] !== 'FOUND' || $foundItem['status'] !== 'REPORTED') {
                return $this->fail('Hanya barang temuan dengan status REPORTED yang bisa diamankan.');
            }

            $this->db->transStart();
            
            // 1. Update status ke SECURED
            $this->db->table('g_item_discoveries')->where('id', $id)->update(['status' => 'SECURED']);

            $this->db->transComplete();

            if ($this->db->transStatus() === FALSE) {
                return $this->failServerError('Gagal mengamankan barang.');
            }

            // 2. Jalankan Matching Engine Reverse (FOUND mencari LOST)
            // Ini berjalan setelah transaksi selesai agar jika matching lama, tidak melock database.
            $matchingEngine = new \App\Libraries\MatchingEngine();
            $matchCount = $matchingEngine->matchFoundToLost($foundItem);

            return $this->respond([
                'status' => 200,
                'message' => 'Barang berhasil diamankan.',
                'matches_found' => $matchCount
            ]);

        } catch (\Exception $e) {
            return $this->failServerError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    private function timeElapsedString($datetime, $full = false) {
        $now = new \DateTime;
        $ago = new \DateTime($datetime);
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = array(
            'y' => 'tahun',
            'm' => 'bulan',
            'w' => 'minggu',
            'd' => 'hari',
            'h' => 'jam',
            'i' => 'mnt',
            's' => 'dtk',
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v;
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' lalu' : 'baru saja';
    }
}
