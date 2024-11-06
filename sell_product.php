<?php
session_start();
header('Content-Type: application/json');

// ตั้งค่าการเชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "project";

$conn = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อฐานข้อมูล
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]));
}

// ตรวจสอบว่าเข้าสู่ระบบแล้วหรือไม่
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

$member_id = $_SESSION['user_id']; // ดึง member_id จากเซสชัน

// รับข้อมูลจากฟอร์ม
$productName = $_POST['productName'];
$productPrice = $_POST['productPrice'];
$productAmount = $_POST['productAmount'];
$productUnit = $_POST['productUnit'];
$productDetails = $_POST['productDetails'];
$address = $_POST['address'];
$category = $_POST['category'];

// ตั้งค่าโฟลเดอร์และชื่อไฟล์สำหรับการอัปโหลดรูปภาพ
$target_dir = "uploads/";
$target_file = $target_dir . basename($_FILES["productPhoto"]["name"]);
$imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

// ประเภทไฟล์และขนาดไฟล์ที่อนุญาต
$allowed_file_types = ['jpg', 'jpeg', 'png', 'gif'];
$max_file_size = 5 * 1024 * 1024; // จำกัดขนาดไฟล์ 5MB

// ตรวจสอบประเภทไฟล์
if (!in_array($imageFileType, $allowed_file_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type']);
    exit();
}

// ตรวจสอบขนาดไฟล์
if ($_FILES["productPhoto"]["size"] > $max_file_size) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds limit']);
    exit();
}

// ย้ายไฟล์ไปยังโฟลเดอร์อัปโหลด
if (move_uploaded_file($_FILES["productPhoto"]["tmp_name"], $target_file)) {
    $productPhoto = $target_file;
} else {
    echo json_encode(['success' => false, 'message' => 'Error uploading file']);
    exit();
}

// เพิ่มข้อมูลสินค้าลงในฐานข้อมูลก่อน
$sql = "INSERT INTO product (product_name, product_price, product_photo, address, product_amount, product_details, product_unit, product_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'มีสินค้า')";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die(json_encode(['success' => false, 'message' => 'Error preparing product insert statement: ' . $conn->error]));
}
$stmt->bind_param("sisssss", $productName, $productPrice, $productPhoto, $address, $productAmount, $productDetails, $productUnit);

if ($stmt->execute()) {
    $product_id = $stmt->insert_id;

    // เพิ่มข้อมูลหมวดหมู่ลงในตาราง type หลังจากเพิ่ม product แล้ว
    $sql = "INSERT INTO type (type_name, product_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE type_id = LAST_INSERT_ID(type_id)";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die(json_encode(['success' => false, 'message' => 'Error preparing category insert statement: ' . $conn->error]));
    }
    $stmt->bind_param("si", $category, $product_id);
    $stmt->execute();
    $type_id = $stmt->insert_id;
    $stmt->close();

    // เพิ่มข้อมูลการขายลงในตาราง selling
    $sellName = $productName;
    $sellPrice = $productPrice;
    $sellAmount = $productAmount;

    $sql = "INSERT INTO selling (sell_name, sell_price, sell_photo, address, sell_amount, member_id, product_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die(json_encode(['success' => false, 'message' => 'Error preparing selling insert statement: ' . $conn->error]));
    }
    $stmt->bind_param("sissiii", $sellName, $sellPrice, $productPhoto, $address, $sellAmount, $member_id, $product_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Product successfully listed']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error listing product']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Error inserting product']);
}

// ปิดการเชื่อมต่อฐานข้อมูล
$stmt->close();
$conn->close();
