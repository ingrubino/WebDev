<?php
/*
// Matrice di esempio con valori decimali come caratteri
$matrix = [
    ['0.1', '2.3'],
    ['0.5', '4.1'],
    ['1.2', '3.8'],
    ['1.8', '5.2'],
    ['2.5', '6.1'],
    ['3.0', '4.9'],
    ['3.7', '7.3'],
    ['4.2', '8.1'],
    ['4.8', '6.7'],
    ['5.5', '9.2']
];

$identifier="identificativo oggetto";
*/
// Titoli del grafico
$titoloGrafico = "Time - current table of " . $identifier;
$titoloAsseX = "time (ms)";
$titoloAsseY = "Current (A)";

// Dimensioni dell'immagine SVG
$larghezza = 800;
$altezza = 600;
$margine = 60;

// Estrai i dati dalla matrice
$datiX = [];
$datiY = [];
foreach ($matrix as $riga) {
    $datiX[] = (float)$riga[0];
    $datiY[] = (float)$riga[1];
}

// Trova i valori massimi e minimi
$minX = min($datiX);
$maxX = max($datiX);
$minY = min($datiY);
$maxY = max($datiY);

// Aggiungi un margine per una migliore visualizzazione
$rangeX = $maxX - $minX;
$rangeY = $maxY - $minY;
$minX -= $rangeX * 0.1;
$maxX += $rangeX * 0.1;
$minY -= $rangeY * 0.1;
$maxY += $rangeY * 0.1;
$rangeX = $maxX - $minX;
$rangeY = $maxY - $minY;

// Area del grafico
$graficoLarghezza = $larghezza - 2 * $margine;
$graficoAltezza = $altezza - 2 * $margine;

// Funzione per convertire coordinate dati in coordinate SVG
function convertiCoordinata($x, $y, $minX, $maxX, $minY, $maxY, $graficoLarghezza, $graficoAltezza, $margine, $altezza) {
    $xPixel = $margine + (($x - $minX) / ($maxX - $minX)) * $graficoLarghezza;
    $yPixel = $altezza - $margine - (($y - $minY) / ($maxY - $minY)) * $graficoAltezza;
    return [round($xPixel, 2), round($yPixel, 2)];
}

// Inizia l'output SVG
header('Content-Type: image/svg+xml');
?>
<svg width="<?php echo $larghezza; ?>" height="<?php echo $altezza; ?>" xmlns="http://www.w3.org/2000/svg">
<style>
    .griglia { stroke: #dcdcdc; stroke-width: 1; }
    .assi { stroke: #b4b4b4; stroke-width: 2; }
    .punto { fill: #0064ff; stroke: #0064ff; }
    .linea { stroke: #ff0000; stroke-width: 2; fill: none; }
    .etichetta { font-family: Arial, sans-serif; font-size: 12px; fill: #000000; }
    .titolo { font-family: Arial, sans-serif; font-size: 16px; font-weight: bold; fill: #000000; }
</style>

<rect width="100%" height="100%" fill="white" />

<?php
// Griglia orizzontale
$passoGrigliaY = max(0.1, ceil($rangeY / 10));
for ($y = ceil($minY / $passoGrigliaY) * $passoGrigliaY; $y <= $maxY; $y += $passoGrigliaY) {
    if ($y < $minY) continue;
    list($x1, $y1) = convertiCoordinata($minX, $y, $minX, $maxX, $minY, $maxY, $graficoLarghezza, $graficoAltezza, $margine, $altezza);
    list($x2, $y2) = convertiCoordinata($maxX, $y, $minX, $maxX, $minY, $maxY, $graficoLarghezza, $graficoAltezza, $margine, $altezza);
    echo "<line x1='$x1' y1='$y1' x2='$x2' y2='$y2' class='griglia' />\n";
    echo "<text x='" . ($margine - 25) . "' y='" . ($y1 + 4) . "' class='etichetta'>" . number_format($y, 1) . "</text>\n";
}

// Griglia verticale
$passoGrigliaX = max(0.1, ceil($rangeX / 10));
for ($x = ceil($minX / $passoGrigliaX) * $passoGrigliaX; $x <= $maxX; $x += $passoGrigliaX) {
    if ($x < $minX) continue;
    list($x1, $y1) = convertiCoordinata($x, $minY, $minX, $maxX, $minY, $maxY, $graficoLarghezza, $graficoAltezza, $margine, $altezza);
    list($x2, $y2) = convertiCoordinata($x, $maxY, $minX, $maxX, $minY, $maxY, $graficoLarghezza, $graficoAltezza, $margine, $altezza);
    echo "<line x1='$x1' y1='$y1' x2='$x2' y2='$y2' class='griglia' />\n";
    echo "<text x='" . ($x1 - 15) . "' y='" . ($altezza - $margine + 20) . "' class='etichetta'>" . number_format($x, 1) . "</text>\n";
}

// Assi cartesiani
// Asse X
list($x1, $y1) = convertiCoordinata($minX, 0, $minX, $maxX, $minY, $maxY, $graficoLarghezza, $graficoAltezza, $margine, $altezza);
list($x2, $y2) = convertiCoordinata($maxX, 0, $minX, $maxX, $minY, $maxY, $graficoLarghezza, $graficoAltezza, $margine, $altezza);
echo "<line x1='$x1' y1='$y1' x2='$x2' y2='$y2' class='assi' />\n";

// Asse Y
list($x1, $y1) = convertiCoordinata(0, $minY, $minX, $maxX, $minY, $maxY, $graficoLarghezza, $graficoAltezza, $margine, $altezza);
list($x2, $y2) = convertiCoordinata(0, $maxY, $minX, $maxX, $minY, $maxY, $graficoLarghezza, $graficoAltezza, $margine, $altezza);
echo "<line x1='$x1' y1='$y1' x2='$x2' y2='$y2' class='assi' />\n";

// Punti e linee del grafico
$puntiPath = "";
foreach ($matrix as $index => $riga) {
    $x = (float)$riga[0];
    $y = (float)$riga[1];
    list($xPixel, $yPixel) = convertiCoordinata($x, $y, $minX, $maxX, $minY, $maxY, $graficoLarghezza, $graficoAltezza, $margine, $altezza);
    
    echo "<circle cx='$xPixel' cy='$yPixel' r='4' class='punto' />\n";
    
    if ($index === 0) {
        $puntiPath .= "M $xPixel $yPixel ";
    } else {
        $puntiPath .= "L $xPixel $yPixel ";
    }
}

echo "<path d='$puntiPath' class='linea' />\n";

// Titoli
echo "<text x='" . ($larghezza / 2) . "' y='30' class='titolo' text-anchor='middle'>$titoloGrafico</text>\n";
echo "<text x='" . ($larghezza / 2) . "' y='" . ($altezza - 15) . "' class='titolo' text-anchor='middle'>$titoloAsseX</text>\n";
echo "<text x='30' y='" . ($altezza / 2) . "' class='titolo' text-anchor='middle' transform='rotate(-90, 30, " . ($altezza / 2) . ")'>$titoloAsseY</text>\n";
?>
</svg>
