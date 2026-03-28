<?php
session_start();
require_once 'db.php';

// Auth check
if (!isset($_SESSION['parent_id'])) {
    header('Location: index.php');
    exit;
}

$parent_id = $_SESSION['parent_id'];
$parent_name = $_SESSION['parent_name'];
$msg = '';
$msg_type = '';

// ── Handle Add Kid ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_kid') {
    $kid_name = trim($_POST['kid_name'] ?? '');
    $kid_username = trim($_POST['kid_username'] ?? '');
    $kid_password = $_POST['kid_password'] ?? '';
    $kid_avatar = $_POST['kid_avatar'] ?? '🦸';

    if (empty($kid_name) || empty($kid_username) || empty($kid_password)) {
        $msg = 'All fields are required to add a kid.';
        $msg_type = 'error';
    } elseif (strlen($kid_password) < 4) {
        $msg = 'Kid password must be at least 4 characters.';
        $msg_type = 'error';
    } else {
        // Check if username exists
        $stmt = $pdo->prepare("SELECT id FROM kids WHERE username = ?");
        $stmt->execute([$kid_username]);
        if ($stmt->fetch()) {
            $msg = 'This username is already taken. Try another.';
            $msg_type = 'error';
        } else {
            $hashed = password_hash($kid_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO kids (parent_id, name, username, password, avatar) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$parent_id, $kid_name, $kid_username, $hashed, $kid_avatar]);
            $msg = "Kid \"$kid_name\" added! Username: <strong>$kid_username</strong>";
            $msg_type = 'success';
        }
    }
}

// ── Handle Assign Chore ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_chore') {
    $kid_id = intval($_POST['kid_id'] ?? 0);
    $title = trim($_POST['chore_title'] ?? '');
    $description = trim($_POST['chore_description'] ?? '');
    $points = intval($_POST['chore_points'] ?? 10);
    $due_date = $_POST['due_date'] ?? null;

    if (empty($title) || $kid_id === 0) {
        $msg = 'Please select a kid and enter a chore title.';
        $msg_type = 'error';
    } else {
        // Verify kid belongs to this parent
        $stmt = $pdo->prepare("SELECT id FROM kids WHERE id = ? AND parent_id = ?");
        $stmt->execute([$kid_id, $parent_id]);
        if (!$stmt->fetch()) {
            $msg = 'Invalid kid selected.';
            $msg_type = 'error';
        } else {
            $stmt = $pdo->prepare("INSERT INTO chores (parent_id, kid_id, title, description, points, due_date) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$parent_id, $kid_id, $title, $description, $points, $due_date ?: null]);
            $msg = "Chore \"$title\" assigned successfully!";
            $msg_type = 'success';
        }
    }
}

// ── Handle Delete Chore ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_chore') {
    $chore_id = intval($_POST['chore_id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM chores WHERE id = ? AND parent_id = ?");
    $stmt->execute([$chore_id, $parent_id]);
    $msg = 'Chore deleted.';
    $msg_type = 'success';
}

// ── Fetch Data ──
$kids = $pdo->prepare("SELECT * FROM kids WHERE parent_id = ? ORDER BY name");
$kids->execute([$parent_id]);
$kids_list = $kids->fetchAll();

$chores = $pdo->prepare("
    SELECT c.*, k.name as kid_name, k.avatar as kid_avatar
    FROM chores c
    JOIN kids k ON c.kid_id = k.id
    WHERE c.parent_id = ?
    ORDER BY c.status ASC, c.created_at DESC
");
$chores->execute([$parent_id]);
$chores_list = $chores->fetchAll();

// Stats
$total_kids = count($kids_list);
$pending_chores = count(array_filter($chores_list, fn($c) => $c['status'] === 'pending'));
$completed_chores = count(array_filter($chores_list, fn($c) => $c['status'] === 'completed'));
$total_points = array_sum(array_map(fn($k) => $k['points'], $kids_list));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard — ChoreQuest</title>
    <meta name="description" content="Manage your family's chores, add kids, and track progress in ChoreQuest.">
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
        .stat-card {
            background: linear-gradient(145deg, rgba(255,255,255,0.08), rgba(255,255,255,0.03));
            border: 1px solid rgba(255,255,255,0.08);
            transition: all 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-4px); border-color: rgba(255,255,255,0.15); }
        .input-field {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }
        .input-field:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: #4c6ef5;
            box-shadow: 0 0 0 3px rgba(76, 110, 245, 0.15);
        }
        .btn-primary {
            background: linear-gradient(135deg, #4c6ef5, #7950f2);
            transition: all 0.3s ease;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(76, 110, 245, 0.4); }
        .btn-danger {
            background: linear-gradient(135deg, #fa5252, #e03131);
            transition: all 0.3s ease;
        }
        .btn-danger:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(250, 82, 82, 0.3); }
        .chore-row {
            transition: all 0.3s ease;
            border: 1px solid rgba(255,255,255,0.05);
        }
        .chore-row:hover { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.1); }
        .avatar-btn { transition: all 0.2s ease; }
        .avatar-btn:hover { transform: scale(1.2); }
        .avatar-btn.selected {
            background: rgba(76, 110, 245, 0.3);
            border-color: #4c6ef5;
            transform: scale(1.15);
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
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-3">
                    <span class="text-2xl">🏠</span>
                    <h1 class="text-xl font-black">
                        Chore<span class="text-transparent bg-clip-text bg-gradient-to-r from-brand-400 to-purple-400">Quest</span>
                    </h1>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-400">
                        Welcome, <span class="text-white font-semibold"><?= htmlspecialchars($parent_name) ?></span>
                    </span>
                    <a href="logout.php"
                        class="px-4 py-2 rounded-lg text-sm font-medium text-gray-400 hover:text-white hover:bg-white/10 transition-all">
                        Sign Out
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Flash Message -->
        <?php if ($msg): ?>
            <div class="mb-6 p-4 rounded-xl text-sm <?= $msg_type === 'error'
                ? 'bg-coral-600/15 border border-coral-600/30 text-coral-400'
                : 'bg-emerald-600/15 border border-emerald-600/30 text-emerald-400' ?>">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <!-- Stat Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="stat-card rounded-2xl p-5">
                <div class="text-3xl mb-2">👶</div>
                <div class="text-3xl font-black"><?= $total_kids ?></div>
                <div class="text-xs text-gray-400 uppercase tracking-wider mt-1">Kids</div>
            </div>
            <div class="stat-card rounded-2xl p-5">
                <div class="text-3xl mb-2">📋</div>
                <div class="text-3xl font-black text-amber-400"><?= $pending_chores ?></div>
                <div class="text-xs text-gray-400 uppercase tracking-wider mt-1">Pending</div>
            </div>
            <div class="stat-card rounded-2xl p-5">
                <div class="text-3xl mb-2">✅</div>
                <div class="text-3xl font-black text-mint-400"><?= $completed_chores ?></div>
                <div class="text-xs text-gray-400 uppercase tracking-wider mt-1">Completed</div>
            </div>
            <div class="stat-card rounded-2xl p-5">
                <div class="text-3xl mb-2">⭐</div>
                <div class="text-3xl font-black text-amber-500"><?= $total_points ?></div>
                <div class="text-xs text-gray-400 uppercase tracking-wider mt-1">Total Points</div>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">

            <!-- Left Column: Forms -->
            <div class="lg:col-span-1 space-y-6">

                <!-- Add Kid Form -->
                <div class="glass rounded-2xl p-6">
                    <h2 class="text-lg font-bold mb-1 flex items-center gap-2">
                        <span class="text-2xl">🧒</span> Add a Kid
                    </h2>
                    <p class="text-gray-500 text-xs mb-5">Create a login for your child.</p>

                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="add_kid">
                        <div>
                            <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1.5">Kid's Name</label>
                            <input type="text" name="kid_name" placeholder="e.g. Alex" required
                                class="input-field w-full px-4 py-2.5 rounded-xl text-white placeholder-gray-500 text-sm outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1.5">Username</label>
                            <input type="text" name="kid_username" placeholder="e.g. alex_hero" required
                                class="input-field w-full px-4 py-2.5 rounded-xl text-white placeholder-gray-500 text-sm outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1.5">Password</label>
                            <input type="text" name="kid_password" placeholder="Simple & memorable" required
                                class="input-field w-full px-4 py-2.5 rounded-xl text-white placeholder-gray-500 text-sm outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1.5">Avatar</label>
                            <div class="flex gap-2 flex-wrap" id="avatar-picker">
                                <?php
                                $avatars = ['🦸', '🧙', '🦹', '🧑‍🚀', '🧑‍🎨', '🦊', '🐱', '🐶', '🦄', '🐸', '🌟', '🚀'];
                                foreach ($avatars as $i => $av): ?>
                                    <button type="button" onclick="pickAvatar(this, '<?= $av ?>')"
                                        class="avatar-btn w-10 h-10 rounded-lg border border-white/10 flex items-center justify-center text-xl hover:bg-white/10 <?= $i === 0 ? 'selected' : '' ?>">
                                        <?= $av ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="kid_avatar" id="kid_avatar" value="🦸">
                        </div>
                        <button type="submit" class="btn-primary w-full py-2.5 rounded-xl text-white font-bold text-sm">
                            Add Kid
                        </button>
                    </form>
                </div>

                <!-- Assign Chore Form -->
                <div class="glass rounded-2xl p-6">
                    <h2 class="text-lg font-bold mb-1 flex items-center gap-2">
                        <span class="text-2xl">📝</span> Assign a Chore
                    </h2>
                    <p class="text-gray-500 text-xs mb-5">Create a new mission for a kid.</p>

                    <?php if (empty($kids_list)): ?>
                        <div class="text-center py-6 text-gray-500">
                            <div class="text-4xl mb-2">👆</div>
                            <p class="text-sm">Add a kid first to assign chores.</p>
                        </div>
                    <?php else: ?>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="assign_chore">
                            <div>
                                <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1.5">Assign To</label>
                                <select name="kid_id" required
                                    class="input-field w-full px-4 py-2.5 rounded-xl text-white text-sm outline-none appearance-none cursor-pointer">
                                    <option value="">Select a kid...</option>
                                    <?php foreach ($kids_list as $kid): ?>
                                        <option value="<?= $kid['id'] ?>">
                                            <?= $kid['avatar'] ?> <?= htmlspecialchars($kid['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1.5">Chore Title</label>
                                <input type="text" name="chore_title" placeholder="e.g. Clean your room" required
                                    class="input-field w-full px-4 py-2.5 rounded-xl text-white placeholder-gray-500 text-sm outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1.5">Description <span class="text-gray-600">(optional)</span></label>
                                <textarea name="chore_description" placeholder="Any extra details..." rows="2"
                                    class="input-field w-full px-4 py-2.5 rounded-xl text-white placeholder-gray-500 text-sm outline-none resize-none"></textarea>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1.5">Points</label>
                                    <input type="number" name="chore_points" value="10" min="1" max="100"
                                        class="input-field w-full px-4 py-2.5 rounded-xl text-white text-sm outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1.5">Due Date</label>
                                    <input type="date" name="due_date"
                                        class="input-field w-full px-4 py-2.5 rounded-xl text-white text-sm outline-none">
                                </div>
                            </div>
                            <button type="submit" class="btn-primary w-full py-2.5 rounded-xl text-white font-bold text-sm">
                                Assign Chore
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Family & Chores Overview -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Kids Overview -->
                <?php if (!empty($kids_list)): ?>
                <div class="glass rounded-2xl p-6">
                    <h2 class="text-lg font-bold mb-4 flex items-center gap-2">
                        <span class="text-2xl">👨‍👩‍👧‍👦</span> Your Kids
                    </h2>
                    <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-3">
                        <?php foreach ($kids_list as $kid): ?>
                            <div class="bg-white/5 rounded-xl p-4 border border-white/5 hover:border-white/10 transition-all">
                                <div class="flex items-center gap-3">
                                    <div class="text-3xl"><?= $kid['avatar'] ?></div>
                                    <div>
                                        <div class="font-bold text-sm"><?= htmlspecialchars($kid['name']) ?></div>
                                        <div class="text-xs text-gray-500">@<?= htmlspecialchars($kid['username']) ?></div>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center gap-1.5">
                                    <span class="text-amber-400 text-sm">⭐</span>
                                    <span class="text-amber-400 font-bold text-lg"><?= $kid['points'] ?></span>
                                    <span class="text-gray-500 text-xs ml-1">points</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Chores List -->
                <div class="glass rounded-2xl p-6">
                    <h2 class="text-lg font-bold mb-4 flex items-center gap-2">
                        <span class="text-2xl">📋</span> All Chores
                    </h2>

                    <?php if (empty($chores_list)): ?>
                        <div class="text-center py-12 text-gray-500">
                            <div class="text-5xl mb-3">🎯</div>
                            <p class="text-sm">No chores assigned yet.</p>
                            <p class="text-xs text-gray-600 mt-1">Create one using the form on the left.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($chores_list as $chore): ?>
                                <div class="chore-row rounded-xl p-4 bg-white/[0.02] flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="text-2xl flex-shrink-0">
                                            <?= $chore['status'] === 'completed' ? '✅' : '⏳' ?>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-semibold text-sm <?= $chore['status'] === 'completed' ? 'line-through text-gray-500' : '' ?>">
                                                <?= htmlspecialchars($chore['title']) ?>
                                            </div>
                                            <div class="text-xs text-gray-500 flex items-center gap-2 mt-0.5">
                                                <span><?= $chore['kid_avatar'] ?> <?= htmlspecialchars($chore['kid_name']) ?></span>
                                                <span>•</span>
                                                <span class="text-amber-400">⭐ <?= $chore['points'] ?> pts</span>
                                                <?php if ($chore['due_date']): ?>
                                                    <span>•</span>
                                                    <span>📅 <?= date('M j', strtotime($chore['due_date'])) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <span class="px-2.5 py-1 rounded-lg text-xs font-semibold <?= $chore['status'] === 'completed'
                                            ? 'bg-emerald-500/15 text-emerald-400'
                                            : 'bg-amber-500/15 text-amber-400' ?>">
                                            <?= ucfirst($chore['status']) ?>
                                        </span>
                                        <form method="POST" class="inline" onsubmit="return confirm('Delete this chore?')">
                                            <input type="hidden" name="action" value="delete_chore">
                                            <input type="hidden" name="chore_id" value="<?= $chore['id'] ?>">
                                            <button type="submit"
                                                class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-600 hover:text-coral-400 hover:bg-coral-500/10 transition-all text-sm">
                                                🗑️
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        function pickAvatar(btn, emoji) {
            document.querySelectorAll('.avatar-btn').forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
            document.getElementById('kid_avatar').value = emoji;
        }
    </script>
</body>
</html>
