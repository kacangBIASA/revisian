<?php
// app/routes/web.php

$router->get('/', [HomeController::class, 'landing']);

// Login
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);

// Google OAuth
$router->get('/auth/google', [AuthController::class, 'googleRedirect']);
$router->get('/auth/google/callback', [AuthController::class, 'googleCallback']);

// sementara dashboard dummy dulu:
$router->get('/dashboard', function () {
  return "Dashboard OK ✅ (nanti kita bikin tampilan dashboard)";
});

// Register
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);

$router->get('/dashboard', [DashboardController::class, 'index'], [AuthMiddleware::class]);

// Branches (Auth required)
$router->get('/branches', [BranchController::class, 'index'], [AuthMiddleware::class]);
$router->get('/branches/create', [BranchController::class, 'create'], [AuthMiddleware::class]);
$router->post('/branches', [BranchController::class, 'store'], [AuthMiddleware::class]);
$router->get('/branches/edit', [BranchController::class, 'edit'], [AuthMiddleware::class]);      // pakai ?id=1
$router->post('/branches/update', [BranchController::class, 'update'], [AuthMiddleware::class]); // pakai ?id=1
$router->post('/branches/delete', [BranchController::class, 'delete'], [AuthMiddleware::class]); // pakai ?id=1

// Public queue (scan QR / online)
$router->get('/q', [QueueController::class, 'publicPage']);
$router->get('/q/status', [QueueController::class, 'publicStatus']);
$router->post('/q/take', [QueueController::class, 'publicTake']);


// Owner manage queue
$router->get('/queues/manage', [QueueController::class, 'manage'], [AuthMiddleware::class]);
$router->post('/queues/action', [QueueController::class, 'action'], [AuthMiddleware::class]);
$router->post('/queues/call-next', [QueueController::class, 'callNext'], [AuthMiddleware::class]);


// QR Image (public)
$router->get('/qr', [QrController::class, 'show']); // /qr?token=xxxx

// History
$router->get('/history', [HistoryController::class, 'index'], [AuthMiddleware::class]);

// Pricing & Checkout
$router->get('/subscription/pricing', [SubscriptionController::class, 'pricing'], [AuthMiddleware::class]);
$router->post('/subscription/checkout', [SubscriptionController::class, 'checkout'], [AuthMiddleware::class]);
$router->get('/subscription/finish', [SubscriptionController::class, 'finish'], [AuthMiddleware::class]);

// Midtrans webhook (PUBLIC, no auth)
$router->post('/midtrans/notify', [MidtransController::class, 'notify']);

// Dashboard
$router->get('/dashboard', [DashboardController::class, 'index'], [AuthMiddleware::class]);
$router->get('/dashboard/stats', [DashboardController::class, 'stats'], [AuthMiddleware::class]);

// Reports
$router->get('/reports', [ReportController::class, 'index'], [AuthMiddleware::class]);
$router->get('/reports/excel', [ReportController::class, 'excel'], [AuthMiddleware::class]);
$router->get('/reports/pdf', [ReportController::class, 'pdf'], [AuthMiddleware::class]);
