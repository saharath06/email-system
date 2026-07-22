<?php
$stylePath = __DIR__ . '/../assets/style.css';

if (file_exists($stylePath)) {
    echo '<style>';
    readfile($stylePath);
    echo '</style>';
} else {
    echo '<style>
        body {
            font-family: Arial, sans-serif;
            background: #0a0a1a;
            color: #fff;
            direction: rtl;
            margin: 0;
            padding: 20px;
        }
    </style>';
}
?>