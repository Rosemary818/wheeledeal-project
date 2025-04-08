<?php
include 'db_connect.php'; // This should now use PDO internally
session_start();

try {
    // Prepare and execute a query using PDO
    $stmt = $conn->query("SELECT * FROM users");

    // Fetch all rows as associative arrays
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Username: " . htmlspecialchars($row['username']) . "<br>";
    }

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WheeledDeal</title>
    
    <style>
        /* General reset */
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            font-size: 18px; /* Increased base font size */
        }
    
        /* Header styles */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            background-color: white;
            border-bottom: 1px solid #ddd;
            vertical-align: middle;
        }
    
        .logo {
            display: flex;
            align-items: center;
        }
    
        .logo img {
            height: 50px;
            margin-right: 10px;
        }
    
        .logo h1 {
            font-size: 30px; /* Increased font size */
            margin: 0;
            color: #333;
        }
    
        nav {
            display: flex;
            align-items: center;
        }
    
        nav a {
            text-decoration: none;
            color: #333;
            margin-right: 20px;
            font-size: 20px; /* Increased font size */
        }
    
        nav a:last-child {
            margin-right: 0;
        }
    
        nav a:hover {
            color: #007bff;
        }
    
        /* Search bar styles */
        .search-container {
            display: flex;
            align-items: center;
            width: 40%;
            position: relative;
        }
    
        .search-container input {
            width: 300%;
            padding: 10px 15px; /* Increased padding */
            border: 1px solid #ddd;
            border-radius: 20px;
            outline: none;
            font-size: 18px; /* Increased font size */
        }
    
        .search-container button {
            margin-left: -40px;
            border: none;
            background: none;
            cursor: pointer;
        }
    
        .search-container button img {
            width: 24px; /* Increased size */
            height: 24px; /* Increased size */
        }
    
        /* Wishlist and login/register links */
        .icons {
            display: flex;
            align-items: center;
        }
    
        .icons a {
            margin-left: 20px;
            color: #333;
            text-decoration: none;
            font-size: 20px; /* Increased font size */
        }
    
        .icons img {
            width: 35px; /* Increased size */
            height: 35px; /* Increased size */
            margin-right: 5px;
        }
    
        .icons a:hover {
            color: #ff5722;
        }
    
        /* Hero section */
        .hero {
            text-align: center;
            padding: 60px 20px; /* Increased padding */
            background-color: #f7f4f1;
        }
    
        .hero h1 {
            font-size: 48px; /* Increased font size */
            margin-bottom: 20px;
        }
    
        .hero p {
            font-size: 24px; /* Increased font size */
            color: #555;
            margin-bottom: 20px;
        }
    
        .hero img {
            width: 60px; /* Increased size */
            vertical-align: middle;
        }
        .hero-image {
    width: 40%; /* Adjusts the width of the image block */
    text-align: center;
    position: absolute; 
    top: 160px;
    left: 1300px;
    width: 30%;
}

.hero-image img {
    width: 60%; /* Adjusted image size to be bigger */
    height: auto; /* Maintain aspect ratio */
    border-radius: 10px; /* Optional: Rounded corners */
    box-shadow: 0 5px 10px  rgba(255, 255, 255, 0.7); /* Optional: Enhanced shadow for better aesthetics */
}

    
     /* Steps section */
.steps {
    display: flex;
    justify-content: center;
    margin-top: 40px;
    gap: 30px;
    flex-wrap: wrap;
}

.steps .step {
    background-color: #fff;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    text-align: center;
    width: 200px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.steps .step:hover {
    transform: translateY(-10px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
}

.steps .step img {
    width: 80px;
    height: 80px;
    margin-bottom: 15px;
}

.steps .step h3 {
    font-size: 20px;
    margin-bottom: 10px;
    color: #333;
}

.steps .step p {
    font-size: 16px;
    color: #555;
}



    
        /* Registration section */
        .container {
            text-align: center;
            background-color: #fff;
            padding: 30px 60px; /* Increased padding */
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 800px; /* Increased max width */
            margin: 0 auto;
        }
    
        h2 {
            color: #333;
            font-size: 32px; /* Increased font size */
            margin-bottom: 25px;
        }
    
        .input-group {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 15px; /* Increased margin */
        }
    
        .input-group input {
            width: 70%;
            padding: 12px 20px; /* Increased padding */
            font-size: 18px; /* Increased font size */
            border: 1px solid #ddd;
            border-radius: 5px;
            outline: none;
            margin-right: 15px; /* Increased margin */
        }
    
        .input-group button {
            padding: 12px 25px; /* Increased padding */
            background-color: #ff5722;
            color: #fff;
            font-size: 18px; /* Increased font size */
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
    
        .input-group button:hover {
            background-color: #e64a19;
        }
    
        .divider {
            border-top: 1px solid #ddd;
            margin: 30px 0; /* Increased margin */
        }
    
        .brand-section {
            text-align: center;
        }
    
        .brand-section h3 {
            margin-bottom: 20px; /* Increased margin */
            font-size: 22px; /* Increased font size */
            color: #555;
        }
    
        .brand-list {
            display: flex;
            justify-content: center;
            flex-wrap: nowrap;
            gap: 25px; /* Increased gap */
        }
    
        .brand-list a {
            text-decoration: none;
            text-align: center;
            display: inline-block;
            color: #333;
            font-size: 16px; /* Increased font size */
            width: 90px; /* Increased width */
            padding: 15px; /* Increased padding */
            background-color: #f1f1f1;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
    
        .brand-list a:hover {
            background-color: #e0e0e0;
        }
    
        .brand-list img {
            max-width: 60px; /* Increased size */
            margin-bottom: 8px; /* Increased margin */
        }
        
        
    
  /* Why choose */
  .why-us {
    padding: 60px 30px;
    background-color: #f9f9f9;
    position: relative; /* Allows positioning within the section */
    text-align: center;
}

.why-us-container {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    position: relative;
}

/* Image container for top-right alignment */
.why-us-image {
    position: absolute;
    top: 60px;
    right: 0;
    width: 40%; /* Adjust image width */
}

.why-us-image img {
    width: 35%;
    height: auto;
    object-fit: cover;
    border-radius: 10px; /* Optional: Rounded corners */
}

/* Content styling */
.why-us-content {
    width: 60%; /* Adjust content width */
    margin-right: 40%;
}

.why-us h2 {
    font-size: 36px;
    margin-bottom: 20px;
    color: #333;
}

.benefits {
    margin-bottom: 20px;
}

.benefits h3 {
    font-size: 24px;
    color: #333;
}

.benefits p {
    font-size: 16px;
    color: #555;
}
 
    
        /* Customer reviews */
        .reviews {
            padding: 60px 20px; /* Increased padding */
            background-color: #fff;
            text-align: center;
        }
    
        .reviews h2 {
            font-size: 36px; /* Increased font size */
            margin-bottom: 30px;
        }
    
        .reviews .review {
            background-color: #f9f9f9;
            padding: 25px; /* Increased padding */
            margin-bottom: 25px; /* Increased margin */
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            max-width: 700px; /* Increased max-width */
            margin-left: auto;
            margin-right: auto;
        }
    
        .rating-overview {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px; /* Increased gap */
            margin: 25px 0; /* Increased margin */
            font-size: 22px; /* Increased font size */
            font-weight: bold;
        }
    
        .rating-overview img {
            width: 30px; /* Increased size */
            height: 30px; /* Increased size */
        }
    
        .rating-overview .rating-text {
            color: black;
        }
        /* Footer styles */
       /* Footer styles */
       footer {
            background: linear-gradient(135deg, #333, #555);
            color: #fff;
            padding: 60px 20px 20px;
            text-align: center;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
            padding-bottom: 40px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .footer-section {
            flex: 1;
            min-width: 200px;
            text-align: left;
        }

        .footer-section h3 {
            font-size: 20px;
            margin-bottom: 15px;
            color: #fff;
        }

        .footer-section p {
            font-size: 16px;
            color: #ddd;
            line-height: 1.6;
        }

        .footer-section ul {
            list-style: none;
            padding: 0;
        }

        .footer-section ul li {
            margin-bottom: 10px;
        }

        .footer-section ul li a {
            color: #ddd;
            text-decoration: none;
            font-size: 16px;
            transition: color 0.3s ease;
        }

        .footer-section ul li a:hover {
            color: #007bff;
        }

        .social-icons {
            display: flex;
            gap: 15px;
        }

        .social-icons a img {
            width: 30px;
            height: 30px;
            transition: transform 0.3s ease;
        }

        .social-icons a:hover img {
            transform: scale(1.2);
        }

        .footer-bottom {
            margin-top: 20px;
            font-size: 14px;
            color: #ddd;
        }

        /* Simple styles for dropdown */
        .dropdown {
            display: none;
            position: absolute;
            background-color: white;
            border: 1px solid #ccc;
            z-index: 1;
        }
        .dropdown a {
            display: block;
            padding: 8px;
            text-decoration: none;
            color: black;
        }
        .dropdown a:hover {
            background-color: #f1f1f1;
        }
        .username {
            cursor: pointer;
        }

        /* Add these styles to your existing CSS */
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background: white;
            border: 1px solid #ddd;
            border-radius: 0 0 5px 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: none;
            z-index: 1000;
            max-height: 300px;
            overflow-y: auto;
        }

        .search-result-item {
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
        }

        .search-result-item:hover {
            background-color: #f5f5f5;
        }
    </style>
    
    <script>
        function toggleDropdown() {
            var dropdown = document.getElementById("userDropdown");
            dropdown.style.display = dropdown.style.display === "block" ? "none" : "block";
        }

        window.onclick = function(event) {
            if (!event.target.matches('.username')) {
                var dropdowns = document.getElementsByClassName("dropdown");
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.style.display === 'block') {
                        openDropdown.style.display = 'none';
                    }
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const searchResults = document.getElementById('searchResults');
            let timeoutId;

            searchInput.addEventListener('input', function() {
                clearTimeout(timeoutId);
                const query = this.value;

                if (query.length < 2) {
                    searchResults.style.display = 'none';
                    return;
                }

                // Add debounce to prevent too many requests
                timeoutId = setTimeout(() => {
                    fetch(`search_ajax.php?query=${encodeURIComponent(query)}`)
                        .then(response => response.json())
                        .then(data => {
                            searchResults.innerHTML = '';
                            if (data.length > 0) {
                                data.forEach(item => {
                                    const div = document.createElement('div');
                                    div.className = 'search-result-item';
                                    div.textContent = `${item.brand} ${item.model} - ${item.year}`;
                                    div.addEventListener('click', () => {
                                        window.location.href = `vehicle_details.php?id=${item.vehicle_id}`;
                                    });
                                    searchResults.appendChild(div);
                                });
                                searchResults.style.display = 'block';
                            } else {
                                searchResults.style.display = 'none';
                            }
                        })
                        .catch(error => console.error('Error:', error));
                }, 300);
            });

            // Hide search results when clicking outside
            document.addEventListener('click', function(e) {
                if (!searchResults.contains(e.target) && e.target !== searchInput) {
                    searchResults.style.display = 'none';
                }
            });
        });
    </script>
</head>
<body>
    <header>
        <!-- Logo -->
        <div class="logo">
            <img src="images/logo3.png" alt="Logo">
            <h1>WheeledDeal</h1>
        </div>

        <!-- Search bar -->
        <div class="search-container">
            <form action="search.php" method="GET" id="searchForm">
                <input type="text" name="query" id="searchInput" placeholder="Search Cars or Brands e.g., Swift, Maruti">
                <button type="submit">
                    <!-- <img src="images/search.png" alt="Search"> -->
                </button>
            </form>
            <div id="searchResults" class="search-results"></div>
        </div>

        <!-- Navigation -->
        <nav>
            <div class="icons">
                <!-- <a href="wishlist.html">
                    <img src="images/wishlist.jpg">
                </a> -->
                <a href="buyer_dashboard.php">Buy Used Cars</a>
                <img src="images/login.png"><a href="login.php">Login</a>/<a href="signup.php">Sign Up</a>
            </div>
        </nav>

        <?php if (isset($_SESSION['name'])): ?>
            <div>
            <span class="welcome-message">Welcome,</span>
                <span class="username" onclick="toggleDropdown()"><?php echo htmlspecialchars($_SESSION['name']); ?></span>
                <div id="userDropdown" class="dropdown">
                    <a href="profile.php">View Profile</a>
                    <a href="logout.php">Logout</a>
                </div>
            </div>
        <?php else: ?>
            <p></p>
        <?php endif; ?>
    </header>

    <section class="hero">
        <h1>Achieve the best price for your car,<br>right from your doorstep.</h1>
        <p>Your Trusted Marketplace for Quality Automobiles</p>
        <img src="images/carbuyers.svg"><b>Verified</b> Car Buyers
        <img src="images/zero.svg"><b>Zero</b> Commission
        <img src="images/Nounwanted.svg"><b>No Unwanted</b> Calls
        <div class="hero-image">
            <img src="images/thumbsUpmanDesktop.webp">
        </div>
       
    </section>

    <div class="container">
        <h2>Enter your car registration</h2>
        <div class="input-group">
            <!-- //<input type="text" placeholder="Enter Your Car No. (DL03-CW-3121)"> -->
            <form action="switch_role.php" method="POST">
                <button type="submit" name="role" value="seller">Sell My Car</button>
            </form>
            
            <!-- <a href="seller_dashboard.php">
                <button type="button">Sell My Car</button>
            </a> -->
            
        </div>

        <div class="divider"></div>

        <div class="brand-section">
            <h3>Let's select your car brand</h3>
            <div class="brand-list">
                <?php
                // Fetch brands from the database
                $sql = "SELECT DISTINCT brand FROM tbl_vehicles"; // Ensure 'brand' is the correct column name
                $result = $conn->query($sql);

                // Check if the query was successful
                if ($result === false) {
                    echo "Error: " . $conn->error; // Display the error message
                } else {
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $brand = htmlspecialchars($row['brand']);
                            if (!empty($brand)) { // Ensure it's not empty
                                echo "<a href='brand_vehicles.php?brand=$brand' class='brand-box'>$brand</a>";
                            }
                        }
                    } else {
                        echo "No brands found.";
                    }
                }
                ?>
            </div>
        </div>
    </div>

    <section class="how-it-works">
        <h1 style="text-align:center; font-size: 33px;">How WheeledDeal Works?</h1>

        <div class="steps">
            <div class="step">
                <img src="images/share.svg"><h3>Share your car details</h3>
                <p>Enter your car details and upload photos of your car.</p>
            </div>
            <div class="step">
                <img src="images/details.svg"><h3>Your car details will be reviewed</h3>
                <p>Get an instant offer or connect with potential buyers.</p>
            </div>
            <div class="step">
                <img src="images/connect.svg"><h3>Connect with interested buyers</h3>
                <p>We arrange buyer connections to help you sell quickly.</p>
            </div>
            <div class="step">
                <img src="images/nospaming.svg"><h3>No spamming</h3>
                <p>We ensure a hassle-free and safe transaction for you.</p>
            </div>
            <div class="step">
                <img src="images/sell.svg"><h3>Sell your car to buyer</h3>
                <p>Sell your car at the agreed price with no commission fees, ensuring you get the full amount offered..</p>
            </div>
        </div>
    </section>

    <section class="why-us">
        <h2>Why Choose Us?</h2>
        <div class="benefits">
            <h3>Wide Selection of Vehicles</h3>
            <p>Browse through a variety of cars from different makes and models.</p>
        </div>
        <div class="benefits">
            <h3>Secure Transactions</h3>
            <p>We ensure secure transactions for buying and selling vehicles through our platform.</p>
        </div>
        <div class="benefits">
            <h3>Easy to Use</h3>
            <p>Our platform is designed to make buying and selling as simple as possible.</p>
        </div>
        <div class="why-us-image">
            <img src="images/howitworksimg.webp">
        </div>
    </section>

    <section class="reviews">
        <h2>Customer Reviews</h2>
        <div class="rating-overview">
            <img src="images/review.svg" alt="Review Icon">
            <span class="rating">4.8</span>
            <img src="images/star.png" alt="Star Icon">
            <span class="rating-text">4.8 out of 5</span>
        </div>
        <div class="review">
            <p>"Excellent service! Sold my car in a day!" - A Customer</p>
        </div>
        <div class="review">
            <p>"Quick and hassle-free process." - Another Customer</p>
        </div>
        <div class="review">
            <p>"Highly recommended for car sellers." - Happy Seller</p>
        </div>
    </section>
    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3>About Us</h3>
                <p>WheeledDeal is your trusted marketplace for buying and selling quality automobiles. We ensure a seamless and secure experience for all our users.</p>
            </div>
            <div class="footer-section">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="about.html">About Us</a></li>
                    <li><a href="contact.html">Contact</a></li>
                    <li><a href="privacy.html">Privacy Policy</a></li>
                    <li><a href="terms.html">Terms of Service</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Contact Us</h3>
                <p>Email: support@wheeleddeal.com</p>
                <p>Phone: +1 (123) 456-7890</p>
            </div>
            <div class="footer-section">
                <!-- <h3>Follow Us</h3>
                <div class="social-icons">
                    <a href="#" target="_blank"><img src="images/facebook.jpg" alt="Facebook"></a>
                    <a href="#" target="_blank"><img src="images/twitter.png" alt="Twitter"></a>
                    <a href="#" target="_blank"><img src="images/instagram.png" alt="Instagram"></a>
                    <a href="#" target="_blank"><img src="images/linked.png" alt="LinkedIn"></a>
                </div> -->
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2025 WheeledDeal. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
