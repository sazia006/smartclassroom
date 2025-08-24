<?php
session_start();

// Database connection
$servername = "labproject-server.mysql.database.azure.com";
$username = "mqlirefgtl";
$password = "eVxbQb00aNn$$pK2";
$dbname = "labproject-server";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle login
if (isset($_POST['login'])) {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = $_POST['password'];
    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['username'] = $username;
    } else {
        $error = "Invalid credentials";
    }
}

// Handle registration
if (isset($_POST['register'])) {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);

    try {
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $email, $password, $role]);
        $success = "Registration successful! Please log in.";
    } catch (PDOException $e) {
        $error = "Registration failed: " . $e->getMessage();
    }
}

// Handle booking
if (isset($_POST['book']) && isset($_SESSION['user_id']) && $_SESSION['role'] === 'user') {
    $classroom_id = filter_input(INPUT_POST, 'classroom_id', FILTER_SANITIZE_NUMBER_INT);
    $start_time = filter_input(INPUT_POST, 'start_time', FILTER_SANITIZE_STRING);
    $end_time = filter_input(INPUT_POST, 'end_time', FILTER_SANITIZE_STRING);

    // Check for overlapping bookings
    $stmt = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE classroom_id = ? AND status = 'confirmed' AND ((start_time <= ? AND end_time >= ?) OR (start_time <= ? AND end_time >= ?))");
    $stmt->execute([$classroom_id, $start_time, $start_time, $end_time, $end_time]);
    $overlap = $stmt->fetchColumn();

    if ($overlap == 0) {
        $stmt = $conn->prepare("INSERT INTO bookings (user_id, classroom_id, start_time, end_time, status) VALUES (?, ?, ?, ?, 'pending')");
        $stmt->execute([$_SESSION['user_id'], $classroom_id, $start_time, $end_time]);
        $success = "Booking request submitted!";
    } else {
        $error = "Room unavailable for selected time.";
    }
}

// Handle admin confirm/cancel
if (isset($_POST['admin_action']) && isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    $booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_SANITIZE_NUMBER_INT);
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);

    $status = ($action === 'confirm') ? 'confirmed' : 'cancelled';
    $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
    $stmt->execute([$status, $booking_id]);
    $success = "Booking " . $action . "ed!";
}

// Logout
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: main.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Smart Classroom Management System</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-r from-indigo-100 via-purple-100 to-pink-100 min-h-screen flex items-center justify-center">
<!-- 
    <div class="w-[400px] max-w-4xl bg-white shadow-2xl rounded-2xl p-6 relative overflow-hidden">
    <h2 class="text-3xl font-bold text-center text-indigo-700 mb-6">Smart Classroom Management System</h2> -->

    <?php if (isset($error)): ?>
        <div class="bg-red-100 text-red-600 p-3 rounded mb-4 text-center animate-bounce"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if (isset($success)): ?>
        <div class="bg-green-100 text-green-600 p-3 rounded mb-4 text-center animate-pulse"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if (!isset($_SESSION['user_id'])): ?>
        <!--Separate the login and registration forms from the booking section-->
        
        <!-- Login/Register Flip Card -->
         
    <div class="w-[400px] max-w-4xl bg-white shadow-2xl rounded-2xl p-6 relative overflow-hidden">
    <h2 class="text-3xl font-bold text-center text-indigo-700 mb-6">Smart Classroom Management System</h2>
        <div class="relative w-[350px] h-[420px] perspective" id="card">
          <div class="absolute w-full h-full transition-transform duration-700 transform-style-preserve-3d" id="inner-card">
            
            <!-- Login -->
            <div class="absolute w-full h-full bg-white rounded-xl shadow-lg p-6 backface-hidden">
              <h3 class="text-xl font-semibold text-center mb-4">Login</h3>
              <form method="POST" action="" class="space-y-4">
                <input type="text" name="username" placeholder="Username" required class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-indigo-400">
                <input type="password" name="password" placeholder="Password" required class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-indigo-400">
                <button type="submit" name="login" class="w-full bg-indigo-600 text-white py-2 rounded-lg hover:bg-indigo-700 transition">Login</button>
              </form>
              <p class="text-center mt-4 text-indigo-600 cursor-pointer" onclick="flipCard()">Don't have an account? Sign Up</p>
            </div>

            <!-- Register -->
            <div class="absolute w-full h-full bg-white rounded-xl shadow-lg p-6 rotate-y-180 backface-hidden">
              <h3 class="text-xl font-semibold text-center mb-4">Register</h3>
              <form method="POST" action="" class="space-y-4">
                <input type="text" name="username" placeholder="Username" required class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-pink-400">
                <input type="email" name="email" placeholder="Email" required class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-pink-400">
                <input type="password" name="password" placeholder="Password" required class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-pink-400">
                <select name="role" required class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-pink-400">
                  <option value="user">User</option>
                  <option value="admin">Admin</option>
                </select>
                <button type="submit" name="register" class="w-full bg-pink-500 text-white py-2 rounded-lg hover:bg-pink-600 transition">Register</button>
              </form>
              <p class="text-center mt-4 text-pink-600 cursor-pointer" onclick="flipCard()">Already have an account? Sign In</p>
            </div>
          </div>
        </div>
    </div>
    <?php else: ?>
        <!-- Logout -->
        <!-- <form method="POST" action="" class="text-right mb-4">
          <button type="submit" name="logout" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition">Logout</button>
        </form> -->
   
        <?php if ($_SESSION['role'] === 'user'): ?>
  <div class="w-full max-w-4xl bg-white shadow-2xl rounded-2xl p-6 relative overflow-hidden">
    <h2 class="text-3xl font-bold text-center text-indigo-700 mb-6">Smart Classroom Management System</h2>
    <div class="w-full max-w-4xl bg-white shadow-2xl rounded-2xl p-6 relative overflow-hidden">
        <h3 class="text-xl font-semibold text-center mb-6">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo htmlspecialchars($_SESSION['role']); ?>)</h3>
<form method="POST" action="" class="text-right mb-4">
          <button type="submit" name="logout" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition">Logout</button>
        </form>        
            
            <!-- User Booking Form -->
            <h3 class="text-2xl font-semibold text-indigo-700 mb-3">Book a Classroom</h3>
            <form method="POST" action="" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
              <select name="classroom_id" required class="p-3 border rounded-lg focus:ring-2 focus:ring-indigo-400">
                <?php
                $stmt = $conn->query("SELECT id, name, capacity FROM classrooms");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<option value='{$row['id']}'>" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . " (Capacity: " . htmlspecialchars($row['capacity'], ENT_QUOTES, 'UTF-8') . ")</option>";
                }
                ?>
              </select>
              <label for="booking_date" class="sr-only">Booking Date</label>
              <input type="date" name="booking_date" required class="p-3 border rounded-lg focus:ring-2 focus:ring-indigo-400">
              <label for="start_time" class="sr-only">Start Time</label>
              <input type="time" name="start_time" placeholder="Start Time" required class="p-3 border rounded-lg focus:ring-2 focus:ring-indigo-400">
              <label for="end_time" class="sr-only">End Time</label>
              <input type="time" name="end_time" placeholder="End Time" required class="p-3 border rounded-lg focus:ring-2 focus:ring-indigo-400">
              <button type="submit" name="book" class="bg-indigo-600 text-white py-2 rounded-lg hover:bg-indigo-700 transition md:col-span-2">Book Now</button>
            </form>

            <!-- Booking History -->
            <h3 class="text-2xl font-semibold text-indigo-700 mb-3">Your Booking History</h3>
            <div class="overflow-x-auto">
              <table class="w-full border border-gray-200 rounded-lg overflow-hidden shadow">
                <thead class="bg-indigo-100">
                  <tr>
                    <th class="p-2">Classroom</th>
                    <th class="p-2">Start Time</th>
                    <th class="p-2">End Time</th>
                    <th class="p-2">Status</th>
                  </tr>
                </thead>
                <tbody>
                <?php
                $stmt = $conn->prepare("SELECT b.id, c.name, b.start_time, b.end_time, b.status FROM bookings b JOIN classrooms c ON b.classroom_id = c.id WHERE b.user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr class='hover:bg-gray-50 transition'>
                        <td class='p-2'>" . htmlspecialchars($row['name']) . "</td>
                        <td class='p-2'>" . htmlspecialchars($row['start_time']) . "</td>
                        <td class='p-2'>" . htmlspecialchars($row['end_time']) . "</td>
                        <td class='p-2 font-semibold'>" . htmlspecialchars($row['status']) . "</td>
                    </tr>";
                }
                ?>
                </tbody>
              </table>
            </div>

        <?php elseif ($_SESSION['role'] === 'admin'): ?>
            <!-- Admin Classrooms -->
            <h3 class="text-2xl font-semibold text-indigo-700 mb-3">Available Classrooms</h3>
            <div class="overflow-x-auto mb-6">
              <table class="w-full border border-gray-200 rounded-lg shadow">
                <thead class="bg-indigo-100">
                  <tr>
                    <th class="p-2">Name</th>
                    <th class="p-2">Capacity</th>
                  </tr>
                </thead>
                <tbody>
                <?php
                $stmt = $conn->query("SELECT name, capacity FROM classrooms");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr class='hover:bg-gray-50 transition'>
                        <td class='p-2'>" . htmlspecialchars($row['name']) . "</td>
                        <td class='p-2'>" . htmlspecialchars($row['capacity']) . "</td>
                    </tr>";
                }
                ?>
                </tbody>
              </table>
            </div>

            <!-- Admin Booking Management -->
            <h3 class="text-2xl font-semibold text-indigo-700 mb-3">Booking Requests</h3>
            <div class="overflow-x-auto">
              <table class="w-full border border-gray-200 rounded-lg shadow">
                <thead class="bg-indigo-100">
                  <tr>
                    <th class="p-2">User</th>
                    <th class="p-2">Classroom</th>
                    <th class="p-2">Start Time</th>
                    <th class="p-2">End Time</th>
                    <th class="p-2">Status</th>
                    <th class="p-2">Action</th>
                  </tr>
                </thead>
                <tbody>
                <?php
                $stmt = $conn->prepare("SELECT b.id, u.username, c.name, b.start_time, b.end_time, b.status FROM bookings b JOIN users u ON b.user_id = u.id JOIN classrooms c ON b.classroom_id = c.id");
                $stmt->execute();
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr class='hover:bg-gray-50 transition'>
                        <td class='p-2'>" . htmlspecialchars($row['username']) . "</td>
                        <td class='p-2'>" . htmlspecialchars($row['name']) . "</td>
                        <td class='p-2'>" . htmlspecialchars($row['start_time']) . "</td>
                        <td class='p-2'>" . htmlspecialchars($row['end_time']) . "</td>
                        <td class='p-2 font-semibold'>" . htmlspecialchars($row['status']) . "</td>
                        <td class='p-2 space-x-2'>
                            <form method='POST' action='' class='inline'>
                                <input type='hidden' name='booking_id' value='{$row['id']}'>
                                <input type='hidden' name='action' value='confirm'>
                                <button type='submit' name='admin_action' class='bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600 transition'>Confirm</button>
                            </form>
                            <form method='POST' action='' class='inline'>
                                <input type='hidden' name='booking_id' value='{$row['id']}'>
                                <input type='hidden' name='action' value='cancel'>
                                <button type='submit' name='admin_action' class='bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 transition'>Cancel</button>
                            </form>
                        </td>
                    </tr>";
                }
                ?>
                </tbody>
              </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    </div>
    </div>

  <style>
    .perspective { perspective: 1000px; }
    .backface-hidden { backface-visibility: hidden; }
    .rotate-y-180 { transform: rotateY(180deg); }
    .transform-style-preserve-3d { transform-style: preserve-3d; }
  </style>

  <script>
    let flipped = false;
    function flipCard() {
      const card = document.getElementById('inner-card');
      flipped = !flipped;
      card.style.transform = flipped ? 'rotateY(180deg)' : 'rotateY(0deg)';
    }
  </script>

</body>
</html>
<?php $conn = null; ?>
