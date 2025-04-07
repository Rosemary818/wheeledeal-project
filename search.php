<?php
require_once 'db_connect.php';

$search_results = [];
$search_query = '';

if (isset($_GET['query'])) {
    $search_query = $_GET['query'];
    $search = '%' . $search_query . '%';
    
    // Updated query to use new table structure and exclude sold vehicles
    $stmt = $conn->prepare("SELECT v.*, vs.*, 
                           GROUP_CONCAT(p.photo_file_path) as photos,
                           CASE WHEN w.wishlist_id IS NOT NULL THEN 1 ELSE 0 END as is_wishlisted
                           FROM tbl_vehicles v 
                           LEFT JOIN tbl_vehicle_specifications vs ON v.vehicle_id = vs.vehicle_id
                           LEFT JOIN tbl_photos p ON v.vehicle_id = p.vehicle_id
                           LEFT JOIN tbl_wishlist w ON v.vehicle_id = w.vehicle_id AND w.user_id = ?
                           LEFT JOIN tbl_transactions t ON v.vehicle_id = t.vehicle_id AND t.status = 'completed'
                           WHERE (v.brand LIKE ? 
                           OR v.model LIKE ? 
                           OR v.year LIKE ?)
                           AND t.transaction_id IS NULL
                           GROUP BY v.vehicle_id
                           ORDER BY v.brand, v.model");
    
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    $stmt->bind_param("isss", $user_id, $search, $search, $search);
    $stmt->execute();
    $result = $stmt->get_result();
    $search_results = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results - WheeledDeal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
        }

        /* Header styles */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            background-color: white;
            border-bottom: 1px solid #ddd;
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
            font-size: 30px;
            margin: 0;
            color: #333;
        }
        
        /* Back Button in Header */
        .back-button {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            background: #f1f1f1;
            color: #333;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s ease;
        }

        .back-button i {
            margin-right: 10px;
        }

        .back-button:hover {
            background: #e0e0e0;
        }

        /* Search Results Container */
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }

        .search-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .search-header h1 {
            color: #344055;
            margin: 0;
            font-size: 24px;
        }

        /* Grid Layout for Search Results */
        .search-results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px 0;
        }

        /* Vehicle Card Styles */
        .vehicle-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .vehicle-card:hover {
            transform: translateY(-5px);
        }

        .vehicle-image-container {
            position: relative;
            width: 100%;
        }

        .vehicle-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .ev-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background-color: #4CAF50;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            z-index: 2;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .vehicle-card-content {
            padding: 15px;
        }

        .vehicle-card h3 {
            color: #344055;
            margin: 0 0 10px 0;
            font-size: 20px;
        }

        .vehicle-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            color: #666;
            font-size: 14px;
        }

        .price {
            color: #28a745;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .view-details {
            display: inline-block;
            background: #ff5722;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s ease;
            width: 100%;
            text-align: center;
            box-sizing: border-box;
        }

        .view-details:hover {
            background: #2a3444;
        }

        .no-results {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .no-results i {
            font-size: 48px;
            color: #666;
            margin-bottom: 20px;
        }

        .no-results p {
            color: #666;
            font-size: 18px;
            margin: 0;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .search-results-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="logo">
            <img src="images/logo3.png" alt="WheeledDeal Logo">
            <h1>WheeledDeal</h1>
        </div>
        <!-- Back Button moved to header right side -->
        <a href="index.php" class="back-button">
            <i class="fas fa-arrow-left"></i> Back to Home
        </a>
    </header>

    <div class="container">
        <!-- Search Header -->
        <div class="search-header">
            <h1>Search Results for "<?php echo htmlspecialchars($search_query); ?>"</h1>
        </div>
        
        <!-- Search Results Grid -->
        <div class="search-results-grid">
            <?php if (count($search_results) > 0): ?>
                <?php foreach ($search_results as $vehicle): ?>
                    <?php 
                        $photos = explode(',', $vehicle['photos']);
                        $mainPhoto = !empty($photos[0]) ? $photos[0] : 'images/no-image.png';
                        $isEV = isset($vehicle['vehicle_type']) && $vehicle['vehicle_type'] == 'EV';
                    ?>
                    <div class="vehicle-card">
                        <div class="vehicle-image-container">
                            <img src="<?php echo htmlspecialchars($mainPhoto); ?>" alt="<?php echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']); ?>">
                            <?php if ($isEV): ?>
                                <span class="ev-badge">EV</span>
                            <?php endif; ?>
                        </div>
                        <div class="vehicle-card-content">
                            <h3><?php echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']); ?></h3>
                            <div class="vehicle-info">
                                <span><?php echo htmlspecialchars($vehicle['year']); ?></span>
                                <span><?php echo htmlspecialchars(number_format($vehicle['kilometer'])); ?> km</span>
                                <span><?php echo htmlspecialchars($vehicle['fuel_type']); ?></span>
                            </div>
                            <div class="price">₹<?php echo htmlspecialchars(number_format($vehicle['price'])); ?></div>
                            <a href="vehicle_details.php?id=<?php echo $vehicle['vehicle_id']; ?>" class="view-details">View Details</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <p>No vehicles found for "<?php echo htmlspecialchars($search_query); ?>". Try a different search term.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 