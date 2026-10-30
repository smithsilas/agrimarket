<?php
/**
 * Mock data populator.   Run from the project root:   php database/seed.php
 * (Refuses to run from a browser. Re-running wipes and re-creates the demo data.)
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Run this script from the command line: php database/seed.php\n"); }

require_once __DIR__ . '/../config/db.php';
$pdo = db();

$demoPassword = 'Password123!';
$hash = password_hash($demoPassword, PASSWORD_DEFAULT);

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['order_items', 'orders', 'products', 'categories', 'users'] as $t) { $pdo->exec("TRUNCATE TABLE $t"); }
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

/* ---------------- categories ---------------- */
$cats = [
    ['Vegetables',       'vegetables', 'fa-carrot',      'emerald'],
    ['Fruits',           'fruits',     'fa-apple-whole', 'red'],
    ['Grains & Cereals', 'grains',     'fa-wheat-awn',   'amber'],
    ['Tubers & Roots',   'tubers',     'fa-seedling',    'yellow'],
    ['Herbs & Spices',   'herbs',      'fa-leaf',        'green'],
    ['Bulk Packages',    'bulk',       'fa-box',         'purple'],
];
$st = $pdo->prepare('INSERT INTO categories (name, slug, icon, color, sort_order) VALUES (?,?,?,?,?)');
$catId = [];
foreach ($cats as $i => [$n, $s, $ic, $co]) { $st->execute([$n, $s, $ic, $co, $i]); $catId[$s] = (int) $pdo->lastInsertId(); }

/* ---------------- users ---------------- */
$u = $pdo->prepare('INSERT INTO users (role, first_name, last_name, email, phone, password_hash, farm_name, county, specialty, avatar) VALUES (?,?,?,?,?,?,?,?,?,?)');
$users = [
    ['admin',  'Site',     'Admin',      'admin@agrimarket.co.ke',   '254700000001', null, null, null, null],
    ['buyer',  'Grace',    'Achieng',    'buyer@example.com',        '254712345678', null, null, null, null],
    ['farmer', 'Wanjiru',  'Kamau',      'nyeri@agrimarket.co.ke',   '254722000001', 'Nyeri Organic Farm',       'Nyeri',     'Tomatoes & Leafy Greens', 'farmers/wanjiru.svg'],
    ['farmer', 'Kiprono',  'Cheruiyot',  'rift@agrimarket.co.ke',    '254722000002', 'Rift Valley Grains',       'Nakuru',    'Maize, Wheat & Beans',    'farmers/kiprono.svg'],
    ['farmer', 'Mwangi',   'Njoroge',    'kinangop@agrimarket.co.ke','254722000003', 'Kinangop Highlands',       'Nyandarua', 'Potatoes & Cabbages',     'farmers/mwangi.svg'],
    ['farmer', 'Amina',    'Hassan',     'greens@agrimarket.co.ke',  '254722000004', 'Nakuru Greens',            'Nakuru',    'Carrots & Onions',        null],
    ['farmer', 'Peter',    'Mutua',      'kiambu@agrimarket.co.ke',  '254722000005', 'Kiambu Fresh',             'Kiambu',    'Kales, Bananas & Greens', null],
    ['farmer', 'Esther',   'Mwende',     'spices@agrimarket.co.ke',  '254722000006', 'Machakos Spices',          'Machakos',  'Capsicums, Mangoes & Herbs', null],
];
$uid = [];
foreach ($users as [$role, $fn, $ln, $em, $ph, $farm, $county, $spec, $av]) {
    $u->execute([$role, $fn, $ln, $em, $ph, $hash, $farm, $county, $spec, $av]);
    $uid[$farm ?: $em] = (int) $pdo->lastInsertId();
}

/* ---------------- products ---------------- */
// [farm, category, name, price, unit, stock, county, image, rating, reviews, featured, description]
$products = [
 ['Nyeri Organic Farm','vegetables','Fresh Tomatoes',80,'kg',400,'Nyeri','products/tomatoes.svg',4.8,42,1,'Hand-picked ripe red farm tomatoes, harvested from open fields in Nyeri. Perfect for stews, sauces and fresh salads. Free from harmful chemical residue.'],
 ['Nakuru Greens','vegetables','Organic Carrots',60,'kg',300,'Nakuru','products/carrots.svg',4.6,18,1,'Sweet, crunchy carrots grown without synthetic pesticides in the cool Nakuru highlands. Washed and sorted by size.'],
 ['Rift Valley Grains','grains','Yellow Maize',120,'2kg bag',250,'Nakuru','products/maize.svg',4.9,65,1,'Dry, sun-cured yellow maize from the Rift Valley breadbasket. Clean grain, ideal for posho, githeri or animal feed.'],
 ['Kinangop Highlands','tubers','Irish Potatoes',1500,'50kg bag',60,'Nyandarua','products/potatoes.svg',4.7,31,1,'Firm, starchy Shangi potatoes straight from Kinangop. Great for chips, mashing and boiling. Sold in 50kg bags for households and hotels.'],
 ['Kiambu Fresh','vegetables','Fresh Kales (Sukuma)',30,'bunch',500,'Kiambu','products/kale.svg',4.5,54,0,'Tender sukuma wiki cut on the morning of dispatch. Each bunch is generous enough for a family meal.'],
 ['Machakos Spices','vegetables','Red Capsicum',110,'kg',150,'Machakos','products/capsicum.svg',4.4,22,0,'Glossy red bell peppers with thick, sweet flesh. Excellent in stir-fries, salads and roasts.'],
 ['Kiambu Fresh','fruits','Ripe Bananas',90,'bunch',220,'Kiambu','products/bananas.svg',4.5,37,0,'Naturally ripened Williams bananas. Sweet, soft and perfect for breakfast or smoothies.'],
 ['Nyeri Organic Farm','fruits','Hass Avocados',150,'kg',180,'Nyeri','products/avocados.svg',4.8,73,0,'Creamy Hass avocados from Nyeri orchards. Sold at the ready-to-ripen stage for a few days of shelf life.'],
 ['Nakuru Greens','vegetables','Red Onions',100,'kg',320,'Nakuru','products/onions.svg',4.3,29,0,'Pungent red onions, well cured for long storage. Strong flavour for cooking and kachumbari.'],
 ['Rift Valley Grains','grains','Rosecoco Beans',160,'kg',210,'Nakuru','products/beans.svg',4.7,48,0,'Speckled rosecoco beans, hand-sorted and free of stones. Cooks soft and creamy.'],
 ['Machakos Spices','fruits','Apple Mangoes',180,'kg',140,'Machakos','products/mangoes.svg',4.9,61,0,'Juicy, fibre-free apple mangoes from Machakos orchards, picked at peak sweetness.'],
 ['Kinangop Highlands','vegetables','Green Cabbages',50,'head',260,'Nyandarua','products/cabbage.svg',4.4,26,0,'Dense, crisp cabbage heads from the cool Kinangop plateau. Keeps well for a week or more.'],
 ['Machakos Spices','herbs','Fresh Coriander (Dhania)',20,'bunch',400,'Machakos','products/coriander.svg',4.6,33,0,'Aromatic coriander cut to order. Add at the end of cooking for a fresh, citrusy lift.'],
 ['Rift Valley Grains','bulk','Maize Grain – 90kg Sack',4200,'90kg sack',45,'Nakuru','products/maize.svg',4.8,19,0,'Wholesale sack of dry maize for millers, schools, hotels and institutions. Delivery by lorry on request.'],
];
$p = $pdo->prepare('INSERT INTO products (farmer_id, category_id, name, description, price, unit, stock, county, image, rating, reviews_count, featured) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
foreach ($products as [$farm, $cat, $name, $price, $unit, $stock, $county, $img, $rating, $reviews, $feat, $desc]) {
    $p->execute([$uid[$farm], $catId[$cat], $name, $desc, $price, $unit, $stock, $county, $img, $rating, $reviews, $feat]);
}

echo "Seeded " . count($products) . " products, " . count($users) . " users, " . count($cats) . " categories.\n\n";
echo "Demo logins (password for all: $demoPassword)\n";
echo "  Admin : admin@agrimarket.co.ke\n";
echo "  Buyer : buyer@example.com\n";
echo "  Farmer: nyeri@agrimarket.co.ke  (also rift@, kinangop@, greens@, kiambu@, spices@ …@agrimarket.co.ke)\n";
echo "\nChange these credentials before deploying anywhere public.\n";
