<?php
$host = "db";
$dbname = "testdb";
$user = "root";
$pass = "root";

// Recupero dei dati se viene passato un identifier
$identifier = $_GET['identifier'] ?? '';
$matrix = array_fill(0, 10, ["", ""]);
$vector = array_fill(0, 10, "");

require_once 'GraficoCartesiano.php';


if ($identifier) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Recuperiamo la matrice e un vettore (tutti uguali, quindi prendiamo il primo)
        $stmt = $pdo->prepare("SELECT row_index, col1, col2, vector_values 
                               FROM dataset 
                               WHERE identifier = :identifier 
                               ORDER BY row_index ASC");
        $stmt->execute(['identifier' => $identifier]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($rows) {
            foreach ($rows as $row) {
                $matrix[$row['row_index']] = [$row['col1'], $row['col2']];
            }
            // Vettore salvato nella prima riga
            $vector = json_decode($rows[0]['vector_values'], true);
        }
    } catch (PDOException $e) {
        die("Errore DB: " . $e->getMessage());
    }
}

include 'header.php';
?>
</head>
<body>
  
<h1> load data from xml file</h1>
<div style="border: 1px solid #ccc; padding: 20px; margin: 20px;">
    <form action="save_xml.php" method="post" enctype="multipart/form-data">
    <input type="file" name="xmlfile" accept=".xml,.txt" required>
    <p>
    <button type="submit">Load and save</button>
</p>
  </form>
</div>
  <p>
<h1>Insert or modify data</h1>
<div style="border: 1px solid #ccc; padding: 20px; margin: 20px;">
<table><tr><td>
  <form method="post" action="save.php">
    <label for="identifier">Part name:</label>
    <input type="text" name="identifier" id="identifier" required value="<?php echo htmlspecialchars($identifier); ?>">
    
    <h3>Time - Current table</h3>
    <table>
      <tr><th>line</th><th>time (ms)</th><th>Current (A)</th></tr>
      <?php for ($i=0; $i<10; $i++): ?>
        <tr>
          <td><?php echo $i+1; ?></td>
          <td>
            <input type="number" step="any" name="matrix[<?php echo $i; ?>][0]" 
                   value="<?php echo htmlspecialchars($matrix[$i][0]); ?>">
          </td>
          <td>
            <input type="number" step="any" name="matrix[<?php echo $i; ?>][1]" 
                   value="<?php echo htmlspecialchars($matrix[$i][1]); ?>">
          </td>
        </tr>
      <?php endfor; ?>
    </table>
      </td>
      <td>  
        <?php
       // lato B
        // Creazione dell'istanza
        $grafico = new GraficoCartesiano($matrix);

        // Personalizzazione (opzionale)
        $grafico->setTitoloGrafico($identifier . " device")
                ->setTitoloAsseX("time (ms)")
                ->setTitoloAsseY("current (A)")
                ->setDimensioni(600, 400)
                ->setMargine(70)
                ->setColori([
                    'punti' => '#ff6600',
                    'linea' => '#009900',
                    'griglia' => '#e0e0e0'
                ]);
                if ($identifier)
                  { ?>
                        <div style="border: 1px solid #ccc; padding: 20px; margin: 20px;">
                        <h2>Protection curve</h2>
                        <?php echo $grafico->generaGrafico(); ?>
                        </div>
                    
                 <?php
                  }
                  else
                  {
                    echo "no image";
                  }
                   
      ?>
      </td>
      </tr></table>
    <h3>Vettore (10 valori)</h3>
    <table>
    <?php for ($j=0; $j<10; $j++): ?>
      <tr><td><?php echo $j+1; ?></td><td>
      <input type="text" name="vector[]" size="5" 
             value="<?php echo htmlspecialchars($vector[$j]); ?>">
    </td></tr>
    <?php endfor; ?>
    </table>

    <br><br>
    <button type="submit">Store into the Database</button>
  </form>
    </div>
    <div style="border: 1px solid #ccc; padding: 20px; margin: 20px;">
 <?php
  echo "<p><a href=\"delete.php?identifier=$identifier\" > <button> remove: $identifier </button> </a></p> "
  ?>
  <p><a href="list_identifiers.php">← Return to the main page</a></p>
    </div>
  <?php include 'footer.php'; ?>
