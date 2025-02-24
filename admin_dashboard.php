<?php
session_start();
require_once 'db.php'; // Database connection

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $db->prepare("SELECT * FROM automobileusers WHERE email = ? AND is_admin = 1");
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $admin['id'];
        header("Location: admin_dashboard.php");
        exit;
    } else {
        $error = "Invalid email or password, or you are not an admin.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WheeleDeal Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Include SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.1.0/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            display: flex;
        }
        .sidebar {
            width: 200px;
            background: #f4f4f4;
            color: white;
            height: 100vh;
            padding: 20px;
            position: fixed;
        }
        .sidebar img {
            width: 100px;
            margin-bottom: 5px;
            display: block; /* Makes it a block element */
    margin: 0 auto; 
        }
        .sidebar h2 {
            color: black;
            text-align: center;
            line-height: 1.2;
            margin-top: 5px;
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
        }
        .sidebar ul li {
            padding: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .sidebar ul li a {
            color: black;
            text-decoration: none;
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        .sidebar ul li a i {
            margin-right: 10px;
        }
        .content {
            margin-left: 250px;
            padding: 20px;
            width: calc(100% - 250px);
        }
        .dashboard-cards {
            display: flex;
            gap: 20px;
        }
        .card {
            background: #ffc107 ;
            padding: 20px;
            border-radius: 10px;
            flex: 1;
            text-align: center;
            width: 150px; /* Reduced width */
    height: 30px; /* Reduced height */
    font-size: 20px;
        }
        .card1 {
            background: #28a745 ;
            padding: 20px;
            border-radius: 10px;
            flex: 1;
            text-align: center;
            
        }
        .card2 {
            background: #17a2b8 ;
            padding: 20px;
            border-radius: 10px;
            flex: 1;
            text-align: center;
        }
        .card3 {
            background: #dc3545 ;
            padding: 20px;
            border-radius: 10px;
            flex: 1;
            text-align: center;
        }
        #totalUsers {
            font-size: 20px;
            font-weight: bold;
            margin-top: 10px;
        }
        .card h3 {
            margin: 0;
        }
        .user-management {
            margin-top: 20px;
        }
        button {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 10px;
            cursor: pointer;
            border-radius: 5px;
        }
        .section {
            display: none;
        }
        .active {
            display: block;
        }
        table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f4f4f4;
        }
    </style>
</head>
<body>
    <div class="sidebar">
    <img src="images/logo3.png" alt="WheeleDeal Logo">
        <h2>WheeledDeal Admin</h2>
        <ul>
            <li><a onclick="showSection('dashboard')"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a onclick="showSection('users')"><i class="fas fa-users"></i> Users</a></li>
            <li><a onclick="showSection('listings')"><i class="fas fa-car"></i> Listings</a></li>
            <li><a onclick="showSection('transactions')"><i class="fas fa-money-bill"></i> Transactions</a></li>
            <li><a onclick="showSection('reviews')"><i class="fas fa-star"></i> Reviews</a></li>
            <li><a onclick="showSection('notifications')"><i class="fas fa-bell"></i> Notifications</a></li>
            <li><a onclick="showSection('reports')"><i class="fas fa-chart-line"></i> Reports</a></li>
            <li><a onclick="showSection('settings')"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="#" onclick="logoutUser()"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    <div class="content">
        <div id="dashboard" class="section active">
            <h1>Admin Dashboard</h1>
            <div class="dashboard-cards">
    <div class="card">
        <h3>Total Users</h3>
        
    </div>
    <p id="totalUsers">Loading...</p>
    <div class="card1">
    <h3>Total Listings</h3>
    <p id="totalListings">Loading...</p>
</div>

                <div class="card2">
                    <h3>Total Sales</h3>
                    <!-- Total Sales count here -->
                </div>
                <div class="card3">
                    <h3>Pending Approvals</h3>
                    <!-- Pending Approvals count here -->
                </div>
            </div>
        </div>
        <div id="users" class="section">
            <h2>User Management</h2>
            <button onclick="alert('Add User Functionality')">Add User</button>
            <button onclick="alert('Edit User Functionality')">Edit User</button>
            <button onclick="alert('Delete User Functionality')">Delete User</button>

            <table id="userTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone Number</th>
                        <th>Gender</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- User data will be populated here by JavaScript -->
                </tbody>
            </table>
        </div>
        <div id="listings" class="section">
            <h2>Car Listings</h2>
            <p>Manage all car listings here.</p>
        </div>
        <div id="transactions" class="section">
            <h2>Transactions</h2>
            <p>View and manage transactions.</p>
        </div>
        <div id="reviews" class="section">
            <h2>Reviews</h2>
            <p>Monitor and manage user reviews.</p>
        </div>
        <div id="notifications" class="section">
            <h2>Notifications</h2>
            <p>Send and view notifications.</p>
        </div>
        <div id="reports" class="section">
            <h2>Reports</h2>
            <p>Generate and export reports.</p>
        </div>
        <div id="settings" class="section">
            <h2>Settings</h2>
            <p>Update website settings.</p>
        </div>
    </div>

    <!-- Include SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.1.0/dist/sweetalert2.all.min.js"></script>

    <script>
        function showSection(sectionId) {
            document.querySelectorAll('.section').forEach(section => {
                section.classList.remove('active');
            });
            document.getElementById(sectionId).classList.add('active');

            if (sectionId === 'users') {
                loadUsers();  // Call loadUsers() to fetch and display the users
            }
        }

        function loadUsers() {
            fetch('get_users.php')
                .then(response => response.json())
                .then(users => {
                    let tableBody = document.querySelector("#userTable tbody");
                    tableBody.innerHTML = "";

                    if (users.length === 0) {
                        tableBody.innerHTML = "<tr><td colspan='6'>No users found.</td></tr>";
                    } else {
                        users.forEach(user => {
                            let row = `<tr>
                                <td>${user.user_id || 'N/A'}</td>
                                <td>${user.name}</td>
                                <td>${user.email}</td>
                                <td>${user.number}</td>
                                <td>${user.gender}</td>
                               <td>
                            <button onclick="editUser(${user.user_id}, '${user.name}', '${user.email}', '${user.number}', '${user.gender}')">Edit</button>
                            <button onclick="deleteUser(${user.user_id})">Delete</button>
                        </td>
                            </tr>`;
                            tableBody.innerHTML += row;
                        });
                    }
                })
                .catch(error => {
                    console.error('Error loading users:', error);
                    alert('There was an issue loading the users.');
                });
        }

        function logoutUser() {
            Swal.fire({
                title: "Are you sure?",
                text: "You will be logged out of the admin panel!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#3085d6",
                confirmButtonText: "Yes, log me out!",
                cancelButtonText: "Cancel"
            }).then((result) => {
                if (result.isConfirmed) {
                    // Redirect to adminlogout.php to destroy the session
                    window.location.href = "adminlogout.php"; // Ensure this points to your actual logout page
                }
            });
        }
        //edit and delete

        function deleteUser(user_id) {
    Swal.fire({
        title: "Are you sure?",
        text: "This action will permanently delete the user!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "Yes, delete it!",
        cancelButtonText: "Cancel"
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('delete_user.php', {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: `user_id=${encodeURIComponent(user_id)}`
            })
            .then(response => response.json())
            .then(data => {
                console.log(data); // Debugging: Check response
                if (data.success) {
                    Swal.fire("Deleted!", "User has been deleted.", "success");
                    loadUsers(); // Refresh the user list
                } else {
                    Swal.fire("Error!", data.message, "error");
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                Swal.fire("Error!", "There was an issue deleting the user.", "error");
            });
        }
    });
}

// function editUser(user_id) {
//     Swal.fire({
//         title: "Edit User",
//         html: `
//             <input type="text" id="editName" class="swal2-input" placeholder="Enter Name">
//             <input type="email" id="editEmail" class="swal2-input" placeholder="Enter Email">
//             <input type="text" id="editPhone" class="swal2-input" placeholder="Enter Phone Number">
//         `,
//         showCancelButton: true,
//         confirmButtonText: "Save Changes",
//         preConfirm: () => {
//             const name = document.getElementById('editName').value;
//             const email = document.getElementById('editEmail').value;
//             const number = document.getElementById('editPhone').value;
// // Change this from 'number' to 'phone'

//             if (!name || !email || !number) {
//                 Swal.showValidationMessage("All fields are required!");
//                 return false;
//             }

//             return { user_id, name, email, number }; // 'phone' should be used here, not 'number'
//         }
//     }).then((result) => {
//         if (result.isConfirmed) {
//             fetch('edit_user.php', {
//                 method: "POST",
//                 headers: {
//                     "Content-Type": "application/x-www-form-urlencoded"
//                 },
//                 body: `user_id=${user_id}&name=${result.value.name}&email=${result.value.email}&number=${result.value.number}`

//             })
//             .then(response => response.json())
//             .then(data => {
//                 if (data.success) {
//                     Swal.fire("Updated!", "User details have been updated.", "success");
//                     loadUsers(); // Refresh the user list
//                 } else {
//                     Swal.fire("Error!", data.message, "error");
//                 }
//             })
//             .catch(error => {
//                 Swal.fire("Error!", "There was an issue updating the user.", "error");
//             });
//         }
//     });
// }
//total no of users 
function loadTotalUsers() {
    fetch('get_total_users.php')
        .then(response => response.json())
        .then(data => {
            if (data.total_users !== undefined) {
                document.getElementById('totalUsers').innerText = data.total_users;
            } else {
                document.getElementById('totalUsers').innerText = "Error";
            }
        })
        .catch(error => {
            console.error('Error fetching total users:', error);
            document.getElementById('totalUsers').innerText = "Error";
        });
}
// Function to load the total number of vehicles
function loadTotalVehicles() {
    fetch('get_lostings.php') // Call the new PHP script
        .then(response => response.json())
        .then(data => {
            if (data.total_vehicles !== undefined) {
                document.getElementById('totalVehicles').innerText = data.total_vehicles;
            } else if (data.error) {
                console.error(data.error); // Log error message if exists
                document.getElementById('totalVehicles').innerText = "Error fetching vehicle count";
            } else {
                document.getElementById('totalVehicles').innerText = "No vehicles found";
            }
        })
        .catch(error => {
            console.error('Error fetching total vehicles:', error);
            document.getElementById('totalVehicles').innerText = "Error";
        });
}

// Modify the showSection function to load total vehicles when the 'listings' section is shown
function showSection(sectionId) {
    document.querySelectorAll('.section').forEach(section => {
        section.classList.remove('active');
    });
    document.getElementById(sectionId).classList.add('active');

    if (sectionId === 'listings') {
        loadTotalVehicles();  // Load total vehicles when "Listings" section is clicked
    }

    if (sectionId === 'users') {
        loadUsers();  // Call loadUsers() to fetch and display the users
    }
}
//listings
function showSection(sectionId) {
    document.querySelectorAll('.section').forEach(section => {
        section.classList.remove('active');
    });
    document.getElementById(sectionId).classList.add('active');

    if (sectionId === 'listings') {
        loadVehicles();  // Load vehicles when "Listings" section is clicked
    }

    if (sectionId === 'users') {
        loadUsers();  // Call loadUsers() to fetch and display the users
    }
}

// Call the function when the dashboard loads
document.addEventListener('DOMContentLoaded', () => {
    loadTotalUsers(); // Assuming you already have this function for users
    loadTotalVehicles(); // Load total vehicles when the dashboard loads
});




    </script>
</body>
</html>
