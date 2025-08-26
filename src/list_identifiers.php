<?php
$host = "db";
$dbname = "testdb";
$user = "root";
$pass = "root";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT DISTINCT identifier FROM dataset ORDER BY identifier ASC");
    $identifiers = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    die("Errore DB: " . $e->getMessage());
}
include 'header.php'; //intestazione della pagina
?>
</head>
<body>
    <h1>List of devices stored into the database</h1>
    <P>
        select the device to view/modify or add a new device </p>
    <ul>
        <?php foreach($identifiers as $id): ?>
            <li>
                <?php 
                    $identif=htmlspecialchars($id);
                    #echo <a href="list_identifiers2.php?identifier=htmlspecialchars($id);" > htmlspecialchars($id); </a> 
                    #echo "<a href=\"list_identifiers2.php?identifier=$identif\" > $identif </a> "
                    echo "<a href=\"storeTable2.php?identifier=$identif\" > $identif </a> "
                ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <a href="storeTable2.php"> <button> Add a new device </button></a>
    <?php include 'footer.php'; ?>
