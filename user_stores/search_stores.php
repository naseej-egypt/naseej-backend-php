<?php
/**
 * Naseej User App - Search Stores
 * GET/POST: /stores/search_stores.php
 * Parameters: query (search term), city, region, sort_by
 */

include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";

handleCorsHeaders();

$db = new Database();
$conn = $db->connect();

try {
    // Get search parameters
    $searchQuery = isset($_REQUEST['query']) ? trim($_REQUEST['query']) : '';
    $city = isset($_REQUEST['city']) ? trim($_REQUEST['city']) : '';
    $region = isset($_REQUEST['region']) ? trim($_REQUEST['region']) : '';
    
    // Build WHERE conditions
    $whereConditions = ["s.is_active = 1"];
    $params = [];
    
    // Search query
    if (!empty($searchQuery)) {
        $whereConditions[] = "(s.name LIKE ? OR s.address LIKE ? OR s.manager_name LIKE ?)";
        $searchTerm = "%{$searchQuery}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // City filter
    if (!empty($city) && $city !== 'All') {
        $whereConditions[] = "s.address LIKE ?";
        $params[] = "%{$city}%";
    }
    
    // Region filter
    if (!empty($region) && $region !== 'All') {
        $whereConditions[] = "s.address LIKE ?";
        $params[] = "%{$region}%";
    }
    
    $whereSQL = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
    
    // Build ORDER BY
    $orderBy = "ORDER BY ";
    switch ($sortBy) {
        case 'distance':
            $orderBy .= "s.name ASC";
            break;
        case 'rating':
            $orderBy .= "s.name ASC";
            break;
        case 'name':
        default:
            $orderBy .= "s.name ASC";
            break;
    }
    
    // Get stores
    $stmt = $conn->prepare("
        SELECT 
            s.id,
            s.name,
            s.address,
            s.phone,
            s.email,
            s.latitude,
            s.longitude,
            s.image_url,
            s.manager_name,
            s.manager_phone,
            s.opening_time,
            s.closing_time,
            s.working_days,
            COUNT(DISTINCT dm.id) as delivery_men_count,
            (
                SELECT COUNT(*) 
                FROM orders o 
                WHERE o.store_id = s.id 
                AND o.status = 'delivered'
            ) as completed_orders
        FROM stores s
        LEFT JOIN delivery_men dm ON s.id = dm.store_id AND dm.is_active = 1
        {$whereSQL}
        GROUP BY s.id
        {$orderBy}
        LIMIT 50
    ");
    
    $stmt->execute($params);
    $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process stores
    $processedStores = [];
    $extractedCities = [];
    $extractedRegions = [];
    
    foreach ($stores as $store) {
        $imageUrl = null;
        if (!empty($store['image_url'])) {
            $imageUrl = getImageUrl($store['image_url'], 'stores');
        }
        
        // Parse working days
        $workingDays = parseJsonArray($store['working_days']);
        
        // FIXED: Calculate if store is currently open with proper 24-hour logic
        $now = new DateTime('now', new DateTimeZone('Africa/Cairo')); // Alexandria, Egypt timezone
        $currentDay = $now->format('l'); // Monday, Tuesday, etc.
        $currentTime = $now->format('H:i:s');
        
        $openingTime = $store['opening_time'];
        $closingTime = $store['closing_time'];
        
        $isOpen = false;
        
        if (in_array($currentDay, $workingDays)) {
            // Check if store operates past midnight (e.g., 09:00 - 03:00)
            if ($closingTime < $openingTime) {
                // Store closes after midnight
                $isOpen = ($currentTime >= $openingTime || $currentTime <= $closingTime);
            } else {
                // Normal operating hours within same day
                $isOpen = ($currentTime >= $openingTime && $currentTime <= $closingTime);
            }
        }
        
        // Extract city and region from address
        $address = $store['address'];
        $cityFromAddress = extractCityFromAddress($address);
        $regionFromAddress = extractRegionFromAddress($address);
        
        // Collect unique cities and regions
        if ($cityFromAddress && !in_array($cityFromAddress, $extractedCities)) {
            $extractedCities[] = $cityFromAddress;
        }
        if ($regionFromAddress && !in_array($regionFromAddress, $extractedRegions)) {
            $extractedRegions[] = $regionFromAddress;
        }
        
        $processedStores[] = [
            'id' => (int)$store['id'],
            'name' => $store['name'],
            'description' => "Store located in {$store['address']}",
            'address' => $store['address'],
            'city' => $cityFromAddress,
            'region' => $regionFromAddress,
            'location' => [
                'latitude' => (float)$store['latitude'],
                'longitude' => (float)$store['longitude']
            ],
            'phone' => $store['phone'],
            'email' => $store['email'],
            'image_url' => $imageUrl,
            'rating' => 4.5,
            'review_count' => (int)$store['completed_orders'],
            'is_open' => $isOpen,
            'opening_hours' => [
                substr($store['opening_time'], 0, 5) . ' - ' . substr($store['closing_time'], 0, 5)
            ],
            'working_days' => $workingDays,
            'distance' => 0.0,
            'manager' => [
                'name' => $store['manager_name'],
                'phone' => $store['manager_phone']
            ],
            'delivery_men_count' => (int)$store['delivery_men_count']
        ];
    }
    
    // Sort cities and regions alphabetically
    sort($extractedCities);
    sort($extractedRegions);
    
    // Add "All" option at the beginning
    array_unshift($extractedCities, 'All');
    array_unshift($extractedRegions, 'All');
    
    $responseData = [
        'stores' => $processedStores,
        'filters' => [
            'available_cities' => $extractedCities,
            'available_regions' => $extractedRegions
        ],
        'search_meta' => [
            'query' => $searchQuery,
            'total_results' => count($processedStores),
            'city_filter' => $city,
            'region_filter' => $region,
            'sort_by' => $sortBy
        ]
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("Search stores error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to search stores");
}

// Helper function to extract city from address
function extractCityFromAddress($address) {
    $cities = ['Cairo', 'Giza', 'Alexandria', 'Luxor', 'Aswan', 'Port Said', 'Suez', 'Mansoura', 'Tanta', 'Asyut'];
    
    foreach ($cities as $city) {
        if (stripos($address, $city) !== false) {
            return $city;
        }
    }
    
    // If no known city found, try to extract from address (take last part after last comma)
    $parts = explode(',', $address);
    if (count($parts) > 0) {
        return trim($parts[count($parts) - 1]);
    }
    
    return 'Unknown';
}

// Helper function to extract region from address
function extractRegionFromAddress($address) {
    $regions = ['Downtown', 'Nasr City', 'Heliopolis', 'Maadi', 'Zamalek', 'Dokki', 'Mohandessin', 'New Cairo'];
    
    foreach ($regions as $region) {
        if (stripos($address, $region) !== false) {
            return $region;
        }
    }
    
    // Try to extract first part of address as region
    $parts = explode(',', $address);
    if (count($parts) >= 2) {
        return trim($parts[0]);
    }
    
    return 'Central';
}
?>