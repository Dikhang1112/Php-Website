<?php
namespace App\Controllers;
use App\Repositories\UserRepository;
use App\Services\UserService;

class ActionController extends BaseController
{
    public function showLogin(): string
    {
        return view('Login');
    }

    public function submit()
    {
        $email = (string) $this->request->getPost('email');
        $password = (string) $this->request->getPost('password');

        $repo = new UserRepository();
        $svc = new UserService($repo);
        $res = $svc->login($email, $password);

        if (!empty($res['ok'])) {
            $u = $res['user'] ?? [];
            $role = strtolower($u['role'] ?? 'client'); // chuẩn hoá role

            // Set session cho TẤT CẢ user đăng nhập thành công (client/admin...)
            session()->regenerate(); // chống session fixation
            session()->set([
                'auth' => true,
                'user_id' => $u['id'] ?? '',
                'email' => $u['email'] ?? '',
                'fullName' => $u['fullName'] ?? ($u['full_name'] ?? ''), // tuỳ field
                'role' => $role,
                'logged_in' => time(),
            ]);

            // Điều hướng theo role
            $redirectTo = (string) ($this->request->getPost('redirect_to') ?? ($role === 'admin' ? '/users' : '/home'));
            return redirect()->to($redirectTo)->with('success', 'Đăng nhập thành công!');
        }

        // Thất bại
        $msg = $res['error'] ?? 'Email hoặc mật khẩu không đúng';
        return redirect()->back()->withInput()->with('error', $msg);
    }


    public function logout()
    {
        session()->destroy();
        return redirect()->to('/login')->with('success', 'Đăng xuất thành công');
    }

}
?>