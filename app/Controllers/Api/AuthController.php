<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\UserModel;
use CodeIgniter\API\ResponseTrait;

class AuthController extends BaseController
{
    use ResponseTrait;

    public function login()
    {
        // 1. Ambil input JSON dari React frontend
        $json = $this->request->getJSON();
        
        if (!$json || !isset($json->username) || !isset($json->password)) {
            return $this->fail('Username dan password wajib diisi.', 400);
        }

        $username = trim($json->username);
        $password = trim($json->password);

        $userModel = new UserModel();

        $user = $userModel->findActiveUser($username);

        if (!$user) {
            return $this->fail('Username atau password salah!', 401);
        }

        // Edge Case: Cek status akun apakah ditangguhkan
        if ($user['status'] === 'nonactive') {
            return $this->fail('Akun Anda ditangguhkan. Silakan hubungi admin.', 403);
        }

        // 3. Validasi Password menggunakan password_verify
        if (!password_verify($password, $user['password'])) {
            return $this->fail('Username atau password salah!', 401);
        }

        //  Generate Mock API Token (Base64)
        // Format token: ID-Role-SaltUnpam2026 yang di-encode ke Base64
        $salt = 'unpam2026';
        $rawToken = $user['id'] . '-' . $user['role'] . '-' . $salt;
        $mockToken = base64_encode($rawToken);

        // 5. Simpan token ke database s_users untuk validasi request berikutnya
        $userModel->update($user['id'], [
            'api_token' => $mockToken
        ]);

        // 6. Return response sukses berupa data JSON yang bersih untuk dikonsumsi React
        return $this->respond([
            'status'  => 200,
            'message' => 'Autentikasi Berhasil',
            'data'    => [
                'user' => [
                    'id'       => $user['id'],
                    'username' => $user['username'],
                    'fullname' => $user['fullname'],
                    'role'     => $user['role'],
                    'email'    => $user['email']
                ],
                'token' => $mockToken
            ]
        ], 200);
    }
}