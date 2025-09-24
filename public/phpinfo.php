<?php
// PHP Configuration Info for Upload Diagnostics
// Access via: http://localhost:8000/phpinfo.php

echo "<h1>PHP Upload Configuration</h1>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Setting</th><th>Current Value</th><th>Recommended</th></tr>";

$settings = [
    'upload_max_filesize' => ['current' => ini_get('upload_max_filesize'), 'recommended' => '25M'],
    'post_max_size' => ['current' => ini_get('post_max_size'), 'recommended' => '30M'],
    'max_execution_time' => ['current' => ini_get('max_execution_time'), 'recommended' => '300'],
    'memory_limit' => ['current' => ini_get('memory_limit'), 'recommended' => '256M'],
    'max_file_uploads' => ['current' => ini_get('max_file_uploads'), 'recommended' => '20'],
    'file_uploads' => ['current' => ini_get('file_uploads') ? 'On' : 'Off', 'recommended' => 'On'],
];

foreach ($settings as $setting => $values) {
    $color = ($values['current'] == $values['recommended'] || 
              ($setting == 'upload_max_filesize' && (int)$values['current'] >= 25) ||
              ($setting == 'post_max_size' && (int)$values['current'] >= 30) ||
              ($setting == 'memory_limit' && (int)$values['current'] >= 256)) ? 'green' : 'red';
    
    echo "<tr>";
    echo "<td><strong>$setting</strong></td>";
    echo "<td style='color: $color'>{$values['current']}</td>";
    echo "<td>{$values['recommended']}</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h2>Upload Test</h2>";
echo "<p>Try uploading your 7MB image now. If it fails, the error message will show the exact server limits.</p>";

// Show full phpinfo if needed
echo "<hr><h2>Full PHP Info</h2>";
echo "<p><a href='?full=1'>Click here to see full PHP configuration</a></p>";

if (isset($_GET['full'])) {
    phpinfo();
}
?>
