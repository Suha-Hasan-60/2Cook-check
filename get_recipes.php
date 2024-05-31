<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

$servername = "localhost";
$username = "root"; // Change this to your database username
$password = ""; // Change this to your database password
$dbname = "cook"; // Change this to your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Check if 'cuisine' parameter is set
if (!isset($_POST['cuisine'])) {
    error_log("Cuisine parameter is not set.");
    echo json_encode(['error' => 'Cuisine parameter is not set']);
    exit();
}

$cuisine = $_POST['cuisine']; // Get the cuisine from POST data

// Prepare SQL statement
$sql = "SELECT recipe_name, rec_info, main_ing, video_url, image_url FROM recipe WHERE cuisine = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log("Prepare failed: " . $conn->error);
    echo json_encode(['error' => 'SQL prepare failed']);
    exit();
}

// Bind parameters and execute
$stmt->bind_param("s", $cuisine);
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    echo json_encode(['error' => 'SQL execute failed']);
    exit();
}

// Get result set
$result = $stmt->get_result();
if ($result === false) {
    error_log("Get result failed: " . $stmt->error);
    echo json_encode(['error' => 'Get result failed']);
    exit();
}

// Fetch recipes
$recipes = [];
while ($row = $result->fetch_assoc()) {
    $main_ing = json_decode($row['main_ing'], true);
    if ($main_ing === null) {
        error_log("JSON decode failed for main_ing: " . json_last_error_msg());
    }
    $ingredients = [];
    foreach ($main_ing as $ingredient) {
        // Fetch EcommerceURL for each ingredient from the Ingredients table
        $ingredientQuery = "SELECT EcommerceURL FROM Ingredients WHERE IngredientName = ?";
        $ingredientStmt = $conn->prepare($ingredientQuery);
        if ($ingredientStmt === false) {
            error_log("Prepare failed for ingredient query: " . $conn->error);
            continue;
        }
        $ingredientStmt->bind_param("s", $ingredient);
        if (!$ingredientStmt->execute()) {
            error_log("Execute failed for ingredient query: " . $ingredientStmt->error);
            continue;
        }
        $ingredientResult = $ingredientStmt->get_result();
        $ingredientData = $ingredientResult->fetch_assoc();
        $ecommerceURL = $ingredientData['EcommerceURL'] ?? '';

        $ingredients[] = [
            'name' => $ingredient,
            'url' => $ecommerceURL
        ];
    }
    $row['main_ing'] = $ingredients;
    $recipes[] = $row;
}

// Close statement and connection
$stmt->close();
$conn->close();

// Send JSON response
header('Content-Type: application/json');
echo json_encode($recipes);
?>
