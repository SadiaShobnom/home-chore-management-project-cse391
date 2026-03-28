<?php
session_start();
require_once 'db.php';

// Redirect if already logged in
if (isset($_SESSION['parent_id'])) {
    header('Location: parent_dashboard.php');
    exit;
}
if (isset($_SESSION['kid_id'])) {
    header('Location: kid_dashboard.php');
    exit;
}

$error = '';
$login_type = isset($_GET['type']) ? $_GET['type'] : 'parent';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_type = $_POST['login_type'] ?? 'parent';

    if ($login_type === 'parent') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM parents WHERE email = ?");
            $stmt->execute([$email]);
            $parent = $stmt->fetch();

            if ($parent && password_verify($password, $parent['password'])) {
                $_SESSION['parent_id'] = $parent['id'];
                $_SESSION['parent_name'] = $parent['name'];
                header('Location: parent_dashboard.php');
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        }
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM kids WHERE username = ?");
            $stmt->execute([$username]);
            $kid = $stmt->fetch();

            if ($kid && password_verify($password, $kid['password'])) {
                $_SESSION['kid_id'] = $kid['id'];
                $_SESSION['kid_name'] = $kid['name'];
                $_SESSION['kid_avatar'] = $kid['avatar'];
                header('Location: kid_dashboard.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChoreQuest — Home Chore Management</title>
    <meta name="description" content="ChoreQuest: A fun and engaging home chore management system for families. Parents assign, kids conquer!">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
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
                        coral: {
                            400: '#ff8787', 500: '#ff6b6b', 600: '#fa5252',
                        },
                        amber: {
                            400: '#ffd43b', 500: '#fcc419', 600: '#fab005',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .glass {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }
        .float-animation {
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-15px) rotate(2deg); }
            66% { transform: translateY(-8px) rotate(-1deg); }
        }
        .tab-active {
            background: linear-gradient(135deg, #4c6ef5, #7950f2);
            color: white;
            box-shadow: 0 4px 15px rgba(76, 110, 245, 0.4);
        }
        .tab-inactive {
            background: rgba(255, 255, 255, 0.06);
            color: rgba(255, 255, 255, 0.5);
        }
        .tab-inactive:hover {
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.8);
        }
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
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 110, 245, 0.4);
        }
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.3;
            animation: orbFloat 8s ease-in-out infinite;
        }
        @keyframes orbFloat {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(30px, -20px) scale(1.1); }
        }
    </style>
</head>
<body class="min-h-screen bg-gray-950 text-white overflow-hidden relative">

    <!-- Background Orbs -->
    <div class="orb w-96 h-96 bg-brand-600 -top-20 -left-20" style="animation-delay: 0s;"></div>
    <div class="orb w-80 h-80 bg-purple-600 top-1/2 -right-20" style="animation-delay: 2s;"></div>
    <div class="orb w-72 h-72 bg-mint-500 -bottom-20 left-1/3" style="animation-delay: 4s;"></div>

    <div class="relative z-10 min-h-screen flex items-center justify-center px-4 py-8">
        <div class="w-full max-w-md">

            <!-- Logo / Header -->
            <div class="text-center mb-8">
                <div class="float-animation inline-block text-6xl mb-4">🏠</div>
                <h1 class="text-4xl font-black tracking-tight">
                    Chore<span class="text-transparent bg-clip-text bg-gradient-to-r from-brand-400 to-purple-400">Quest</span>
                </h1>
                <p class="text-gray-400 mt-2 text-sm">Family chore management, made fun.</p>
            </div>

            <!-- Login Card -->
            <div class="glass rounded-2xl p-8 shadow-2xl">

                <!-- Tab Switcher -->
                <div class="flex gap-2 mb-8 p-1 rounded-xl bg-white/5">
                    <button onclick="switchTab('parent')" id="tab-parent"
                        class="flex-1 py-2.5 px-4 rounded-lg text-sm font-semibold transition-all duration-300 tab-active">
                        👨‍👩‍👧 Parent
                    </button>
                    <button onclick="switchTab('kid')" id="tab-kid"
                        class="flex-1 py-2.5 px-4 rounded-lg text-sm font-semibold transition-all duration-300 tab-inactive">
                        🧒 Kid
                    </button>
                </div>

                <?php if ($error): ?>
                    <div class="mb-6 p-3 rounded-xl bg-coral-600/20 border border-coral-600/30 text-coral-400 text-sm text-center">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <!-- Parent Login Form -->
                <form id="form-parent" method="POST" class="space-y-5" style="display: <?= $login_type === 'parent' ? 'block' : 'none' ?>;">
                    <input type="hidden" name="login_type" value="parent">
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Email Address</label>
                        <input type="email" name="email" placeholder="parent@email.com" required
                            class="input-field w-full px-4 py-3 rounded-xl text-white placeholder-gray-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Password</label>
                        <input type="password" name="password" placeholder="••••••••" required
                            class="input-field w-full px-4 py-3 rounded-xl text-white placeholder-gray-500 outline-none">
                    </div>
                    <button type="submit" class="btn-primary w-full py-3.5 rounded-xl text-white font-bold text-sm uppercase tracking-wider">
                        Sign In as Parent
                    </button>
                </form>

                <!-- Kid Login Form -->
                <form id="form-kid" method="POST" class="space-y-5" style="display: <?= $login_type === 'kid' ? 'block' : 'none' ?>;">
                    <input type="hidden" name="login_type" value="kid">
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Username</label>
                        <input type="text" name="username" placeholder="your_username" required
                            class="input-field w-full px-4 py-3 rounded-xl text-white placeholder-gray-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Password</label>
                        <input type="password" name="password" placeholder="••••••••" required
                            class="input-field w-full px-4 py-3 rounded-xl text-white placeholder-gray-500 outline-none">
                    </div>
                    <button type="submit" class="btn-primary w-full py-3.5 rounded-xl text-white font-bold text-sm uppercase tracking-wider">
                        Sign In as Kid Hero
                    </button>
                </form>

                <!-- Register Link (Parents Only) -->
                <div class="mt-6 text-center">
                    <p class="text-gray-500 text-sm">
                        New parent?
                        <a href="register.php" class="text-brand-400 hover:text-brand-300 font-semibold transition-colors">
                            Create Account →
                        </a>
                    </p>
                </div>
            </div>

            <!-- Footer -->
            <p class="text-center text-gray-600 text-xs mt-6">
                ChoreQuest &copy; <?= date('Y') ?> — Making chores an adventure!
            </p>
        </div>
    </div>

    <script>
        function switchTab(type) {
            const tabParent = document.getElementById('tab-parent');
            const tabKid = document.getElementById('tab-kid');
            const formParent = document.getElementById('form-parent');
            const formKid = document.getElementById('form-kid');

            if (type === 'parent') {
                tabParent.className = tabParent.className.replace('tab-inactive', 'tab-active');
                tabKid.className = tabKid.className.replace('tab-active', 'tab-inactive');
                formParent.style.display = 'block';
                formKid.style.display = 'none';
            } else {
                tabKid.className = tabKid.className.replace('tab-inactive', 'tab-active');
                tabParent.className = tabParent.className.replace('tab-active', 'tab-inactive');
                formKid.style.display = 'block';
                formParent.style.display = 'none';
            }
        }

        // Restore tab from URL or POST
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('type') === 'kid') switchTab('kid');
    </script>
</body>
</html>
