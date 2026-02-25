<?php
require_once '../config.php';

// --- แก้ไขส่วนนี้: อนุญาตทั้ง User และ Admin ---
if (isLoggedIn()) {
    // ผ่านเลย ถ้าเป็น User
} elseif (isAdmin()) {
    // ผ่านเลย ถ้าเป็น Admin
} else {
    // ถ้าไม่ใช่ทั้งสอง ให้ไปหน้า Login ของ User
    redirect('/pages/login.php');
}
// ------------------------------------------

// --- LOGIC ---
 $sportId = intval($_GET['sport_id'] ?? 0);
if (!$sportId) redirect('/pages/courts.php');

// Get Sport Info
 $stmt = $pdo->prepare("SELECT * FROM sports WHERE sport_id = ?");
 $stmt->execute([$sportId]);
 $sport = $stmt->fetch();
if (!$sport) redirect('/pages/courts.php');

// Date Logic
 $selectedDate = $_GET['date'] ?? date('Y-m-d');
 $prevDate = date('Y-m-d', strtotime($selectedDate . ' -1 day'));
 $nextDate = date('Y-m-d', strtotime($selectedDate . ' +1 day'));

// Get Courts
 $stmt = $pdo->prepare("SELECT * FROM courts WHERE sport_id = ?");
 $stmt->execute([$sportId]);
 $courts = $stmt->fetchAll();

// Get Slots
 $stmt = $pdo->prepare("SELECT * FROM time_slots WHERE sport_id = ? ORDER BY start_time ASC");
 $stmt->execute([$sportId]);
 $timeSlots = $stmt->fetchAll();

// Get Equipment
 $stmt = $pdo->prepare("SELECT * FROM equipment WHERE sport_id = ? AND stock > 0");
 $stmt->execute([$sportId]);
 $equipments = $stmt->fetchAll();

// Get Bookings Map (Key: "courtId_slotId")
 $bookingsMap = [];
 $stmt = $pdo->prepare("SELECT court_id, slot_id FROM bookings WHERE booking_date = ? AND payment_status IN ('pending', 'paid')");
 $stmt->execute([$selectedDate]);
foreach($stmt->fetchAll() as $b) {
    $bookingsMap[$b['court_id'].'_'.$b['slot_id']] = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book <?= htmlspecialchars($sport['sport_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/home.css">
</head>
<body class='bodyy'>

    <!-- NAVBAR -->
    <nav class="navbar-home" id="navbar">
        <div class="navbar-container">
            <a href="index.php" class="navbar-logo">HIT THE <span>COURT</span></a>
            
            <!-- Menu with Dropdown -->
            <ul class="nav-menu">
                <!-- Courts Dropdown -->
                <li class="nav-item">
                    <a href="<?= SITE_URL ?>/pages/courts.php" class="nav-link">
                        Courts
                    </a>
                    
                </li>

                <li class="nav-item">
                    <a href="<?= SITE_URL ?>/pages/reservations.php" class="nav-link">Reservations</a>
                </li>
                <li class="nav-item">
                    <a href="<?= SITE_URL ?>/pages/reports.php" class="nav-link">Contact Us</a>
                </li>
                <li class="nav-item">
                    <a href="<?= SITE_URL ?>/pages/guidebook.php" class="nav-link">Guidebook</a>
                </li>
            </ul>
            
            <!-- User Actions -->
            <div class="nav-auth">
                <?php if (isLoggedIn()): ?>
                    <div class="user-menu">
                        <button class="user-btn">
                            <div class="user-avatar">
                                <?= strtoupper(substr($_SESSION['username'], 0, 1)) ?>
                            </div>
                            <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </button>
                        <div class="user-dropdown">
                            <div style="padding: 1rem; border-bottom: 1px solid var(--gray-200);">
                                <small style="color: var(--gray-500);">Signed in as</small>
                                <p style="font-weight: 600;"><?= htmlspecialchars($_SESSION['username']) ?></p>
                            </div>
                            <div style="padding: 0.5rem;">
                                <a href="<?= SITE_URL ?>/pages/reservations.php" class="dropdown-link">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                    My Bookings
                                </a>
                                 <a href="<?= SITE_URL ?>/pages/profile.php" class="dropdown-link">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                    My Profile
                                </a>
                                    <a href="<?= SITE_URL ?>/pages/membership.php" class="dropdown-link">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M6 3h12l3 6-9 12L3 9l3-6z"></path>
                                            <path d="M3 9h18"></path>
                                            <path d="M9 3l3 6 3-6"></path>
                                        </svg>
                                        Membership
                                    </a>
                                <div style="border-top: 1px solid var(--gray-200); margin-top: 0.5rem; padding-top: 0.5rem;">
                                    <a href="<?= SITE_URL ?>/api/auth.php?action=logout" class="dropdown-link" style="color: var(--error);">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                                        Logout
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?= SITE_URL ?>/pages/login.php" class="btn btn-ghost">Login</a>
                    <a href="<?= SITE_URL ?>/pages/register.php" class="btn btn-primary">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- CONTENT -->
    <div class="booking-page-container">
        
        <!-- LEFT: Selection -->
        <div class="booking-left">
            <div class="page-header">
                <h1>Booking: <?= htmlspecialchars($sport['sport_name']) ?></h1>
            </div>

            <!-- Date Selector -->
            <div class="date-selector">
                <a href="?sport_id=<?= $sportId ?>&date=<?= $prevDate ?>" class="date-arrow">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
                </a>
                <div class="current-date"><?= date('D, d M Y', strtotime($selectedDate)) ?></div>
                <a href="?sport_id=<?= $sportId ?>&date=<?= $nextDate ?>" class="date-arrow">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </a>
            </div>

            <!-- Form Start -->
            <form action="<?= SITE_URL ?>/api/book.php" method="POST" id="bookingForm">
                <input type="hidden" name="sport_id" value="<?= $sportId ?>">
                <input type="hidden" name="booking_date" value="<?= $selectedDate ?>">
                <input type="hidden" name="slot_court" id="inputSlotCourt" value="">

                <!-- Grid Table -->
                <table class="booking-grid-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <?php foreach($courts as $c): ?>
                                <th>C<?= preg_replace('/[^0-9]/', '', $c['court_name']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($timeSlots as $slot): ?>
                        <tr>
                            <td class="time-label">
                                <?= date('g:i A', strtotime($slot['start_time'])) ?>
                            </td>
                            <?php foreach($courts as $c): 
                                $key = $c['court_id'].'_'.$slot['slot_id'];
                                
                                // --- Logic ตรวจสอบ ---
                                // 1. ถูกจองแล้ว?
                                $isBookedBySomeone = isset($bookingsMap[$key]);
                                // 2. สถานะคอร์ทไม่ว่าง (Maintenance)?
                                $isCourtUnavailable = ($c['status'] !== 'available');
                                
                                // สรุปสถานะ
                                $isDisabled = $isBookedBySomeone || $isCourtUnavailable;
                                
                                // ข้อความแสดงผล
                                $btnText = '🟢';
                                $btnClass = '';
                                
                                if ($isCourtUnavailable) {
                                    $btnText = '🟡'; // แสดง "Maintenance"
                                    $btnClass = 'is-maintenance'; // CSS Class ใหม่
                                } elseif ($isBookedBySomeone) {
                                    $btnText = '🔴';
                                    $btnClass = 'is-booked';
                                }
                            ?>
                            <td>
                                <button type="button" 
                                        class="slot-btn <?= $btnClass ?>" 
                                        data-court-id="<?= $c['court_id'] ?>"
                                        data-slot-id="<?= $slot['slot_id'] ?>"
                                        data-price="<?= $sport['price'] ?>"
                                        data-time="<?= date('g:i A', strtotime($slot['start_time'])) ?>"
                                        data-court-name="C<?= preg_replace('/[^0-9]/', '', $c['court_name']) ?>"
                                        <?= $isDisabled ? 'disabled' : '' ?>>
                                    <?= $btnText ?>
                                </button>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Equipment -->
                <?php if(!empty($equipments)): ?>
                <div class="equipment-section">
                    <h3 style="font-family:var(--font-display); margin-bottom:1rem;">Add Equipment</h3>
                    <div class="equipment-grid">
                        <?php foreach($equipments as $eq): ?>
                        <div class="eq-item">
                            <div class="eq-info">
                                <h4><?= htmlspecialchars($eq['eq_name']) ?></h4>
                                <p><?= number_format($eq['price']) ?> THB (Stock: <?= $eq['stock'] ?>)</p>
                            </div>
                            <div class="eq-qty-control">
                                <button type="button" class="eq-qty-btn" onclick="changeQty('eq<?= $eq['eq_id'] ?>', -1, <?= $eq['stock'] ?>)">-</button>
                                <input type="number" name="equipment[<?= $eq['eq_id'] ?>]" id="eq<?= $eq['eq_id'] ?>" value="0" max="<?= $eq['stock'] ?>" data-price="<?= $eq['price'] ?>" data-name="<?= htmlspecialchars($eq['eq_name']) ?>" class="eq-qty-val" readonly>
                                <button type="button" class="eq-qty-btn" onclick="changeQty('eq<?= $eq['eq_id'] ?>', 1, <?= $eq['stock'] ?>)">+</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </form>
        </div>

        <!-- RIGHT: Summary -->
        <div class="booking-right">
            <div class="summary-box">
                <h3 style="font-family:var(--font-display); margin-bottom:1.5rem;">Summary</h3>
                
                <div class="sum-row">
                    <span>Date</span>
                    <span style="font-weight:600; color:var(--secondary)"><?= date('d M Y', strtotime($selectedDate)) ?></span>
                </div>
                <div class="sum-row">
                    <span>Time</span>
                    <span id="sumTime" style="font-weight:600; color:var(--secondary)">-</span>
                </div>
                <div class="sum-row">
                    <span>Court</span>
                    <span id="sumCourt" style="font-weight:600; color:var(--secondary)">-</span>
                </div>
                
                <div id="equipSummary" style="display:none;" class="sum-row">
                    <span>Equipment</span>
                    <span id="sumEquip" style="font-weight:600; color:var(--secondary); text-align:right;"></span>
                </div>

                <div class="sum-row total">
                    <span>Total</span>
                    <span id="sumTotal">0 THB</span>
                </div>

                <button type="submit" form="bookingForm" id="btnSubmit" class="btn-submit" disabled>
                    Proceed to Payment
                </button>
            </div>
        </div>

    </div>

     <!-- FOOTER -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <span class="footer-logo">HIT THE COURT</span>
                    <p class="footer-text">
                        College of Arts, Media and Technology,<br>
                        Chiang Mai University<br>
                        © 2026 Hit the Court. A Chiang Mai University Experimental Project.
                    </p>
                </div>
                
                <div class="footer-links">
                    <h4>Menu</h4>
                    <ul>
                        <li><a href="<?= SITE_URL ?>/pages/courts.php">Court Reservation</a></li>
                        <li><a href="#about">About Us</a></li>
                        <li><a href="<?= SITE_URL ?>/pages/guidebook.php">Guidebook</a></li>
                        <li><a href="<?= SITE_URL ?>/pages/reports.php">Contact Us</a></li>
                    </ul>
                </div>
                
                <div class="footer-links">
                    <h4>Contact Us</h4>
                    <ul>
                        <li>
                            <a href="tel:111-222-3">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72"></path></svg>
                                111-222-3
                            </a>
                        </li>
                        <li>
                            <a href="mailto:peoplecmucamt@gmail.com">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                peoplecmucamt@gmail.com
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>HIT THE COURT</p>
            </div>
        </div>
    </footer>

    <!-- JAVASCRIPT -->
    <script>
        // 1. State
        let selectedBtn = null;
        let currentTotal = 0;

        // 2. Slot Selection
        const buttons = document.querySelectorAll('.slot-btn:not(.is-booked):not(.is-maintenance)');
        
        buttons.forEach(btn => {
            btn.addEventListener('click', function() {
                // Reset previous
                if (selectedBtn) {
                    selectedBtn.classList.remove('is-selected');
                    selectedBtn.innerText = 'Available';
                }

                // Set new
                this.classList.add('is-selected');
                this.innerText = 'Selected';
                selectedBtn = this;

                // Update Hidden Input
                const value = this.dataset.courtId + '_' + this.dataset.slotId;
                document.getElementById('inputSlotCourt').value = value;

                // Update UI
                document.getElementById('sumTime').innerText = this.dataset.time;
                document.getElementById('sumCourt').innerText = this.dataset.courtName;
                document.getElementById('btnSubmit').disabled = false;

                calculateTotal();
            });
        });

        // 3. Equipment Quantity
        function changeQty(id, change, max) {
            const input = document.getElementById(id);
            let val = parseInt(input.value) + change;
            if (val < 0) val = 0;
            if (val > max) val = max;
            input.value = val;
            calculateTotal();
        }

        // 4. Calculate Total
        function calculateTotal() {
            let total = 0;
            
            // Court Price
            if (selectedBtn) {
                total += parseFloat(selectedBtn.dataset.price);
            }

            // Equipment Price
            let equipDetails = [];
            document.querySelectorAll('.eq-qty-val').forEach(input => {
                if (input.value > 0) {
                    const qty = parseInt(input.value);
                    const price = parseFloat(input.dataset.price);
                    const name = input.dataset.name || 'Item'; 
                    
                    total += (qty * price);
                    equipDetails.push(name + ' x ' + qty);
                }
            });

            // Update Display
            document.getElementById('sumTotal').innerText = total.toLocaleString() + ' THB';
            
            const eDiv = document.getElementById('equipSummary');
            const sumEquip = document.getElementById('sumEquip');
            
            if (equipDetails.length > 0) {
                eDiv.style.display = 'flex';
                sumEquip.innerHTML = equipDetails.join('<br>'); 
            } else {
                eDiv.style.display = 'none';
                sumEquip.innerText = '';
            }
        }
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
    const hamburger = document.getElementById('hamburger-btn');
    const navMenu = document.getElementById('nav-menu');

    if (hamburger && navMenu) {
        hamburger.addEventListener('click', () => {
            hamburger.classList.toggle('active');
            navMenu.classList.toggle('active');
        });
    }

    // ปิดเมนูเมื่อคลิกข้างนอก (Optional)
    document.addEventListener('click', (e) => {
        if (!navMenu.contains(e.target) && !hamburger.contains(e.target)) {
            hamburger.classList.remove('active');
            navMenu.classList.remove('active');
        }
    });
});
</script>


</body>
</html>