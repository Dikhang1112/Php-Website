<?php
namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ClientFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Chưa đăng nhập
        if (!session()->get('auth')) {
            return redirect()->to(site_url('login'))
                ->with('error', 'Vui lòng đăng nhập');
        }

        // Đăng nhập nhưng không phải là client
        if (session()->get('role') !== 'client') {
            return redirect()->to(site_url('login'))
                ->with('error', 'Bạn không có quyền truy cập');
        }
        // Không return gì thêm => cho phép đi tiếp
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // không cần gì
    }
}
