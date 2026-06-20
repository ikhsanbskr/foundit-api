<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\CategoryModel;
use App\Models\CategoryDetailModel;
use App\Models\BankModel;
use CodeIgniter\API\ResponseTrait;

class MasterDataController extends BaseController
{
    use ResponseTrait;

    protected $categoryModel;
    protected $categoryDetailModel;
    protected $bankModel;

    // Inisialisasi model di constructor agar hemat memori
    public function __construct()
    {
        $this->categoryModel = new CategoryModel();
        $this->categoryDetailModel = new CategoryDetailModel();
        $this->bankModel = new BankModel();
    }

    // Endpoint: GET /api/master/categories
    public function getCategories()
    {
        try {
            $data = $this->categoryModel->orderBy('id', 'ASC')->findAll();

            return $this->respond([
                'status'  => 200,
                'message' => 'Success.',
                'data'    => $data
            ], 200);

        } catch (\Exception $e) {
            return $this->failServerError('Server error context.');
        }
    }

    // Endpoint: GET /api/master/categories/details/(:num)
    public function getCategoryDetails($categoryId = null)
    {
        // Validasi parameter input
        if ($categoryId === null || !is_numeric($categoryId)) {
            return $this->failValidationErrors('Invalid identifier.');
        }

        try {
            $data = $this->categoryDetailModel->getDetailsByCategory((int)$categoryId);

            return $this->respond([
                'status'  => 200,
                'message' => 'Success.',
                'data'    => $data
            ], 200);

        } catch (\Exception $e) {
            return $this->failServerError('Server error context.');
        }
    }

    // Endpoint: GET /api/master/banks
    public function getBanks()
    {
        try {
            $data = $this->bankModel->orderBy('bank_name', 'ASC')->findAll();

            return $this->respond([
                'status'  => 200,
                'message' => 'Success.',
                'data'    => $data
            ], 200);

        } catch (\Exception $e) {
            return $this->failServerError('Server error context.');
        }
    }
}