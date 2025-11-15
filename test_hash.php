<?php
$hash = '$2y$10$xhtSy8hPuNhTZ4YJodcU8Or3.Iv6dbSVs55CES5Xyl9uUlv0.I18e';

$passwords = ['please', 'ashiru123', 'password', 'admin', '123456', 'please1', 'admin_section1'];

foreach ($passwords as $pwd) {
    if (password_verify($pwd, $hash)) {
        echo "Password is: $pwd\n";
        exit;
    }
}

echo "Password not found in list.\n";

// Show hash of 'please'
echo "Hash of 'please': " . password_hash('please', PASSWORD_DEFAULT) . "\n";
?>