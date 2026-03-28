<?php
session_start();
require_once 'db.php';

// Auth check
if (!isset($_SESSION['kid_id'])) {
    header('Location: index.php?type=kid');
    exit;
}

$kid_id = $_SESSION['kid_id'];
$kid_name = $_SESSION['kid_name'];
$kid_avatar = $_SESSION['kid_avatar'] ?? '🦸';

$msg = '';
$msg_type = '';

// ── Handle Mark as Done ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_chore') {
    $chore_id = intval($_POST['chore_id'] ?? 0);

    // Verify chore belongs to this kid and is pending
    $stmt = $pdo->prepare("SELECT * FROM chores WHERE id = ? AND kid_id = ? AND status = 'pending'");
    $stmt->execute([$chore_id, $kid_id]);
    $chore = $stmt->fetch();

    if ($chore) {
        // Update chore status
        $stmt = $pdo->prepare("UPDATE chores SET status = 'completed', completed_at = NOW() WHERE id = ?");
        $stmt->execute([$chore_id]);

        // Add points to kid
        $stmt = $pdo->prepare("UPDATE kids SET points = points + ? WHERE id = ?");
        $stmt->execute([$chore['points'], $kid_id]);

        $msg = "🎉 Awesome! You earned {$chore['points']} points for completing \"{$chore['title']}\"!";
        $msg_type = 'success';
    } else {
        $msg = 'This chore is not available.';
        $msg_type = 'error';
    }
}

// ── Fetch kid data (refreshed) ──
$stmt = $pdo->prepare("SELECT * FROM kids WHERE id = ?");
$stmt->execute([$kid_id]);
$kid = $stmt->fetch();
$kid_points = $kid['points'];
$kid_avatar = $kid['avatar'];

// ── Fetch Chores ──
$stmt = $pdo->prepare("
    SELECT * FROM chores WHERE kid_id = ?
    ORDER BY FIELD(status, 'pending', 'completed'), created_at DESC
");
$stmt->execute([$kid_id]);
$chores_list = $stmt->fetchAll();

$pending = array_filter($chores_list, fn($c) => $c['status'] === 'pending');
$completed = array_filter($chores_list, fn($c) => $c['status'] === 'completed');

// ── Level calculation ──
$level = 1;
$level_names = ['Rookie', 'Explorer', 'Champion', 'Legend', 'Master', 'Grand Master'];
if ($kid_points >= 500) { $level = 6; }
elseif ($kid_points >= 300) { $level = 5; }
elseif ($kid_points >= 150) { $level = 4; }
elseif ($kid_points >= 75) { $level = 3; }
elseif ($kid_points >= 25) { $level = 2; }
$level_name = $level_names[$level - 1];

// Progress to next level
$level_thresholds = [0, 25, 75, 150, 300, 500, 999999];
$current_threshold = $level_thresholds[$level - 1];
$next_threshold = $level_thresholds[$level];
$progress = min(100, (($kid_points - $current_threshold) / max(1, $next_threshold - $current_threshold)) * 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mission Board — ChoreQuest</title>
    <meta name="description" content="Your ChoreQuest mission board. Complete chores, earn points, level up!">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        brand: {
                            50: '#f0f4ff', 100: '#dbe4ff', 200: '#bac8ff',
                            300: '#91a7ff', 400: '#748ffc', 500: '#5c7cfa',
                            600: '#4c6ef5', 700: '#4263eb', 800: '#3b5bdb', 900: '#364fc7',
                        },
                        mint: {
                            50: '#e6fcf5', 100: '#c3fae8', 200: '#96f2d7',
                            300: '#63e6be', 400: '#38d9a9', 500: '#20c997',
                        },
                        coral: { 400: '#ff8787', 500: '#ff6b6b', 600: '#fa5252' },
                        amber: { 400: '#ffd43b', 500: '#fcc419', 600: '#fab005' },
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .glass {
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .hero-gradient {
            background: linear-gradient(135deg, rgba(76, 110, 245, 0.15), rgba(121, 80, 242, 0.1), rgba(32, 201, 151, 0.08));
        }
        .mission-card {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.06);
            transition: all 0.3s ease;
        }
        .mission-card:hover {
            background: rgba(255, 255, 255, 0.07);
            border-color: rgba(255, 255, 255, 0.12);
            transform: translateY(-2px);
        }
        .btn-complete {
            background: linear-gradient(135deg, #20c997, #12b886);
            transition: all 0.3s ease;
        }
        .btn-complete:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(32, 201, 151, 0.4);
        }
        .progress-bar {
            background: linear-gradient(90deg, #4c6ef5, #7950f2, #20c997);
            background-size: 200% auto;
            animation: shimmer 3s ease infinite;
        }
        @keyframes shimmer {
            0% { background-position: 0% center; }
            50% { background-position: 100% center; }
            100% { background-position: 0% center; }
        }
        .bounce-in {
            animation: bounceIn 0.5s ease;
        }
        @keyframes bounceIn {
            0% { transform: scale(0.8); opacity: 0; }
            60% { transform: scale(1.05); }
            100% { transform: scale(1); opacity: 1; }
        }
        .completed-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.04);
        }
        .confetti-emoji {
            animation: confettiFloat 2s ease-out forwards;
            position: fixed;
            font-size: 2rem;
            z-index: 100;
            pointer-events: none;
        }
        @keyframes confettiFloat {
            0% { opacity: 1; transform: translateY(0) rotate(0deg) scale(1); }
            100% { opacity: 0; transform: translateY(-200px) rotate(720deg) scale(0.3); }
        }
        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 3px; }
    </style>
</head>
<body class="min-h-screen bg-gray-950 text-white">

    <!-- Top Nav -->
    <nav class="sticky top-0 z-50 glass border-b border-white/5">
        <div class="max-w-5xl mx-auto px-4 sm:px-6">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-3">
                    <span class="text-2xl">🏠</span>
                    <h1 class="text-xl font-black">
                        Chore<span class="text-transparent bg-clip-text bg-gradient-to-r from-brand-400 to-purple-400">Quest</span>
                    </h1>
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-amber-500/10 border border-amber-500/20">
                        <span class="text-amber-400">⭐</span>
                        <span class="text-amber-400 font-bold text-sm"><?= $kid_points ?></span>
                    </div>
                    <a href="logout.php"
                        class="px-4 py-2 rounded-lg text-sm font-medium text-gray-400 hover:text-white hover:bg-white/10 transition-all">
                        Sign Out
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 sm:px-6 py-8">

        <!-- Flash Message -->
        <?php if ($msg): ?>
            <div class="mb-6 p-4 rounded-xl text-sm bounce-in <?= $msg_type === 'error'
                ? 'bg-coral-600/15 border border-coral-600/30 text-coral-400'
                : 'bg-emerald-600/15 border border-emerald-600/30 text-emerald-400' ?>">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <!-- Hero Section -->
        <div class="hero-gradient rounded-2xl p-8 mb-8 border border-white/5">
            <div class="flex flex-col sm:flex-row items-center gap-6">
                <div class="text-7xl bounce-in"><?= $kid_avatar ?></div>
                <div class="text-center sm:text-left flex-1">
                    <h2 class="text-3xl font-black mb-1">Welcome, <?= htmlspecialchars($kid_name) ?>!</h2>
                    <p class="text-gray-400 mb-4">You're doing an amazing job, hero! Keep going! 🚀</p>

                    <!-- Level & Progress -->
                    <div class="flex items-center gap-3 mb-2">
                        <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-brand-600/20 text-brand-400 border border-brand-500/20">
                            Level <?= $level ?> — <?= $level_name ?>
                        </span>
                        <span class="text-xs text-gray-500"><?= $kid_points ?> / <?= $next_threshold < 999999 ? $next_threshold : '∞' ?> pts</span>
                    </div>
                    <div class="w-full h-2.5 rounded-full bg-white/5 overflow-hidden">
                        <div class="progress-bar h-full rounded-full transition-all duration-1000"
                            style="width: <?= $progress ?>%"></div>
                    </div>
                </div>
                <div class="text-center">
                    <div class="text-4xl font-black text-amber-400"><?= $kid_points ?></div>
                    <div class="text-xs text-gray-500 uppercase tracking-wider">Total Points</div>
                </div>
            </div>
        </div>

        <!-- Pending Missions -->
        <div class="mb-8">
            <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                <span class="text-2xl">🎯</span> Active Missions
                <?php if (count($pending) > 0): ?>
                    <span class="ml-2 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-500/15 text-amber-400"><?= count($pending) ?></span>
                <?php endif; ?>
            </h3>

            <?php if (empty($pending)): ?>
                <div class="glass rounded-2xl p-12 text-center">
                    <div class="text-6xl mb-4">🎉</div>
                    <h4 class="text-xl font-bold mb-2">All Missions Complete!</h4>
                    <p class="text-gray-400">You've finished everything. Time to relax, hero! 😎</p>
                </div>
            <?php else: ?>
                <div class="grid sm:grid-cols-2 gap-4">
                    <?php foreach ($pending as $chore): ?>
                        <div class="mission-card rounded-2xl p-5">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-bold text-base"><?= htmlspecialchars($chore['title']) ?></h4>
                                    <?php if ($chore['description']): ?>
                                        <p class="text-gray-400 text-sm mt-1"><?= htmlspecialchars($chore['description']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-500/10 ml-3 flex-shrink-0">
                                    <span class="text-amber-400 text-xs">⭐</span>
                                    <span class="text-amber-400 font-bold text-sm"><?= $chore['points'] ?></span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between">
                                <div class="text-xs text-gray-500 flex items-center gap-2">
                                    <?php if ($chore['due_date']): ?>
                                        <span>📅 Due <?= date('M j', strtotime($chore['due_date'])) ?></span>
                                    <?php else: ?>
                                        <span>📅 No deadline</span>
                                    <?php endif; ?>
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="action" value="complete_chore">
                                    <input type="hidden" name="chore_id" value="<?= $chore['id'] ?>">
                                    <button type="submit" onclick="celebrateComplete(event)"
                                        class="btn-complete px-4 py-2 rounded-xl text-white font-bold text-xs uppercase tracking-wider">
                                        ✓ Done!
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Completed Missions -->
        <?php if (!empty($completed)): ?>
        <div>
            <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                <span class="text-2xl">🏆</span> Completed Missions
                <span class="ml-2 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-500/15 text-emerald-400"><?= count($completed) ?></span>
            </h3>
            <div class="space-y-2">
                <?php foreach ($completed as $chore): ?>
                    <div class="completed-card rounded-xl p-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="text-xl">✅</span>
                            <div>
                                <span class="text-sm text-gray-400 line-through"><?= htmlspecialchars($chore['title']) ?></span>
                                <?php if ($chore['completed_at']): ?>
                                    <span class="text-xs text-gray-600 ml-2">
                                        <?= date('M j, g:ia', strtotime($chore['completed_at'])) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="text-xs text-amber-500/60">+<?= $chore['points'] ?> pts</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <script>
        function celebrateComplete(e) {
            const emojis = ['🎉', '⭐', '🌟', '✨', '🎊', '🏆', '💪', '🚀'];
            const btn = e.target;
            const rect = btn.getBoundingClientRect();

            for (let i = 0; i < 8; i++) {
                const emoji = document.createElement('div');
                emoji.className = 'confetti-emoji';
                emoji.textContent = emojis[Math.floor(Math.random() * emojis.length)];
                emoji.style.left = (rect.left + Math.random() * 60 - 30) + 'px';
                emoji.style.top = (rect.top + Math.random() * 20) + 'px';
                emoji.style.animationDelay = (Math.random() * 0.3) + 's';
                document.body.appendChild(emoji);
                setTimeout(() => emoji.remove(), 2000);
            }
        }
    </script>
</body>
</html>
