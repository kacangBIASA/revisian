<?php
// app/controllers/AuthController.php

class AuthController extends Controller
{
    private AuthService $auth;

    public function __construct()
    {
        $this->auth = new AuthService();
    }

    public function showLogin()
    {
        return View::render('auth/login', [
            'title' => 'Login Owner - QueueNow',
            'error' => Session::flash('error'),
            'success' => Session::flash('success'),
        ], 'layouts/guest');
    }

    public function login()
    {
        if (!CSRF::verify($_POST['_csrf'] ?? null)) {
            Session::flash('error', 'CSRF token tidak valid.');
            redirect('/login');
        }

        $email = trim((string)($_POST['email'] ?? ''));
        $pass  = (string)($_POST['password'] ?? '');

        if ($email === '' || $pass === '') {
            Session::flash('error', 'Email dan password wajib diisi.');
            redirect('/login');
        }

        $ok = $this->auth->loginWithPassword($email, $pass);
        if (!$ok) {
            Session::flash('error', 'Email atau password salah.');
            redirect('/login');
        }

        redirect('/dashboard');
    }

    public function logout()
    {
        $this->auth->logout();
        Session::flash('success', 'Berhasil logout.');
        redirect('/');
    }

    // ===== Google OAuth (tanpa composer) =====
    public function googleRedirect()
    {
        $clientId = config('google.client_id');
        $redirect = config('google.redirect_uri');

        $state = bin2hex(random_bytes(16));
        Session::set('google_oauth_state', $state);

        $params = http_build_query([
            'response_type' => 'code',
            'client_id'     => $clientId,
            'redirect_uri'  => $redirect,
            'scope'         => 'openid email profile',
            'state'         => $state,
            'prompt'        => 'select_account',
        ]);

        header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
        exit;
    }

    public function googleCallback()
    {
        $state = (string)($_GET['state'] ?? '');
        $saved = (string)Session::get('google_oauth_state', '');
        Session::forget('google_oauth_state');

        if ($state === '' || $saved === '' || !hash_equals($saved, $state)) {
            Session::flash('error', 'State OAuth tidak valid.');
            redirect('/login');
        }

        if (!empty($_GET['error'])) {
            Session::flash('error', 'Login Google dibatalkan.');
            redirect('/login');
        }

        $code = (string)($_GET['code'] ?? '');
        if ($code === '') {
            Session::flash('error', 'Kode OAuth tidak ditemukan.');
            redirect('/login');
        }

        $token = $this->googleExchangeToken($code);
        if (!$token || empty($token['access_token'])) {
            Session::flash('error', 'Gagal mengambil token Google.');
            redirect('/login');
        }

        $user = $this->googleFetchUserInfo($token['access_token']);
        if (!$user || empty($user['email'])) {
            Session::flash('error', 'Gagal mengambil profil Google.');
            redirect('/login');
        }

        $email = strtolower(trim((string)$user['email']));

        // ✅ CEK: owner sudah ada atau belum
        $exists = DB::fetchOne("SELECT id FROM owners WHERE email = ? LIMIT 1", [$email]);

        if (!$exists) {
            // ✅ AKUN GOOGLE BARU → wajib lengkapi form register
            Session::set('google_pending', [
                'email'   => $email,
                'name'    => $user['name'] ?? '',
                'id'      => $user['id'] ?? '',
                'picture' => $user['picture'] ?? '',
            ]);

            Session::flash('success', 'Login Google berhasil. Silakan lengkapi data bisnis untuk membuat akun.');
            redirect('/register');
        }

        // ✅ SUDAH ADA → login biasa
        $this->auth->loginWithGoogle([
            'id'      => $user['id'] ?? null,
            'email'   => $email,
            'name'    => $user['name'] ?? null,
            'picture' => $user['picture'] ?? null,
        ]);

        redirect('/dashboard');
    }

    private function googleExchangeToken(string $code): ?array
    {
        $url = 'https://oauth2.googleapis.com/token';

        $post = http_build_query([
            'code'          => $code,
            'client_id'     => config('google.client_id'),
            'client_secret' => config('google.client_secret'),
            'redirect_uri'  => config('google.redirect_uri'),
            'grant_type'    => 'authorization_code',
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $post,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 15,
        ]);

        $res = curl_exec($ch);
        curl_close($ch);

        if (!$res) return null;
        $json = json_decode($res, true);
        return is_array($json) ? $json : null;
    }

    private function googleFetchUserInfo(string $accessToken): ?array
    {
        $url = 'https://www.googleapis.com/oauth2/v2/userinfo';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
            CURLOPT_TIMEOUT        => 15,
        ]);

        $res = curl_exec($ch);
        curl_close($ch);

        if (!$res) return null;
        $json = json_decode($res, true);
        return is_array($json) ? $json : null;
    }

    public function showRegister()
    {
        $google = Session::get('google_pending'); // bisa null

        // kalau ada google_pending, prefilling email otomatis
        $old = Session::flash('old') ? json_decode(Session::flash('old'), true) : [];
        if (is_array($google) && !empty($google['email'])) {
            $old['email'] = $google['email'];
        }

        return View::render('auth/register', [
            'title'   => 'Daftar Owner - QueueNow',
            'error'   => Session::flash('error'),
            'success' => Session::flash('success'),
            'old'     => $old,
            'google'  => $google, // ⬅️ kirim ke view
        ], 'layouts/guest');
    }

    public function register()
    {
        if (!CSRF::verify($_POST['_csrf'] ?? null)) {
            Session::flash('error', 'CSRF token tidak valid.');
            redirect('/register');
        }

        $businessName = trim((string)($_POST['business_name'] ?? ''));
        $category     = trim((string)($_POST['business_category'] ?? ''));
        $phone        = trim((string)($_POST['phone'] ?? ''));
        $email        = strtolower(trim((string)($_POST['email'] ?? '')));
        $pass         = (string)($_POST['password'] ?? '');
        $pass2        = (string)($_POST['password_confirm'] ?? '');

        // simpan old input untuk repopulate form
        Session::flash('old', json_encode([
            'business_name' => $businessName,
            'business_category' => $category,
            'phone' => $phone,
            'email' => $email,
        ]));

        // ✅ Deteksi mode google-complete
        $google = Session::get('google_pending');
        $isGoogleComplete = is_array($google)
            && !empty($google['email'])
            && strtolower((string)$google['email']) === $email;

        // Validasi dasar
        if ($businessName === '' || $email === '') {
            Session::flash('error', 'Nama bisnis dan email wajib diisi.');
            redirect('/register');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Format email tidak valid.');
            redirect('/register');
        }

        // ✅ Kalau Google, password dibuat otomatis di server (user tidak perlu isi)
        if ($isGoogleComplete) {
            $pass = bin2hex(random_bytes(16)); // random
            $pass2 = $pass;
        } else {
            // register normal (LOCAL)
            if ($pass === '' || $pass2 === '') {
                Session::flash('error', 'Password dan konfirmasi password wajib diisi.');
                redirect('/register');
            }
            if (strlen($pass) < 8) {
                Session::flash('error', 'Password minimal 8 karakter.');
                redirect('/register');
            }
            if ($pass !== $pass2) {
                Session::flash('error', 'Konfirmasi password tidak sama.');
                redirect('/register');
            }
        }

        // cek email unik
        $exists = DB::fetchOne("SELECT id FROM owners WHERE email = ? LIMIT 1", [$email]);
        if ($exists) {
            Session::flash('error', 'Email sudah terdaftar. Silakan login.');
            redirect('/login');
        }

        $hash = password_hash($pass, PASSWORD_BCRYPT);

        $authProvider = $isGoogleComplete ? 'GOOGLE' : 'LOCAL';

        // create owner (default FREE)
        $ownerId = DB::exec(
            "INSERT INTO owners (email, password_hash, phone, business_name, business_category, plan, auth_provider)
         VALUES (?, ?, ?, ?, ?, 'FREE', ?)",
            [$email, $hash, ($phone !== '' ? $phone : null), $businessName, ($category !== '' ? $category : null), $authProvider]
        );

        // create business pertama
        DB::exec(
            "INSERT INTO businesses (owner_id, name, category, phone) VALUES (?, ?, ?, ?)",
            [$ownerId, $businessName, ($category !== '' ? $category : null), ($phone !== '' ? $phone : null)]
        );

        // login otomatis
        Session::set('owner', [
            'id'    => (int)$ownerId,
            'email' => $email,
            'plan'  => 'FREE',
        ]);

        // ✅ bersihkan google pending kalau tadi register via google
        if ($isGoogleComplete) {
            Session::forget('google_pending');
        }

        Session::flash('old', null);
        redirect('/dashboard');
    }
}
