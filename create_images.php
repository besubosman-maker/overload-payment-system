<?php
// Script to create placeholder profile images
$profiles = [
    'admin' => ['name' => 'Dr. Samuel Teshome', 'bg' => '004080', 'text' => 'ST'],
    'alemayehu' => ['name' => 'Dr. Alemayehu Kebede', 'bg' => '0066cc', 'text' => 'AK'],
    'selam' => ['name' => 'Prof. Selam Tekle', 'bg' => '28a745', 'text' => 'ST'],
    'girma' => ['name' => 'Dr. Girma Mekonnen', 'bg' => 'dc3545', 'text' => 'GM'],
    'hanna' => ['name' => 'Ms. Hanna Yohannes', 'bg' => 'ffc107', 'text' => 'HY']
];

function createProfileImage($name, $bgColor, $text, $filename) {
    // In a real implementation, you'd use GD library to create images
    // For now, we'll create simple placeholder images
    $imageUrl = "https://ui-avatars.com/api/?name=" . urlencode($text) . 
                "&background=" . $bgColor . 
                "&color=fff&size=200&bold=true&format=jpg";
    
    // Download and save the image
    $imageData = file_get_contents($imageUrl);
    file_put_contents("../assets/images/profiles/" . $filename, $imageData);
    
    echo "Created profile image for $name: $filename<br>";
}

foreach ($profiles as $key => $profile) {
    $filename = $key . '_profile.jpg';
    createProfileImage($profile['name'], $profile['bg'], $profile['text'], $filename);
}

echo "Profile images created successfully!";
?>