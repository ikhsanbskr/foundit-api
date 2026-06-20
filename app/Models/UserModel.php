<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
  // Mengarahkan model ke nama tabel kustom yang kamu minta
  protected $table            = 's_users';
  protected $primaryKey       = 'id';
  protected $useAutoIncrement = true;
  protected $returnType       = 'array';
  protected $useSoftDeletes   = false;

  // FITUR KEAMANAN: Kolom yang diizinkan untuk manipulasi data (mencegah Mass Assignment Vulnerability)
  protected $allowedFields    = [
    'username',
    'password',
    'fullname',
    'email',
    'role',
    'phone_number',
    'api_token',
    'status'
  ];

  // Otomatisasi manajemen waktu (Data Auditing)
  protected $useTimestamps = true;
  protected $createdField  = 'created_at';
  protected $updatedField  = 'updated_at';

  /**
   * Mengambil data user berdasarkan username tunggal.
   * Digunakan oleh AuthController untuk proses validasi login.
   * * @param string $username
   * @return array|null
   */
  public function findActiveUser(string $username)
  {
    return $this->where('username', $username)
      ->first();
  }
}
