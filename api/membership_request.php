<?php
require_once '../config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $planId = 1; // Default Plan ID = 1 (Premium)
    
    // 1. ตรวจสอบว่ามีคำขอ Pending อยู่ไหม (ถ้ามีให้ไปจ่ายเงินเก่าเลย)
    $check = $pdo->prepare("SELECT id FROM user_membership WHERE user_id = ? AND payment_status = 'pending'");
    $check->execute([$_SESSION['user_id']]);
    $existing = $check->fetch();
    
    if ($existing) {
        redirect('/pages/pay_membership.php?id=' . $existing['id']);
    }

    // 2. สร้างคำขอใหม่ (Insert into user_membership)
    $stmt = $pdo->prepare("INSERT INTO user_membership (user_id, plan_id, payment_status) VALUES (?, ?, 'pending')");
    $stmt->execute([$_SESSION['user_id'], $planId]);
    $newId = $pdo->lastInsertId();

    // 3. Redirect ไปหน้าจ่ายเงินพร้อม ID
    redirect('/pages/pay_membership.php?id=' . $newId);
}

redirect('/pages/membership.php');
?>