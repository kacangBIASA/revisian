<?php
// app/services/AuthService.php

class AuthService
{
    public function loginWithPassword(string $email, string $password): bool
    {
        $owner = DB::fetchOne("SELECT * FROM owners WHERE email = ?", [$email]);
        if (!$owner) return false;

        $hash = $owner['password_hash'] ?? null;
        if (!$hash || !password_verify($password, $hash)) return false;

        $this->setOwnerSession($owner);
        return true;
    }

    public function loginWithGoogle(array $googleUser): int
    {
        // googleUser: ['id','email','name','picture']
        $email = $googleUser['email'] ?? '';
        if ($email === '') throw new RuntimeException('Email Google kosong');

        $existing = DB::fetchOne("SELECT * FROM owners WHERE email = ?", [$email]);

        if ($existing) {
            // link google_id kalau belum
            DB::exec(
                "UPDATE owners SET google_id=?, avatar=?, auth_provider='GOOGLE' WHERE id=?",
                [$googleUser['id'] ?? null, $googleUser['picture'] ?? null, $existing['id']]
            );
            $owner = DB::fetchOne("SELECT * FROM owners WHERE id=?", [$existing['id']]);
            $this->setOwnerSession($owner);
            return (int)$existing['id'];
        }

        // create owner baru (default FREE)
        $businessName = $googleUser['name'] ? ($googleUser['name'] . ' Business') : 'My Business';
        $businessCat  = 'Umum';

        $ownerId = DB::exec(
            "INSERT INTO owners (email, google_id, avatar, auth_provider, password_hash, phone, business_name, business_category, plan)
             VALUES (?, ?, ?, 'GOOGLE', NULL, NULL, ?, ?, 'FREE')",
            [$email, $googleUser['id'] ?? null, $googleUser['picture'] ?? null, $businessName, $businessCat]
        );

        // auto buat business pertama
        DB::exec(
            "INSERT INTO businesses (owner_id, name, category, phone) VALUES (?, ?, ?, NULL)",
            [$ownerId, $businessName, $businessCat]
        );

        $owner = DB::fetchOne("SELECT * FROM owners WHERE id=?", [$ownerId]);
        $this->setOwnerSession($owner);
        return $ownerId;
    }

    private function setOwnerSession(array $owner): void
    {
        Session::set('owner', [
            'id'    => (int)$owner['id'],
            'email' => $owner['email'],
            'plan'  => $owner['plan'] ?? 'FREE',
        ]);
    }

    public function logout(): void
    {
        Session::forget('owner');
    }
}
