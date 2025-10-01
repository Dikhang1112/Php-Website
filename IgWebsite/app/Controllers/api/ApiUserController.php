<?php
namespace App\Controllers\Api;
use App\Controllers\BaseController;
use CodeIgniter\Controller;

class ApiUserController extends BaseController
{
    public function index()
    {
        $db = service('mongoDB');
        echo $db->getDatabaseName();
        $users = $db->selectCollection('users')
            ->find([], ['sort' => ['createdAt' => -1]])
            ->toArray();

        return $this->response->setJSON([
            'count' => count($users),
            'items' => $users,
        ]);
    }
}

?>