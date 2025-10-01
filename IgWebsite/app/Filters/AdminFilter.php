<?php
namespace App\Filters;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AdminFilter implements FilterInterface
{

    public function before(RequestInterface $request, $arguments = null)
    {
        // Trước khi login
        if (!session()->get('auth')) {
            return redirect()->to('/login')
                ->with('error', 'Vui lòng đăng nhập');
        }
        //Login nhưng không phải là admin
        if (session()->get('role') !== 'admin') {
            return redirect()->to('/login')
                ->with('error', 'Bạn không có quyền truy cập');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Add your logic here
    }
}


?>