<?php
// app/controllers/QrController.php

class QrController extends Controller
{
    public function show()
    {
        $token = trim((string)($_GET['token'] ?? ''));
        if ($token === '') {
            http_response_code(400);
            echo "token required";
            return;
        }

        // Cari cabang dari token
        $branch = DB::fetchOne("SELECT id, qr_token FROM branches WHERE qr_token=?", [$token]);
        if (!$branch) {
            http_response_code(404);
            echo "branch not found";
            return;
        }

        // Data yang di-encode di QR: URL publik ambil antrean
        $publicUrl = base_url('/q?token=' . urlencode($token));

        // Lokasi cache file PNG
        $dir = __DIR__ . '/../../public/uploads/qr';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $file = $dir . '/' . $token . '.png';

        // Kalau sudah ada cache, langsung serve
        if (is_file($file)) {
            header('Content-Type: image/png');
            header('Cache-Control: public, max-age=86400'); // 1 hari
            readfile($file);
            return;
        }

        // Generate QR (pakai layanan QR)
        // Kamu bisa ganti size sesuai kebutuhan
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=360x360&data=' . urlencode($publicUrl);

        $img = $this->httpGetBinary($qrUrl);

        if ($img) {
            @file_put_contents($file, $img);
            header('Content-Type: image/png');
            header('Cache-Control: public, max-age=86400');
            echo $img;
            return;
        }

        // Fallback kalau gagal fetch
        http_response_code(500);
        echo "failed to generate qr";
    }

    private function httpGetBinary(string $url): ?string
    {
        // Prefer cURL
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 10,
            ]);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($res !== false && $code >= 200 && $code < 300) return $res;
            return null;
        }

        // Fallback file_get_contents
        $ctx = stream_context_create([
            'http' => ['timeout' => 10]
        ]);
        $res = @file_get_contents($url, false, $ctx);
        return $res !== false ? $res : null;
    }
}
